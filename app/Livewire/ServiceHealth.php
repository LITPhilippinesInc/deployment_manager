<?php
namespace App\Livewire;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
class ServiceHealth extends Component {
    public function render() {
        abort_unless(auth()->check() && app(\App\Services\Access\AccessResolver::class)->coversWholeTeam(auth()->user(), currentTeam()->id), 403);
        $now = now()->utc()->startOfMinute();
        $rows = DB::table('lit_health_checks')->where('provider','Auth0')->where('checked_at','>=',$now->copy()->subDays(30))->orderBy('checked_at')->get();
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
        return view('livewire.service-health', ['bars'=>$bars,'counts'=>$counts,'uptime'=>$uptime,'latest'=>$rows->last()]);
    }
}
