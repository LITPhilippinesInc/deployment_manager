<?php
namespace App\Notifications;
use App\Notifications\Channels\EmailChannel;
use Illuminate\Notifications\Messages\MailMessage;
class LitServiceHealth extends CustomEmailNotification {
 public function __construct(public string $monitorName, public string $event, public string $detail, public ?string $emails = null) {
  $this->onQueue('high');
 }
 public function via(object $notifiable): array {
  $settings=$notifiable->emailNotificationSettings;
  if (!$settings?->use_instance_email_settings || !instanceSettings()->resend_enabled || !$this->emails || !in_array($this->emails,$notifiable->getRecipients(),true)) return [];
  return [EmailChannel::class];
 }
 public function toMail(object $notifiable): MailMessage {
  return (new MailMessage)->subject('LIT Service Health: '.$this->event)
   ->line('Monitor: '.$this->monitorName)->line($this->detail)
   ->line('Review the Service Health dashboard for details.');
 }
}
