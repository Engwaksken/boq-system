<?php

namespace App\Mail;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invitation $invitation,
        public string $code,
        public User $inviter,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('You’re invited to join :organisation', [
            'organisation' => $this->invitation->organisation->name,
        ]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.team-invitation', with: [
            'organisation' => $this->invitation->organisation->name,
            'inviter' => $this->inviter->name,
            'code' => $this->code,
            'expiresAt' => $this->invitation->expires_at,
            'acceptUrl' => route('team.index'),
        ]);
    }
}
