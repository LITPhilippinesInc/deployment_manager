<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
class LitHealthCheck extends Command {
 protected $signature = 'lit:health-check';
 protected $description = 'Record LIT monitor availability';
 public function handle(): int {
  $this->check('Auth0','https://status.auth0.com/');
  foreach(DB::table('lit_monitors')->where('enabled',true)->cursor() as $m) $this->check('custom:'.$m->id,$m->url);
  DB::table('lit_health_checks')->where('checked_at','<',now()->utc()->subDays(31))->delete();
  return self::SUCCESS;
 }
 private function check(string $provider,string $url): void {
  $minute=now()->utc()->startOfMinute(); $start=microtime(true); $code=null; $reachable=false;
  try {
   $p=parse_url($url); $host=$p['host']??''; $scheme=$p['scheme']??''; $port=$scheme==='https'?443:80;
   if (!in_array($scheme,['http','https'],true)||isset($p['user'])||isset($p['pass'])||isset($p['port'])&&$p['port']!==$port||!preg_match('/^[a-z0-9.-]+$/iD',$host)||!str_contains($host,'.')) throw new \RuntimeException('Invalid endpoint');
   $ips=gethostbynamel($host);
   if (!$ips) throw new \RuntimeException('DNS failed');
   foreach($ips as $ip) if (!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_GLOBAL_RANGE)) throw new \RuntimeException('Nonpublic address');
   $response=Http::connectTimeout(3)->timeout(5)->withoutRedirecting()->withOptions(['proxy'=>'','curl'=>[CURLOPT_RESOLVE=>[$host.':'.$port.':'.$ips[0]],CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4]])->get($url);
   $code=$response->status(); $reachable=$code>=200&&$code<400;
  } catch (\Exception $e) {}
  DB::table('lit_health_checks')->insertOrIgnore(['provider'=>$provider,'checked_at'=>$minute,'reachable'=>$reachable,'code'=>$code,'ms'=>(int)round((microtime(true)-$start)*1000)]);
 }
}
