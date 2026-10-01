<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'order_id',
        'invoice_number',
        'paystack_reference',
        'paystack_transaction_id',
        'amount',
        'currency',
        'status',
        'payment_method',
        'payment_channel',
        'customer_email',
        'customer_phone',
        'customer_address',
        'paystack_response',
        'paid_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paystack_response' => 'array',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Get the order that owns the invoice.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the customer through the order relationship.
     */
    public function customer()
    {
        return $this->order->customer;
    }

    /**
     * Generate a unique invoice number.
     */
    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $year = date('Y');
        $month = date('m');
        
        $lastInvoice = static::where('invoice_number', 'like', "{$prefix}-{$year}{$month}%")
            ->orderBy('invoice_number', 'desc')
            ->first();
        
        if ($lastInvoice) {
            $lastNumber = (int) substr($lastInvoice->invoice_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return sprintf('%s-%s%s-%04d', $prefix, $year, $month, $newNumber);
    }

    /**
     * Check if invoice is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if invoice is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Mark invoice as paid.
     */
    public function markAsPaid(array $paystackData = []): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
            'paystack_response' => $paystackData,
            'paystack_transaction_id' => $paystackData['data']['id'] ?? null,
            'payment_method' => $paystackData['data']['payment_method'] ?? null,
            'payment_channel' => $paystackData['data']['channel'] ?? null,
        ]);
    }

    /**
     * Mark invoice as failed.
     */
    public function markAsFailed(array $paystackData = []): void
    {
        $this->update([
            'status' => 'failed',
            'paystack_response' => $paystackData,
        ]);
    }

    /**
     * Get invoice status badge color.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'paid' => 'success',
            'pending' => 'warning',
            'failed' => 'danger',
            'cancelled' => 'secondary',
            default => 'secondary',
        };
    }
}