<?php

namespace App\Mail;

use App\Models\Boq;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BoqShared extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Boq $boq,
        public User $sender,
        public string $subjectLine,
        public ?string $personalMessage,
        public string $pdfBytes,
        public string $pdfName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
            replyTo: [new Address($this->sender->email, $this->sender->name)],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.boq-shared', with: [
            'company' => $this->boq->brandingIdentity()['company_name'] ?? $this->sender->name,
        ]);
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->pdfBytes, $this->pdfName)->withMime('application/pdf')];
    }
}
