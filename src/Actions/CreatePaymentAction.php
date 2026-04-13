<?php

namespace Fahipay\Gateway\Actions;

use Fahipay\Gateway\Data\PaymentData;
use Fahipay\Gateway\Facades\FahipayGateway;
use Illuminate\Support\Str;

class CreatePaymentAction
{
    public function execute(array $data): PaymentData
    {
        $transactionId = $data['transaction_id'] ?? $this->generateTransactionId();
        $amount = (float) $data['amount'];
        $description = $data['description'] ?? null;
        $metadata = $data['metadata'] ?? [];
        $callbackUrl = $data['callback_url'] ?? null;
        $metadata['callback_url'] = $callbackUrl;

        if ($callbackUrl) {
            FahipayGateway::setReturnUrl($callbackUrl);
        }

        return FahipayGateway::createPayment(
            $transactionId,
            $amount,
            $description,
            $metadata
        );
    }

    protected function generateTransactionId(): string
    {
        $prefix = config('fahipay.payment.prefix', 'PAY');
        $length = config('fahipay.payment.unique_id_length', 12);
        
        return $prefix . '-' . Str::random($length);
    }
}