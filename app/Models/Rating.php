<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rating extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'rider_id',
        'customer_id',
        'order_id',
        'rating',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    /**
     * Get the rider that owns the rating.
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    /**
     * Get the customer that owns the rating.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the order that owns the rating.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the average rating for a rider.
     */
    public static function getAverageRatingForRider(string $riderId): float
    {
        return static::where('rider_id', $riderId)->avg('rating') ?? 0;
    }

    /**
     * Get the total number of ratings for a rider.
     */
    public static function getTotalRatingsForRider(string $riderId): int
    {
        return static::where('rider_id', $riderId)->count();
    }

    /**
     * Check if a customer has already rated a rider for a specific order.
     */
    public static function hasCustomerRatedRiderForOrder(string $customerId, string $riderId, string $orderId): bool
    {
        return static::where('customer_id', $customerId)
            ->where('rider_id', $riderId)
            ->where('order_id', $orderId)
            ->exists();
    }
}
