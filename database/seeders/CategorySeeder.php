<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Category::create([
            'name' => 'Stationery',
            'description' => 'Office and school stationery items',
            'is_active' => true,
        ]);

        \App\Models\Category::create([
            'name' => 'Art Supplies',
            'description' => 'Art and craft materials',
            'is_active' => true,
        ]);

        \App\Models\Category::create([
            'name' => 'Books',
            'description' => 'Educational and reference books',
            'is_active' => true,
        ]);
    }
}
