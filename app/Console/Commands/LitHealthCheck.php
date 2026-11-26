<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
class LitHealthCheck extends Command {
    protected $signature = 'lit:health-check';
    protected $description = 'Record LIT external status page availability';
    public function handle(): int {
        $minute = now()->utc()->startOfMinute();
        $start = microtime(true);
        $code = null; $ms = null; $reachable = false;
        try {
            $response = Http::connectTimeout(3)->timeout(5)->withoutRedirecting()->get('https://status.auth0.com/');
            $code = $response->status();
            $reachable = $code >= 200 && $code < 400;
            $ms = (int) round((microtime(true) - $start) * 1000);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $ms = (int) round((microtime(true) - $start) * 1000);
        }
        DB::table('lit_health_checks')->insertOrIgnore(['provider'=>'Auth0','checked_at'=>$minute,'reachable'=>$reachable,'code'=>$code,'ms'=>$ms]);
        DB::table('lit_health_checks')->where('checked_at','<',now()->utc()->subDays(31))->delete();
        return self::SUCCESS;
    }
}
