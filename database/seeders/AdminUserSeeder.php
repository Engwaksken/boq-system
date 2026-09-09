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
     * Run the database seeds.
     */
    public function run(): void
    {
        $organisation = Organisation::firstOrCreate(
            ['code' => 'DEFAULT'],
            [
                'name' => 'Default Organisation',
                'email' => 'admin@example.com',
                'default_locale' => 'en',
                'default_currency' => 'UGX',
                'is_active' => true,
            ]
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('password'),
                'locale' => 'en',
            ]
        );

        // organisation_id and is_active are intentionally not mass-assignable; set them explicitly.
        $admin->forceFill([
            'organisation_id' => $organisation->id,
            'is_active' => true,
        ])->save();

        $superAdminRole = Role::where('slug', 'super-admin')->first();
        if ($superAdminRole) {
            $admin->roles()->syncWithoutDetaching([$superAdminRole->id]);
        }
    }
}
