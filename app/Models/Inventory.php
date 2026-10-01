<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'inventory';

    /**
     * Flag to prevent infinite sync loops
     */
    public static bool $syncingToProduct = false;

    protected $fillable = [
        'product_id',
        'quantity_in_stock',
        'quantity_reserved',
        'reorder_level',
        'max_stock_level',
        'cost_price',
        'selling_price',
        'supplier',
        'last_restocked',
        'expiry_date',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'quantity_in_stock' => 'integer',
            'quantity_reserved' => 'integer',
            'reorder_level' => 'integer',
            'max_stock_level' => 'integer',
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'last_restocked' => 'date',
            'expiry_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the product that owns the inventory.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the available quantity (calculated field).
     */
    public function getQuantityAvailableAttribute(): int
    {
        return $this->quantity_in_stock - $this->quantity_reserved;
    }

    /**
     * Check if stock is low.
     */
    public function isLowStock(): bool
    {
        return $this->quantity_available <= $this->reorder_level;
    }

    /**
     * Check if stock is out.
     */
    public function isOutOfStock(): bool
    {
        return $this->quantity_available <= 0;
    }

    /**
     * Reserve quantity for an order.
     */
    public function reserveQuantity(int $quantity): bool
    {
        if ($this->quantity_available >= $quantity) {
            $this->quantity_reserved += $quantity;
            return $this->save();
        }
        return false;
    }

    /**
     * Release reserved quantity.
     */
    public function releaseQuantity(int $quantity): bool
    {
        if ($this->quantity_reserved >= $quantity) {
            $this->quantity_reserved -= $quantity;
            return $this->save();
        }
        return false;
    }

    /**
     * Reduce stock after order completion.
     */
    public function reduceStock(int $quantity): bool
    {
        if ($this->quantity_in_stock >= $quantity) {
            $this->quantity_in_stock -= $quantity;
            return $this->save();
        }
        return false;
    }

    /**
     * Add stock (restock).
     */
    public function addStock(int $quantity, ?string $supplier = null): bool
    {
        $this->quantity_in_stock += $quantity;
        $this->last_restocked = now();
        
        if ($supplier) {
            $this->supplier = $supplier;
        }
        
        return $this->save();
    }

    /**
     * Boot method to register model events
     */
    protected static function boot()
    {
        parent::boot();

        // Sync to Product when Inventory quantity_in_stock changes
        static::saved(function ($inventory) {
            if (!static::$syncingToProduct && $inventory->wasChanged('quantity_in_stock')) {
                static::$syncingToProduct = true;
                
                $product = $inventory->product;
                if ($product && $product->stock_quantity != $inventory->quantity_in_stock) {
                    // Prevent Product from syncing back to Inventory
                    \App\Models\Product::$syncingToInventory = true;
                    
                    $product->stock_quantity = $inventory->quantity_in_stock;
                    $product->save();
                    
                    \App\Models\Product::$syncingToInventory = false;
                }
                
                static::$syncingToProduct = false;
            }
        });
    }
}