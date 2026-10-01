<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ad extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'title',
        'description',
        'type',
        'media_url',
        'thumbnail_url',
        'position',
        'status',
        'priority',
        'clicks',
        'impressions',
        'starts_at',
        'ends_at',
        'discount_type',
        'discount_value',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'discount_value' => 'decimal:2',
            'priority' => 'integer',
            'clicks' => 'integer',
            'impressions' => 'integer',
        ];
    }

    /**
     * Get the products linked to this ad (optional, many-to-many).
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'ad_product');
    }

    /**
     * Scope for active ads.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            });
    }

    /**
     * Scope for ads by position.
     */
    public function scopeByPosition($query, string $position)
    {
        return $query->where('position', $position);
    }

    /**
     * Scope for image ads.
     */
    public function scopeImages($query)
    {
        return $query->where('type', 'image');
    }

    /**
     * Scope for video ads.
     */
    public function scopeVideos($query)
    {
        return $query->where('type', 'video');
    }

    /**
     * Check if ad is currently active.
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $now = now();
        
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }
        
        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Increment impressions.
     */
    public function incrementImpressions(): void
    {
        $this->increment('impressions');
    }

    /**
     * Increment clicks.
     */
    public function incrementClicks(): void
    {
        $this->increment('clicks');
    }

    /**
     * Get click-through rate.
     */
    public function getClickThroughRateAttribute(): float
    {
        if ($this->impressions === 0) {
            return 0;
        }

        return round(($this->clicks / $this->impressions) * 100, 2);
    }

    /**
     * Get ad status badge color.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'inactive' => 'secondary',
            'scheduled' => 'warning',
            default => 'secondary',
        };
    }
}