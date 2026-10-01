<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\HasApiTokens;

class Rider extends Authenticatable
{
    use HasFactory, HasUuids, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'license_number',
        'vehicle_type',
        'vehicle_number',
        'status',
        'latitude',
        'longitude',
        'is_active',
        'profile_picture',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];


    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    /**
     * Get the orders for the rider.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Check if rider is available
     */
    public function isAvailable(): bool
    {
        return $this->status === 'available' && $this->is_active;
    }

    public function getRoleAttribute()
    {
        return 'rider';
    }
}
