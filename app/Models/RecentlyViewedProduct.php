<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecentlyViewedProduct extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'customer_id',
        'product_id',
        'viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }

    /**
     * Get the customer that viewed the product.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the product that was viewed.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Add or update a recently viewed product.
     */
    public static function addViewedProduct(string $customerId, string $productId): void
    {
        static::updateOrCreate(
            [
                'customer_id' => $customerId,
                'product_id' => $productId,
            ],
            [
                'viewed_at' => now(),
            ]
        );
    }

    /**
     * Get recently viewed products for a customer.
     */
    public static function getRecentlyViewed(string $customerId, int $limit = 10)
    {
        return static::with('product')
            ->where('customer_id', $customerId)
            ->orderBy('viewed_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Clean up old viewed products (keep only last 50 per customer).
     */
    public static function cleanupOldViews(): void
    {
        static::whereIn('customer_id', function ($query) {
            $query->select('customer_id')
                ->from('recently_viewed_products')
                ->groupBy('customer_id')
                ->havingRaw('COUNT(*) > 50');
        })
        ->whereNotIn('id', function ($query) {
            $query->select('id')
                ->from('recently_viewed_products')
                ->orderBy('viewed_at', 'desc')
                ->limit(50);
        })
        ->delete();
    }
}