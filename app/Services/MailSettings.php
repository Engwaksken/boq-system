<?php

namespace App\Services;

use App\Models\SiteSetting;
use Throwable;

/**
 * Applies the mail (SMTP) settings stored in Site Settings over the .env
 * defaults, so mail can be reconfigured from the admin without editing files.
 * Values that are not set fall back to the framework config. The settings table
 * may not exist yet on a fresh install, so every read is guarded.
 */
class MailSettings
{
    public const KEYS = [
        'mail_mailer', 'mail_host', 'mail_port', 'mail_username',
        'mail_password', 'mail_encryption', 'mail_from_address', 'mail_from_name',
    ];

    /** Stored values keyed by name, or [] when the settings table is unavailable. */
    public function stored(): array
    {
        try {
            return SiteSetting::whereIn('key', self::KEYS)->pluck('value', 'key')->all();
        } catch (Throwable) {
            return [];
        }
    }

    public function configured(): bool
    {
        return filled($this->stored()['mail_mailer'] ?? null);
    }

    /** The decrypted stored password, or null when none is set. */
    public function password(): ?string
    {
        $stored = $this->stored()['mail_password'] ?? null;
        if (blank($stored)) {
            return null;
        }
        try {
            return decrypt($stored);
        } catch (Throwable) {
            return $stored; // value saved before encryption, or a key mismatch
        }
    }

    /** Override the mail config from the stored settings (called once at boot). */
    public function apply(): void
    {
        $this->applyValues($this->stored(), $this->password());
    }

    /** Override the mail config from a raw set of values (e.g. the settings form). */
    public function applyValues(array $values, ?string $password = null): void
    {
        $mailer = $values['mail_mailer'] ?? null;
        if (blank($mailer)) {
            return; // keep the .env configuration
        }

        config(['mail.default' => $mailer]);

        if ($mailer === 'smtp') {
            $defaults = config('mail.mailers.smtp');
            $defaults = is_array($defaults) ? $defaults : [];
            config(['mail.mailers.smtp' => array_merge($defaults, [
                'transport' => 'smtp',
                'host' => filled($values['mail_host'] ?? null) ? $values['mail_host'] : ($defaults['host'] ?? '127.0.0.1'),
                'port' => (int) (filled($values['mail_port'] ?? null) ? $values['mail_port'] : ($defaults['port'] ?? 587)),
                'username' => filled($values['mail_username'] ?? null) ? $values['mail_username'] : null,
                'password' => $password,
                'encryption' => filled($values['mail_encryption'] ?? null) ? $values['mail_encryption'] : null,
            ])]);
        }

        config([
            'mail.from.address' => filled($values['mail_from_address'] ?? null) ? $values['mail_from_address'] : config('mail.from.address'),
            'mail.from.name' => filled($values['mail_from_name'] ?? null) ? $values['mail_from_name'] : config('mail.from.name'),
        ]);
    }
}
