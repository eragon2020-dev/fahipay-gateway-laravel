<?php

namespace Fahipay\Gateway\Models;

use Illuminate\Database\Eloquent\Model;
use Fahipay\Gateway\Enums\PaymentStatus;

class FahipayPayment extends Model
{
    protected $table = 'fahipay_payments';

    protected $fillable = [
        'transaction_id',
        'merchant_id',
        'amount',
        'currency',
        'status',
        'method',
        'approval_code',
        'description',
        'metadata',
        'error_message',
        'initiated_at',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'initiated_at' => 'datetime',
        'completed_at' => 'datetime',
        'status' => PaymentStatus::class,
    ];

    public static function createPayment(
        string $transactionId,
        string $merchantId,
        float $amount,
        ?string $description = null,
        ?array $metadata = null
    ): self {
        return static::create([
            'transaction_id' => $transactionId,
            'merchant_id' => $merchantId,
            'amount' => $amount,
            'description' => $description,
            'metadata' => $metadata,
            'status' => PaymentStatus::PENDING,
            'initiated_at' => now(),
        ]);
    }

    public function markAsCompleted(?string $approvalCode = null): void
    {
        $this->update([
            'status' => PaymentStatus::COMPLETED,
            'approval_code' => $approvalCode,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(?string $errorMessage = null): void
    {
        $this->update([
            'status' => PaymentStatus::FAILED,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    public function markAsCancelled(): void
    {
        $this->update([
            'status' => PaymentStatus::CANCELLED,
            'completed_at' => now(),
        ]);
    }

    public function scopePending($query)
    {
        return $query->where('status', PaymentStatus::PENDING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', PaymentStatus::COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', PaymentStatus::FAILED);
    }

    public function isCompleted(): bool
    {
        return $this->status === PaymentStatus::COMPLETED;
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::PENDING;
    }

    public function isFailed(): bool
    {
        return in_array($this->status, [PaymentStatus::FAILED, PaymentStatus::CANCELLED]);
    }
}