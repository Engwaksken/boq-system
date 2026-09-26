<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed an initial administrator only when explicit credentials are
     * supplied. Production must never create a predictable default account.
     */
    public function run(): void
    {
        $email = trim((string) env('ADMIN_SEED_EMAIL', ''));
        $password = (string) env('ADMIN_SEED_PASSWORD', '');
        $name = trim((string) env('ADMIN_SEED_NAME', 'System Administrator'));

        if ($email === '' || $password === '') {
            $this->command?->warn(
                'AdminUserSeeder skipped: set ADMIN_SEED_EMAIL and ADMIN_SEED_PASSWORD to create the initial administrator.'
            );

            return;
        }

        if (strlen($password) < 12) {
            throw new \RuntimeException('ADMIN_SEED_PASSWORD must contain at least 12 characters.');
        }

        $organisation = Organisation::firstOrCreate(
            ['code' => 'DEFAULT'],
            [
                'name' => env('ADMIN_SEED_ORGANISATION', 'Default Organisation'),
                'email' => $email,
                'default_locale' => 'en',
                'default_currency' => 'UGX',
                'is_active' => true,
            ]
        );

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name !== '' ? $name : 'System Administrator',
                'password' => Hash::make($password),
                'locale' => 'en',
            ]
        );

        $admin->forceFill([
            'organisation_id' => $organisation->id,
            'is_active' => true,
        ])->save();

        $superAdminRole = Role::query()
            ->whereIn('slug', ['super-admin', 'super_admin'])
            ->first();

        if ($superAdminRole) {
            $admin->roles()->syncWithoutDetaching([$superAdminRole->id]);
        }
    }
}
