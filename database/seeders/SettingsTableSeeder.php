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
            // Mail Configuration
            [
                'key' => 'mail_mailer',
                'value' => 'smtp',
            ],
            [
                'key' => 'mail_host',
                'value' => 'smtp.mailtrap.io',
            ],
            [
                'key' => 'mail_port',
                'value' => '2525',
            ],
            [
                'key' => 'mail_username',
                'value' => '',
            ],
            [
                'key' => 'mail_password',
                'value' => '',
            ],
            [
                'key' => 'mail_encryption',
                'value' => 'tls',
            ],
            [
                'key' => 'mail_from_address',
                'value' => 'noreply@chapterone.com',
            ],
            [
                'key' => 'mail_from_name',
                'value' => 'Chapter One Stationery',
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
