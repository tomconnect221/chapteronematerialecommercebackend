<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Subadmin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subadmin>
 */
class SubadminFactory extends Factory
{
    protected $model = Subadmin::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'branch_id' => Branch::factory(),
            'is_active' => true,
            'permissions' => Subadmin::DEFAULT_PERMISSIONS,
            'remember_token' => Str::random(10),
        ];
    }
}