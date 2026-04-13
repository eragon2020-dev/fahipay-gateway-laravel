<?php

namespace Fahipay\Gateway\Jobs;

use Fahipay\Gateway\Models\FahipayPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExpirePendingPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?string $transactionId = null
    ) {}

    public function handle(): void
    {
        $hours = config('fahipay.payment.expire_hours', 24);
        $cutoff = now()->subHours($hours);

        $query = FahipayPayment::pending()
            ->where('created_at', '<', $cutoff);

        if ($this->transactionId) {
            $query->where('transaction_id', $this->transactionId);
        }

        $payments = $query->get();
        $count = $payments->count();

        foreach ($payments as $payment) {
            $payment->markAsFailed('Payment expired');
            Log::info("Pending payment expired", [
                'transaction_id' => $payment->transaction_id,
            ]);
        }

        Log::info("Expired pending payments", [
            'count' => $count,
        ]);
    }
}