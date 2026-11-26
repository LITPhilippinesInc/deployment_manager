<?php
namespace App\Livewire;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
class ServiceHealth extends Component {
    public bool $showMonitorModal = false;
    public string $name = '';
    public string $type = 'http';
    public int $interval = 1;
    public string $url = '';
    public ?int $editing = null;
    private function authorizeManage(): void {
        abort_unless(auth()->user()?->isAdminOfTeam(currentTeam()->id), 403);
    }
    public function addMonitor(): void {
        $this->authorizeManage();
        $this->cancelEdit();
        $this->showMonitorModal=true;
    }
    public function saveMonitor(): void {
        $this->authorizeManage();
        $this->validate(['type'=>'required|in:http,rss','interval'=>'required|integer|in:1,5,15','name'=>'required|string|max:100','url'=>'required|url:http,https|max:2048']);
        $p=parse_url($this->url);
        if (isset($p['user']) || isset($p['pass']) || isset($p['port']) || !preg_match('/^[a-z0-9.-]+$/iD',$p['host']??'') || !str_contains($p['host']??'','.')) {
            $this->addError('url','Use a public hostname without credentials or custom port.'); return;
        }
        $q=DB::table('lit_monitors')->where('team_id',currentTeam()->id);
        if ($this->editing) {
            $m=(clone $q)->where('id',$this->editing)->first(); abort_unless($m,404);
            if ($m->url !== $this->url || $m->type !== $this->type) { $this->addError('url','Create a new monitor to change endpoint and preserve history.'); return; }
            (clone $q)->where('id',$this->editing)->update(['name'=>$this->name,'interval_minutes'=>$this->interval,'updated_at'=>now()]);
        } else {
            if ($q->count()>=10) { $this->addError('name','Maximum 10 monitors per team.'); return; }
            DB::table('lit_monitors')->insert(['team_id'=>currentTeam()->id,'name'=>$this->name,'url'=>$this->url,'type'=>$this->type,'interval_minutes'=>$this->interval,'created_at'=>now(),'updated_at'=>now()]);
        }
        $this->reset('name','url','editing','type','interval','showMonitorModal');
    }
    public function editMonitor(int $id): void {
        $this->authorizeManage();
        $m=DB::table('lit_monitors')->where('team_id',currentTeam()->id)->where('id',$id)->first(); abort_unless($m,404);
        $this->resetValidation(); $this->showMonitorModal=true; $this->editing=$id; $this->name=$m->name; $this->url=$m->url; $this->type=$m->type; $this->interval=$m->interval_minutes;
    }
    public function cancelEdit(): void {
        $this->resetValidation();
        $this->reset('name','url','editing','type','interval','showMonitorModal');
    }
    public function toggleMonitor(int $id): void {
        $this->authorizeManage();
        $q=DB::table('lit_monitors')->where('team_id',currentTeam()->id)->where('id',$id);
        $m=(clone $q)->first(); abort_unless($m,404); $q->update(['enabled'=>!$m->enabled,'updated_at'=>now()]);
    }
    public function deleteMonitor(int $id): void {
        $this->authorizeManage();
        DB::transaction(function() use($id) {
            $deleted=DB::table('lit_monitors')->where('team_id',currentTeam()->id)->where('id',$id)->delete();
            abort_unless($deleted,404);
            DB::table('lit_health_checks')->where('provider','custom:'.$id)->delete();
        });
        $this->cancelEdit();
    }
    public function render() {
        abort_unless(auth()->check() && app(\App\Services\Access\AccessResolver::class)->coversWholeTeam(auth()->user(), currentTeam()->id), 403);
        $monitors=DB::table('lit_monitors')->where('team_id',currentTeam()->id)->orderBy('id')->get();
        $items=[];
        foreach($monitors as $m) $items[]=['name'=>$m->name,'url'=>$m->url,'id'=>$m->id,'type'=>$m->type,'interval'=>$m->interval_minutes,'incidents'=>$m->type==='rss'?DB::table('lit_monitor_incidents')->where('monitor_id',$m->id)->orderByDesc('id')->limit(50)->get():[],'enabled'=>$m->enabled]+$this->history('custom:'.$m->id);
        return view('livewire.service-health',['items'=>$items,'canManage'=>auth()->user()?->isAdminOfTeam(currentTeam()->id)]);
    }
    private function history(string $provider): array {
        $now = now()->utc()->startOfMinute();
        $rows = DB::table('lit_health_checks')->where('provider',$provider)->where('checked_at','>=',$now->copy()->subDays(30))->orderBy('checked_at')->get();
        $bars = array_fill(0, 60, null);
        $counts = array_fill(0, 60, 0);
        $uptime = [];
        foreach ([1,7,30] as $days) {
            $subset = $rows->filter(fn ($r) => \Carbon\Carbon::parse($r->checked_at,'UTC')->gte($now->copy()->subDays($days)));
            $uptime[$days] = $subset->count() ? round(100 * $subset->where('reachable',true)->count() / $subset->count(), 2) : null;
        }
        foreach ($rows as $r) {
            $age = $now->timestamp - \Carbon\Carbon::parse($r->checked_at,'UTC')->timestamp;
            if ($age < 0 || $age >= 86400) continue;
            $i = 59 - intdiv($age,1440);
            $counts[$i]++;
            $bars[$i] = ($bars[$i] === false || ! $r->reachable) ? false : true;
        }
        return ['bars'=>$bars,'counts'=>$counts,'uptime'=>$uptime,'latest'=>$rows->last()];
    }
}
