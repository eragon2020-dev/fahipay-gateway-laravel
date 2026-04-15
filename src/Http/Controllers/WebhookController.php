<?php

namespace Fahipay\Gateway\Http\Controllers;

use Illuminate\Routing\Controller;
use Fahipay\Gateway\FahipayGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        protected FahipayGateway $gateway
    ) {}

    public function handle(Request $request)
    {
        try {
            $transaction = $this->gateway->handleCallback($request);
            
            return response()->json([
                'status' => 'success',
                'transaction_id' => $transaction->transactionId,
            ]);
        } catch (\Exception $e) {
            Log::error('FahiPay webhook error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function callback(Request $request)
    {
        return $this->handle($request);
    }

    public function success(Request $request)
    {
        if (!$this->gateway->validateCallback($request)) {
            return view('fahipay::error', [
                'message' => 'Invalid signature',
            ]);
        }

        $success = $request->get('Success') === 'true';
        $transactionId = $request->get('ShoppingCartID');
        $approvalCode = $request->get('ApprovalCode');

        if ($success) {
            event(new \Fahipay\Gateway\Events\PaymentCompletedEvent($transactionId, $approvalCode));
            
            return view('fahipay::success', [
                'transactionId' => $transactionId,
                'approvalCode' => $approvalCode,
            ]);
        }

        return view('fahipay::error', [
            'transactionId' => $transactionId,
            'message' => 'Payment was not completed',
        ]);
    }

    public function cancel(Request $request)
    {
        if (!$this->gateway->validateCallback($request)) {
            return view('fahipay::error', [
                'message' => 'Invalid signature',
            ]);
        }

        $transactionId = $request->get('ShoppingCartID');
        
        event(new \Fahipay\Gateway\Events\PaymentCancelledEvent($transactionId));

        return view('fahipay::cancelled', [
            'transactionId' => $transactionId,
        ]);
    }

    public function error(Request $request)
    {
        if (!$this->gateway->validateCallback($request)) {
            return view('fahipay::error', [
                'message' => 'Invalid signature',
            ]);
        }

        $transactionId = $request->get('ShoppingCartID');
        $message = $request->get('Message', 'Payment failed');

        event(new \Fahipay\Gateway\Events\PaymentFailedEvent($transactionId, $message));

        return view('fahipay::error', [
            'transactionId' => $transactionId,
            'message' => $message,
        ]);
    }
}