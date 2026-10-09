<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\Organisation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BulkInvitationService
{
    public function import(User $user, UploadedFile $file): array
    {
        Gate::forUser($user)->authorize('create', [Invitation::class, Organisation::findOrFail($user->organisation_id)]);
        Validator::make(['file' => $file], ['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']])->validate();
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'The CSV file could not be read.']);
        }
        $roles = Role::whereIn('slug', ['project-manager', 'procurement-officer', 'finance', 'user'])->pluck('id', 'slug');
        $rows = [];
        $errors = [];
        $seen = [];
        try {
            $headers = fgetcsv($handle);
            if (is_array($headers)) {
                $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
                $headers = array_map(fn ($value) => strtolower(trim($value)), $headers);
            }
            if ($headers !== ['email', 'role']) {
                throw ValidationException::withMessages(['file' => 'Use the template columns: email,role.']);
            }
            $line = 1;
            while (($values = fgetcsv($handle)) !== false) {
                $line++;
                if ($values === [null] || implode('', $values) === '') {
                    continue;
                }
                if (count($rows) >= 200) {
                    throw ValidationException::withMessages(['file' => 'Upload no more than 200 invitations at a time.']);
                }
                $email = mb_strtolower(trim($values[0] ?? ''));
                $role = strtolower(trim($values[1] ?? 'user')) ?: 'user';
                $validator = Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']]);
                if (count($values) !== 2 || $validator->fails() || ! isset($roles[$role]) || isset($seen[$email])) {
                    $errors[] = "Row {$line}: check the email, allowed role and duplicate email addresses.";
                }
                $seen[$email] = true;
                $rows[] = ['email' => $email, 'role' => $role, 'role_id' => $roles[$role] ?? null];
            }
        } finally {
            fclose($handle);
        }
        if ($rows === [] || $errors !== []) {
            throw ValidationException::withMessages(['file' => $errors ?: ['The template has no invitation rows.']]);
        }
        $results = [];
        foreach ($rows as $row) {
            $member = User::where('organisation_id', $user->organisation_id)
                ->whereRaw('LOWER(email) = ?', [$row['email']])->exists();
            if ($member) {
                $results[] = ['email' => $row['email'], 'role' => $row['role'], 'status' => 'Already a member', 'code' => '', 'email_sent' => false];
                continue;
            }
            $pending = Invitation::where('organisation_id', $user->organisation_id)
                ->whereRaw('LOWER(email) = ?', [$row['email']])->whereNull('accepted_at')->whereNull('consumed_at')
                ->whereNull('revoked_at')->where('expires_at', '>', now())->exists();
            if ($pending) {
                $results[] = ['email' => $row['email'], 'role' => $row['role'], 'status' => 'Already invited', 'code' => '', 'email_sent' => false];
                continue;
            }
            [, $code, $sent] = app(InvitationService::class)->create($user, ['email' => $row['email'], 'role_id' => $row['role_id']]);
            $results[] = ['email' => $row['email'], 'role' => $row['role'], 'status' => $sent ? 'Email sent' : 'Created — email not sent', 'code' => $code, 'email_sent' => $sent];
        }

        return $results;
    }
}
