<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HardwarePriceChanged extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $itemName,
        public readonly string $supplier,
        public readonly string $message,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Price alert: '.$this->itemName)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->message)
            ->line('Supplier: '.$this->supplier);
    }
}
