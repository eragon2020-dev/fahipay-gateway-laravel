<?php

namespace Fahipay\Gateway\Actions;

use Fahipay\Gateway\Data\TransactionData;
use Fahipay\Gateway\Enums\PaymentStatus;
use Fahipay\Gateway\Events\PaymentCompletedEvent;
use Fahipay\Gateway\Events\PaymentFailedEvent;
use Fahipay\Gateway\Events\PaymentCancelledEvent;
use Fahipay\Gateway\Exceptions\FahipayException;
use Fahipay\Gateway\Facades\FahipayGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProcessCallbackAction
{
    public function execute(Request $request): TransactionData
    {
        if (!FahipayGateway::validateCallback($request)) {
            Log::warning('FahiPay: Invalid callback signature', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            throw new FahipayException('Invalid signature');
        }

        $transactionId = $request->get('ShoppingCartID');
        $success = $request->get('Success') === 'true';
        $approvalCode = $request->get('ApprovalCode');

        $transaction = new TransactionData(
            transactionId: $transactionId,
            amount: 0,
            status: $success ? PaymentStatus::COMPLETED : PaymentStatus::FAILED,
            approvalCode: $approvalCode,
            rawResponse: $request->all()
        );

        if ($success) {
            event(new PaymentCompletedEvent($transactionId, $approvalCode));
            Log::info("FahiPay: Payment completed", ['transaction_id' => $transactionId]);
        } else {
            $errorMessage = $request->get('Message', 'Payment failed');
            $transaction->errorMessage = $errorMessage;
            event(new PaymentFailedEvent($transactionId, $errorMessage));
            Log::warning("FahiPay: Payment failed", [
                'transaction_id' => $transactionId,
                'reason' => $errorMessage
            ]);
        }

        return $transaction;
    }

    public function handleCancellation(Request $request): TransactionData
    {
        $transactionId = $request->get('ShoppingCartID');
        
        event(new PaymentCancelledEvent($transactionId));
        Log::info("FahiPay: Payment cancelled", ['transaction_id' => $transactionId]);

        return new TransactionData(
            transactionId: $transactionId,
            amount: 0,
            status: PaymentStatus::CANCELLED,
            rawResponse: $request->all()
        );
    }
}