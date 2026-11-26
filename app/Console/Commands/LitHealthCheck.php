<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
class LitHealthCheck extends Command {
 protected $signature = 'lit:health-check';
 protected $description = 'Record LIT monitor availability';
 public function handle(): int {
  foreach(DB::table('lit_monitors')->where('enabled',true)->cursor() as $m) {
   $last=DB::table('lit_health_checks')->where('provider','custom:'.$m->id)->max('checked_at');
   if ($last && \Carbon\Carbon::parse($last,'UTC')->gt(now()->utc()->startOfMinute()->subMinutes($m->interval_minutes))) continue;
   $before=DB::table('lit_monitor_incidents')->where('monitor_id',$m->id)->max('id')??0;
   $this->check('custom:'.$m->id,$m->url,$m->type==='rss'?$m->id:null,$m->type);
   if ($m->email_enabled) $this->alerts($m,$before);
  }
  DB::table('lit_health_checks')->where('checked_at','<',now()->utc()->subDays(31))->delete();
  return self::SUCCESS;
 }
 private function alerts(object $m,int $before): void {
  $team=\App\Models\Team::find($m->team_id);
  if (!$team || !$team->emailNotificationSettings?->use_instance_email_settings || !instanceSettings()->resend_enabled || !\App\Services\LitHealthRecipients::emails($team)) return;
  $rows=DB::table('lit_health_checks')->where('provider','custom:'.$m->id)->orderByDesc('checked_at')->limit(2)->get();
  $latest=$rows->first(); if (!$latest) return;
  $context=['url'=>$m->url,'type'=>$m->type,'code'=>$latest->code,'ms'=>$latest->ms,'interval'=>$m->interval_minutes,'checked_at'=>$latest->checked_at];
  $state=$m->alert_state;
  $next=$state;
  if (!$latest->reachable) {
   if ($rows->count()===2 && !$rows[1]->reachable) $next='unreachable';
  } else {
   $next=$m->type==='status'?($latest->provider_status??'unknown'):'reachable';
  }
  try {
   if ($state!==$next && $next!==null) {
    if ($state!==null || !in_array($next,['reachable','operational','unknown'],true)) {
     \App\Services\LitHealthRecipients::send($team,$m->name,str_replace('_',' ',$next),'Previous state: '.($state??'not established').'. Current state: '.$next.'.'.($next==='unreachable'?' Two consecutive health checks failed.':''),false,$context);
    }
    DB::table('lit_monitors')->where('id',$m->id)->where('email_enabled',true)->update(['alert_state'=>$next]);
   }
   if ($m->type==='rss' && $state!==null && $before>0) {
    $new=DB::table('lit_monitor_incidents')->where('monitor_id',$m->id)->where('id','>',$before)->orderBy('id')->get();
    if ($new->isNotEmpty()) \App\Services\LitHealthRecipients::send($team,$m->name,'New RSS announcements','New provider announcements were retrieved. RSS alone does not establish current provider health.',false,$context+['announcements'=>$new->take(5)->map(fn($entry)=>['title'=>$entry->title,'published'=>$entry->published,'description'=>mb_substr($entry->description??'',0,1500)])->values()->all()]);
   }
  } catch (\Throwable $e) {
   \Illuminate\Support\Facades\Log::warning('LIT monitor notification enqueue failed',['monitor_id'=>$m->id,'exception'=>get_class($e)]);
  }
 }
 private function check(string $provider,string $url, ?int $feedId=null,string $type='http'): void {
  $minute=now()->utc()->startOfMinute(); $start=microtime(true); $code=null; $reachable=false; $status=null;
  try {
   $p=parse_url($url); $host=$p['host']??''; $scheme=$p['scheme']??''; $port=$scheme==='https'?443:80;
   if (!in_array($scheme,['http','https'],true)||isset($p['user'])||isset($p['pass'])||isset($p['port'])&&$p['port']!==$port||!preg_match('/^[a-z0-9.-]+$/iD',$host)||!str_contains($host,'.')) throw new \RuntimeException('Invalid endpoint');
   $ips=gethostbynamel($host);
   if (!$ips) throw new \RuntimeException('DNS failed');
   foreach($ips as $ip) if (!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_GLOBAL_RANGE)) throw new \RuntimeException('Nonpublic address');
   $response=Http::connectTimeout(3)->timeout(5)->withoutRedirecting()->withOptions(['proxy'=>'','curl'=>[CURLOPT_RESOLVE=>[$host.':'.$port.':'.$ips[0]],CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4,CURLOPT_MAXFILESIZE=>2097152,CURLOPT_NOPROXY=>'*']])->get($url);
   $code=$response->status(); $reachable=$code>=200&&$code<400;
   if ($type==='status') {
    $reachable=false;
    if ($code>=200 && $code<300) {
     $data=$response->json();
     $indicator=data_get($data,'status.indicator');
     $status=match($indicator) {'none'=>'operational','minor'=>'degraded','major'=>'partial_outage','critical'=>'major_outage','maintenance'=>'maintenance',default=>'unknown'};
     $reachable=$status!=='unknown';
    }
   }
   if ($feedId!==null) {
    $reachable=false;
    if ($code>=200 && $code<300) { $this->storeFeed($feedId,$response->body()); $reachable=true; }
   }
  } catch (\Exception $e) {}
  DB::table('lit_health_checks')->insertOrIgnore(['provider'=>$provider,'checked_at'=>$minute,'reachable'=>$reachable,'provider_status'=>$status,'code'=>$code,'ms'=>(int)round((microtime(true)-$start)*1000)]);
 }
 private function storeFeed(int $id,string $body): void {
  if (strlen($body)>2097152 || preg_match('/<!DOCTYPE|<!ENTITY/i',$body)) throw new \RuntimeException('Unsafe feed');
  $xml=simplexml_load_string($body,\SimpleXMLElement::class,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
  if ($xml===false || !in_array($xml->getName(),['rss','feed'],true)) throw new \RuntimeException('Invalid RSS/Atom');
  $entries=$xml->getName()==='rss'?$xml->xpath('./channel/item'):$xml->xpath('/*[local-name()="feed"]/*[local-name()="entry"]');
  if ($entries===false) throw new \RuntimeException('Invalid entries');
  foreach(array_slice($entries,0,200) as $entry) {
   $field=static function($name) use($entry) { $nodes=$entry->xpath('./*[local-name()="'.$name.'"]'); return (string)($nodes[0]??''); };
   $title=substr(strip_tags($field('title')),0,4000);
   $description=substr(strip_tags($field('description')?:$field('summary')?:$field('content')),0,20000);
   $published=substr($field('pubDate')?:$field('updated')?:$field('published'),0,200);
   $fingerprint=hash('sha256',json_encode([$field('guid')?:$field('id'),$title,$description,$published],JSON_INVALID_UTF8_SUBSTITUTE));
   DB::table('lit_monitor_incidents')->insertOrIgnore(['monitor_id'=>$id,'fingerprint'=>$fingerprint,'title'=>$title,'description'=>$description,'published'=>$published?:null,'created_at'=>now(),'updated_at'=>now()]);
  }
 }
}
