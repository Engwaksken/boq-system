<?php

namespace App\Mail;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

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
        $organisation = $this->invitation->organisation;

        return new Content(view: 'emails.team-invitation', with: [
            'organisation' => $organisation->name,
            'inviter' => $this->inviter->name,
            'code' => $this->code,
            'expiresAt' => $this->invitation->expires_at,
            'acceptUrl' => route('team.index'),
            'logoUrl' => $organisation->logo_path ? Storage::disk('public')->url($organisation->logo_path) : null,
            'platformName' => config('app.name'),
        ]);
    }
}
