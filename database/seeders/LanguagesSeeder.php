<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $languages = [
            [
                'code' => 'en',
                'name' => 'English',
                'native_name' => 'English',
                'direction' => 'ltr',
                'date_format' => 'Y-m-d',
                'time_format' => 'H:i',
                'number_format' => 'en_US',
                'decimal_separator' => '.',
                'thousands_separator' => ',',
                'currency_format' => ':symbol :value',
                'is_active' => true,
                'is_default' => true,
                'translation_completion' => 100,
            ],
            [
                'code' => 'lg',
                'name' => 'Luganda',
                'native_name' => 'Luganda',
                'direction' => 'ltr',
                'date_format' => 'd/m/Y',
                'time_format' => 'H:i',
                'number_format' => 'en_US',
                'decimal_separator' => '.',
                'thousands_separator' => ',',
                'currency_format' => ':symbol :value',
                'is_active' => true,
                'is_default' => false,
                'translation_completion' => 100,
            ],
        ];

        foreach ($languages as $language) {
            Language::updateOrCreate(['code' => $language['code']], $language);
        }
    }
}
