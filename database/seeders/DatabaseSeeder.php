<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            LanguagesSeeder::class,
            PlansAndFeaturesSeeder::class,
            ProductVersionsSeeder::class,
            PaymentGatewaysSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
