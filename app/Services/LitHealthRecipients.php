<?php
namespace App\Services;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
class LitHealthRecipients {
 public static function eligible(string $email): bool {
  return filter_var($email,FILTER_VALIDATE_EMAIL)!==false && !preg_match('/@(?:[^@]+\.)?(?:example\.(?:com|net|org)|localhost|invalid|test)$/iD',$email);
 }
 public static function emails(Team $team): array {
  $ids=DB::table('lit_notification_recipients')->where('team_id',$team->id)->pluck('user_id');
  return $team->members()->whereIn('users.id',$ids)->pluck('users.email')->filter(fn($email)=>self::eligible($email))->unique()->values()->all();
 }
 public static function send(Team $team,string $name,string $event,string $detail,bool $sync=false): int {
  $count=0;
  foreach(self::emails($team) as $email) {
   $notification=new \App\Notifications\LitServiceHealth($name,$event,$detail,$email);
   if (!$notification->via($team)) continue;
   if ($sync) $team->notifyNow($notification); else $team->notify($notification);
   $count++;
  }
  return $count;
 }
}
