<?php

namespace App\Mail;

use App\Models\Invitation;
use App\Models\Organisation;
use App\Models\SiteSetting;
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
            'role' => $this->invitation->role?->name,
            'code' => $this->code,
            'expiresAt' => $this->invitation->expires_at,
            'acceptUrl' => route('team.index'),
            'registerUrl' => SiteSetting::get('allow_registration', true) && \Illuminate\Support\Facades\Route::has('register')
                ? route('register')
                : null,
            'logoUrl' => $this->logoUrl($organisation),
            'platformName' => $this->platformName(),
            'platformUrl' => config('app.url'),
        ]);
    }

    /** The organisation logo, falling back to the platform logo. */
    private function logoUrl(Organisation $organisation): ?string
    {
        if ($organisation->logo_path) {
            return Storage::disk('public')->url($organisation->logo_path);
        }

        $logo = SiteSetting::get('logo', '');

        return $logo ? asset('storage/'.$logo) : null;
    }

    private function platformName(): string
    {
        return (string) (SiteSetting::get('system_name', config('app.name')) ?: config('app.name'));
    }
}
