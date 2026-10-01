<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory, HasUuids;

    protected $with = ['variants'];

    /**
     * Flag to prevent infinite sync loops
     */
    public static bool $syncingToInventory = false;

    protected $fillable = [
        'name',
        'description',
        'short_description',
        'price',
        'cost_price',
        'sale_price',
        'discount_type',
        'discount_value',
        'stock_quantity',
        'min_stock_level',
        'sku',
        'barcode',
        'weight',
        'dimensions',
        'color',
        'size',
        'brand',
        'model',
        'warranty_period',
        'is_featured',
        'is_ad',
        'ad_discount_type',
        'ad_discount_value',
        'is_digital',
        'requires_shipping',
        'tax_rate',
        'image',
        'images',
        'tags',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'category_id',
        'branch_id',
        'is_active',
    ];

    protected $appends = ['price_after_ad_discount', 'ad_discount_amount'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'weight' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'is_featured' => 'boolean',
            'is_ad' => 'boolean',
            'ad_discount_value' => 'decimal:2',
            'is_digital' => 'boolean',
            'requires_shipping' => 'boolean',
            'is_active' => 'boolean',
            'images' => 'array',
            'tags' => 'array',
        ];
    }

    /**
     * Get the category that owns the product.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the branch that owns the product.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the order items for the product.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the ads that link to this product (many-to-many).
     */
    public function ads(): BelongsToMany
    {
        return $this->belongsToMany(Ad::class, 'ad_product');
    }

    /**
     * Get the inventory for the product.
     */
    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('created_at');
    }

    /**
     * Check if product is low in stock
     */
    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->min_stock_level;
    }

    /**
     * Check if product is out of stock
     */
    public function isOutOfStock(): bool
    {
        return $this->stock_quantity <= 0;
    }

    /**
     * Get the effective price (sale price, or price after direct discount, or regular price)
     */
    public function getEffectivePrice(): float
    {
        if ($this->sale_price !== null && (float) $this->sale_price >= 0) {
            return (float) $this->sale_price;
        }
        if ($this->discount_type && $this->discount_value !== null) {
            $price = (float) $this->price;
            $value = (float) $this->discount_value;
            if ($this->discount_type === 'percent') {
                $value = min(100, max(0, $value));
                return round($price * (1 - $value / 100), 2);
            }
            if ($this->discount_type === 'fixed') {
                return round(max(0, $price - $value), 2);
            }
        }
        return (float) $this->price;
    }

    /**
     * Get profit margin
     */
    public function getProfitMargin(): float
    {
        if (!$this->cost_price) {
            return 0;
        }
        
        return (($this->getEffectivePrice() - $this->cost_price) / $this->cost_price) * 100;
    }

    /**
     * Get total sales count
     */
    public function getTotalSales(): int
    {
        return $this->orderItems()->sum('quantity');
    }

    /**
     * Get total revenue from this product
     */
    public function getTotalRevenue(): float
    {
        return $this->orderItems()->sum('total_price');
    }

    /**
     * Get discount from an active ad linked to this product (when product has no own ad discount).
     * Returns ['type' => 'percent'|'fixed', 'value' => number] or null.
     */
    public function getLinkedAdDiscount(): ?array
    {
        $ads = $this->relationLoaded('ads') ? $this->ads : $this->ads()->get();

        foreach ($ads as $ad) {
            if ($ad->status !== 'active' || !$ad->discount_type || $ad->discount_value === null) {
                continue;
            }
            if ($ad->starts_at && $ad->starts_at->isFuture()) {
                continue;
            }
            if ($ad->ends_at && $ad->ends_at->isPast()) {
                continue;
            }
            return [
                'type' => $ad->discount_type,
                'value' => (float) $ad->discount_value,
            ];
        }

        return null;
    }

    /**
     * Get unit price after applying ad discount.
     * Base price: sale_price if set, else price with direct discount (discount_type/discount_value) if set, else price.
     * Then applies ad discount (product is_ad or linked Ad) if applicable — customer gets the best price.
     */
    public function getUnitPriceWithAdDiscount(?float $basePrice = null): float
    {
        $price = $basePrice ?? (float) $this->price;

        // Base price: sale_price overrides everything
        if ($basePrice === null && $this->sale_price !== null && (float) $this->sale_price >= 0) {
            $price = (float) $this->sale_price;
        }
        // Direct admin discount (not ad-related)
        elseif ($this->discount_type && $this->discount_value !== null) {
            $value = (float) $this->discount_value;
            if ($this->discount_type === 'percent') {
                $value = min(100, max(0, $value));
                $price = round($price * (1 - $value / 100), 2);
            } elseif ($this->discount_type === 'fixed') {
                $price = round(max(0, $price - $value), 2);
            }
        }

        // 1) Product's own ad discount (further reduction)
        if ($this->is_ad && $this->ad_discount_type && $this->ad_discount_value !== null) {
            $value = (float) $this->ad_discount_value;
            if ($this->ad_discount_type === 'percent') {
                $value = min(100, max(0, $value));
                $adPrice = round($price * (1 - $value / 100), 2);
            } else {
                $adPrice = round(max(0, $price - $value), 2);
            }
            $price = min($price, $adPrice);
        }

        // 2) Linked active ad discount (further reduction)
        $linked = $this->getLinkedAdDiscount();
        if ($linked) {
            $value = $linked['value'];
            if ($linked['type'] === 'percent') {
                $value = min(100, max(0, $value));
                $adPrice = round($price * (1 - $value / 100), 2);
            } else {
                $adPrice = round(max(0, $price - $value), 2);
            }
            $price = min($price, $adPrice);
        }

        return round($price, 2);
    }

    /**
     * Appended: price after product or linked-ad discount (for API display).
     */
    public function getPriceAfterAdDiscountAttribute(): float
    {
        return $this->getUnitPriceWithAdDiscount();
    }

    /**
     * Appended: discount amount per unit (for API display).
     */
    public function getAdDiscountAmountAttribute(): float
    {
        return round((float) $this->price - $this->getUnitPriceWithAdDiscount(), 2);
    }

    /**
     * Get the ad discount amount per unit (0 if no discount).
     */
    public function getAdDiscountPerUnit(): float
    {
        $original = (float) $this->price;
        $discounted = $this->getUnitPriceWithAdDiscount();
        return round($original - $discounted, 2);
    }

    /**
     * Scope for ad products
     */
    public function scopeAd($query)
    {
        return $query->where('is_ad', true);
    }

    /**
     * Scope for featured products
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope for digital products
     */
    public function scopeDigital($query)
    {
        return $query->where('is_digital', true);
    }

    /**
     * Scope for low stock products
     */
    public function scopeLowStock($query)
    {
        return $query->whereRaw('stock_quantity <= min_stock_level');
    }

    /**
     * Scope for out of stock products
     */
    public function scopeOutOfStock($query)
    {
        return $query->where('stock_quantity', '<=', 0);
    }

    /**
     * Get the primary inventory record (first active one)
     */
    public function getPrimaryInventory()
    {
        return $this->inventory()->where('is_active', true)->first();
    }

    /**
     * Boot method to register model events
     */
    protected static function boot()
    {
        parent::boot();

        // Sync to Inventory when Product stock_quantity changes
        static::saved(function ($product) {
            if (!static::$syncingToInventory && $product->wasChanged('stock_quantity')) {
                static::$syncingToInventory = true;
                
                $inventory = $product->getPrimaryInventory();
                if ($inventory && $inventory->quantity_in_stock != $product->stock_quantity) {
                    // Prevent Inventory from syncing back to Product
                    \App\Models\Inventory::$syncingToProduct = true;
                    
                    $inventory->quantity_in_stock = $product->stock_quantity;
                    $inventory->save();
                    
                    \App\Models\Inventory::$syncingToProduct = false;
                }
                
                static::$syncingToInventory = false;
            }
        });
    }
}
