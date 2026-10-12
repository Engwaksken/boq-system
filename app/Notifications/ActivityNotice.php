<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** One email about a project event (expense recorded, deadline reminder). Sent immediately or via digest. */
class ActivityNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly ?string $url = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->message);

        if ($this->url) {
            $mail->action('Open in BOQ System', $this->url);
        }

        return $mail->line('You can change how you receive these in Profile > Preferences > Notifications.');
    }
}
