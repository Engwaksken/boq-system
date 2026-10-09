<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent by Admin > Settings > Mail to confirm the mail configuration works. */
class MailSettingsTest extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $appName) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Mail settings test'));
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>'.e(__('This is a test email from :app. Your mail settings are working.', [
            'app' => $this->appName,
        ])).'</p>');
    }
}
