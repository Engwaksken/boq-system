<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A summary of held-back project emails for a user on an hourly, daily or weekly digest. */
class ActivityDigest extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param list<array{title: string, message: string, url: ?string}> $items */
    public function __construct(
        public readonly string $frequency,
        public readonly array $items,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = count($this->items);
        $mail = (new MailMessage)
            ->subject(sprintf('Your %s summary: %d update%s', $this->frequency, $count, $count === 1 ? '' : 's'))
            ->greeting('Hello '.$notifiable->name.',');

        foreach (array_slice($this->items, 0, 50) as $item) {
            $mail->line('**'.$item['title'].'**: '.$item['message'])
                ->line($item['url'] ? 'Open: '.$item['url'] : ' ');
        }

        return $mail->line('You can change how you receive these in Profile > Preferences > Notifications.');
    }
}
