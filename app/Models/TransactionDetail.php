<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionDetail extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'order_id',
        'invoice_id',
        'transaction_reference',
        'paystack_reference',
        'type',
        'status',
        'amount',
        'fee',
        'currency',
        'payment_method',
        'payment_channel',
        'gateway_response',
        'gateway_data',
        'description',
        'initiated_by',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'gateway_data' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Get the order that owns the transaction detail.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the invoice associated with the transaction detail.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Generate a unique transaction reference.
     */
    public static function generateTransactionReference(): string
    {
        $prefix = 'TXN';
        $year = date('Y');
        $month = date('m');
        
        $lastTransaction = static::where('transaction_reference', 'like', "{$prefix}-{$year}{$month}%")
            ->orderBy('transaction_reference', 'desc')
            ->first();
        
        if ($lastTransaction) {
            $lastNumber = (int) substr($lastTransaction->transaction_reference, -6);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return sprintf('%s-%s%s-%06d', $prefix, $year, $month, $newNumber);
    }

    /**
     * Check if transaction is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if transaction is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if transaction failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Mark transaction as completed.
     */
    public function markAsCompleted(array $gatewayData = []): void
    {
        $this->update([
            'status' => 'completed',
            'processed_at' => now(),
            'gateway_data' => $gatewayData,
        ]);
    }

    /**
     * Mark transaction as failed.
     */
    public function markAsFailed(string $reason, array $gatewayData = []): void
    {
        $this->update([
            'status' => 'failed',
            'gateway_response' => $reason,
            'gateway_data' => $gatewayData,
            'processed_at' => now(),
        ]);
    }

    /**
     * Get transaction status badge color.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'success',
            'pending' => 'warning',
            'failed' => 'danger',
            'cancelled' => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * Get transaction type badge color.
     */
    public function getTypeColorAttribute(): string
    {
        return match ($this->type) {
            'payment' => 'primary',
            'refund' => 'warning',
            'chargeback' => 'danger',
            'adjustment' => 'info',
            default => 'secondary',
        };
    }

    /**
     * Scope for completed transactions.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for pending transactions.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for failed transactions.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for payments.
     */
    public function scopePayments($query)
    {
        return $query->where('type', 'payment');
    }

    /**
     * Scope for refunds.
     */
    public function scopeRefunds($query)
    {
        return $query->where('type', 'refund');
    }
}