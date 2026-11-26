<?php
namespace App\Livewire;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
class ServiceHealth extends Component {
    public array $recipientIds = [];
    public string $recipientMessage = '';
    public function mount(): void {
        $this->recipientIds=DB::table('lit_notification_recipients')->where('team_id',currentTeam()->id)->pluck('user_id')->map(fn($id)=>(string)$id)->all();
    }
    public function saveRecipients(): void {
        $this->authorizeManage();
        $this->validate(['recipientIds'=>'array','recipientIds.*'=>'integer|distinct']);
        $team=\App\Models\Team::findOrFail(currentTeam()->id);
        $ids=$team->members()->whereIn('users.id',$this->recipientIds)->get(['users.id','users.email'])->filter(fn($u)=>\App\Services\LitHealthRecipients::eligible($u->email))->pluck('id');
        abort_unless($ids->count()===count($this->recipientIds),422);
        DB::transaction(function() use($team,$ids) {
            DB::table('lit_notification_recipients')->where('team_id',$team->id)->delete();
            foreach($ids as $id) DB::table('lit_notification_recipients')->insert(['team_id'=>$team->id,'user_id'=>$id]);
        });
        $this->recipientMessage='Recipients saved.';
    }
    public function testRecipients(): void {
        $this->authorizeManage();
        try {
            $count=\App\Services\LitHealthRecipients::send(\App\Models\Team::findOrFail(currentTeam()->id),'Email Test','Test Notification','Testing Service Health email delivery.',true);
            $this->recipientMessage=$count ? "Test email accepted for $count recipient(s)." : 'No eligible saved recipients or instance Resend is not enabled.';
        } catch (\Throwable $e) {
            $this->recipientMessage='Test delivery failed. Check email settings and recipient addresses.';
        }
    }
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
        $this->validate(['type'=>'required|in:http,rss,status','interval'=>'required|integer|in:1,5,15','name'=>'required|string|max:100','url'=>'required|url:http,https|max:2048']);
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
    public function toggleEmail(int $id): void {
        $this->authorizeManage();
        $q=DB::table('lit_monitors')->where('team_id',currentTeam()->id)->where('id',$id);
        $m=(clone $q)->first(); abort_unless($m,404);
        $q->update(['email_enabled'=>!$m->email_enabled,'alert_state'=>null,'updated_at'=>now()]);
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
        foreach($monitors as $m) $items[]=['name'=>$m->name,'url'=>$m->url,'id'=>$m->id,'type'=>$m->type,'interval'=>$m->interval_minutes,'incidents'=>$m->type==='rss'?DB::table('lit_monitor_incidents')->where('monitor_id',$m->id)->orderByDesc('id')->limit(50)->get():[],'enabled'=>$m->enabled,'email_enabled'=>$m->email_enabled]+$this->history('custom:'.$m->id);
        return view('livewire.service-health',['items'=>$items,'recipientMembers'=>auth()->user()?->isAdminOfTeam(currentTeam()->id)?\App\Models\Team::findOrFail(currentTeam()->id)->members()->get(['users.id','users.name','users.email']):collect(),'canManage'=>auth()->user()?->isAdminOfTeam(currentTeam()->id)]);
    }
    private function history(string $provider): array {
        $now = now()->utc()->startOfMinute();
        $rows = DB::table('lit_health_checks')->where('provider',$provider)->where('checked_at','>=',$now->copy()->subDays(30))->orderBy('checked_at')->get();
        $bars = array_fill(0, 60, null);
        $counts = array_fill(0, 60, 0);
        $uptime = [];
        $statusBars=array_fill(0,60,'unknown');
        $severity=['unknown'=>0,'operational'=>1,'maintenance'=>2,'degraded'=>3,'partial_outage'=>4,'major_outage'=>5];
        foreach ([1,7,30] as $days) {
            $subset = $rows->filter(fn ($r) => \Carbon\Carbon::parse($r->checked_at,'UTC')->gte($now->copy()->subDays($days)));
            $uptime[$days] = $subset->count() ? round(100 * $subset->where('reachable',true)->count() / $subset->count(), 2) : null;
        }
        foreach ($rows as $r) {
            $age = $now->timestamp - \Carbon\Carbon::parse($r->checked_at,'UTC')->timestamp;
            if ($age < 0 || $age >= 86400) continue;
            $i = 59 - intdiv($age,1440);
            $counts[$i]++;
            $s=$r->provider_status??'unknown';
            if (($severity[$s]??0)>$severity[$statusBars[$i]]) $statusBars[$i]=$s;
            $bars[$i] = ($bars[$i] === false || ! $r->reachable) ? false : true;
        }
        return ['statusBars'=>$statusBars,'bars'=>$bars,'counts'=>$counts,'uptime'=>$uptime,'latest'=>$rows->last()];
    }
}
