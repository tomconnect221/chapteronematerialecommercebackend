<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SettingsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'site_name',
                'value' => 'E-Commerce',
            ],
            [
                'key' => 'vat',
                'value' => '10', // percentage
            ],
            [
                'key' => 'delivery_fee',
                'value' => '50', // flat rate
            ],
            [
                'key' => 'currency',
                'value' => 'NGN',
            ],
            [
                'key' => 'support_email',
                'value' => 'support@ecommerce.com',
            ],
        ];

        foreach ($settings as $setting) {
            \App\Models\Setting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }
    }
}
