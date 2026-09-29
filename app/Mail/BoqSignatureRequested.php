<?php

namespace App\Mail;

use App\Models\Boq;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Asks a client to review and sign a BOQ through a one-time signing link.
 */
class BoqSignatureRequested extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Boq $boq,
        public User $sender,
        public string $url,
        public ?Carbon $expiresAt,
        public ?string $personalMessage = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Please sign: BOQ :ref – :name', ['ref' => $this->boq->reference, 'name' => $this->boq->name]),
            replyTo: [new Address($this->sender->email, $this->sender->name)],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.boq-signature-request', with: [
            'company' => $this->boq->brandingIdentity()['company_name'] ?? $this->sender->name,
        ]);
    }
}
