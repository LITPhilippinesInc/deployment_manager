<?php
namespace App\Notifications;
use App\Notifications\Channels\EmailChannel;
use Illuminate\Notifications\Messages\MailMessage;
class LitServiceHealth extends CustomEmailNotification {
 public function __construct(public string $monitorName, public string $event, public string $detail, public ?string $emails = null, public array $context = []) {
  $this->onQueue('high');
 }
 public function via(object $notifiable): array {
  $settings=$notifiable->emailNotificationSettings;
  if (!$settings?->use_instance_email_settings || !instanceSettings()->resend_enabled || !$this->emails || !in_array($this->emails,$notifiable->getRecipients(),true)) return [];
  return [EmailChannel::class];
 }
 public function toMail(object $notifiable): MailMessage {
  $labels=['unreachable'=>'SERVICE DOWN','reachable'=>'RECOVERED','operational'=>'OPERATIONAL','degraded'=>'DEGRADED','partial outage'=>'PARTIAL OUTAGE','major outage'=>'MAJOR OUTAGE','maintenance'=>'MAINTENANCE','unknown'=>'STATUS UNKNOWN','New RSS announcements'=>'PROVIDER UPDATE'];
  $label=$labels[$this->event]??strtoupper($this->event);
  $code=$this->context['code']??null;
  $subject='['.$label.'] '.$this->monitorName.($this->event==='unreachable' && $code ? ' — HTTP '.$code : '');
  $mail=(new MailMessage)->subject($subject)->greeting($label)->line('Service: '.$this->monitorName)->line($this->detail);
  if(isset($this->context['url'])) $mail->line('Endpoint: '.$this->context['url']);
  if(isset($this->context['type'])) $mail->line('Monitor type: '.strtoupper($this->context['type']));
  if($code!==null) $mail->line('HTTP response: '.$code.' — '.(\Symfony\Component\HttpFoundation\Response::$statusTexts[$code]??'Unknown status'));
  elseif(isset($this->context['type'])) $mail->line('HTTP response: No response recorded');
  if(isset($this->context['ms'])) $mail->line('Response time: '.$this->context['ms'].' ms');
  if(isset($this->context['interval'])) $mail->line('Check interval: '.$this->context['interval'].' minute(s)');
  if(isset($this->context['checked_at'])) $mail->line('Detected at: '.\Carbon\Carbon::parse($this->context['checked_at'],'UTC')->setTimezone('Asia/Manila')->format('F j, Y, g:i A').' (PHT)');
  foreach($this->context['announcements']??[] as $entry) {
   $mail->line('Announcement: '.$entry['title'])->line('Published: '.($entry['published']?:'Not provided'))->line($entry['description']?:'No description provided');
  }
  $action=match($this->event) {
   'unreachable'=>'Verify the endpoint and investigate the HTTP or connectivity error. This does not necessarily indicate a provider-wide outage.',
   'reachable','operational'=>'Verify normal service operation and close any related incident if appropriate.',
   'New RSS announcements'=>'Review the provider feed for impact and recommended actions.',
   'maintenance'=>'Review the provider maintenance notice and prepare for possible disruption.',
   default=>'Review the provider status and assess impact on LIT services.'
  };
  return $mail->line('Recommended action: '.$action)->action('View Service Health Dashboard',url('/service-health'));
 }
}
