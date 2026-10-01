<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Branch::create([
            'name' => 'Main Branch',
            'address' => '123 Main Street, City Center',
            'phone' => '+1234567890',
            'email' => 'main@example.com',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'is_active' => true,
        ]);

        \App\Models\Branch::create([
            'name' => 'North Branch',
            'address' => '456 North Avenue, North District',
            'phone' => '+1234567891',
            'email' => 'north@example.com',
            'latitude' => 40.7589,
            'longitude' => -73.9851,
            'is_active' => true,
        ]);
    }
}
