<?php

namespace Fahipay\Gateway\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use Fahipay\Gateway\Actions\CreatePaymentAction;
use Fahipay\Gateway\Actions\VerifyPaymentAction;
use Fahipay\Gateway\Http\Requests\CreatePaymentRequest;
use Fahipay\Gateway\Http\Resources\PaymentResource;
use Fahipay\Gateway\Http\Resources\TransactionResource;
use Fahipay\Gateway\Models\FahipayPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(
        protected CreatePaymentAction $createPayment,
        protected VerifyPaymentAction $verifyPayment
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);
        
        $payments = FahipayPayment::query()
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->merchant_id, fn($q, $id) => $q->where('merchant_id', $id))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'data' => PaymentResource::collection($payments),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    public function store(CreatePaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        if (!FahipayPayment::where('transaction_id', $validated['transaction_id'])->exists()) {
            FahipayPayment::createPayment(
                $validated['transaction_id'],
                config('fahipay.merchant_id'),
                $validated['amount'],
                $validated['description'] ?? null,
                $validated['metadata'] ?? null
            );
        }

        $payment = $this->createPayment->execute($validated);

        return response()->json([
            'data' => [
                'transaction_id' => $payment->transactionId,
                'amount' => $payment->amount,
                'status' => $payment->status->value,
                'payment_url' => $payment->paymentUrl,
            ],
            'message' => 'Payment created successfully',
        ], 201);
    }

    public function show(Request $request, string $transactionId): JsonResponse
    {
        $request->validate([
            'transaction_id' => 'sometimes|string|max:100|regex:/^[A-Za-z0-9\-_]+$/',
        ]);
        
        $payment = FahipayPayment::where('transaction_id', $transactionId)->first();

        if (!$payment) {
            return response()->json([
                'error' => 'Payment not found',
            ], 404);
        }

        return response()->json([
            'data' => new PaymentResource($payment),
        ]);
    }

    public function verify(string $transactionId): JsonResponse
    {
        if (!preg_match('/^[A-Za-z0-9\-_]+$/', $transactionId)) {
            return response()->json([
                'error' => 'Invalid transaction ID format',
            ], 400);
        }
        
        try {
            $transaction = $this->verifyPayment->execute($transactionId);

            return response()->json([
                'data' => new TransactionResource($transaction),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    public function update(Request $request, string $transactionId): JsonResponse
    {
        $request->validate([
            'description' => 'sometimes|string|max:255',
            'metadata' => 'sometimes|array',
        ]);
        
        $payment = FahipayPayment::where('transaction_id', $transactionId)->first();

        if (!$payment) {
            return response()->json([
                'error' => 'Payment not found',
            ], 404);
        }

        $payment->update($request->only(['description', 'metadata']));

        return response()->json([
            'data' => new PaymentResource($payment),
            'message' => 'Payment updated successfully',
        ]);
    }

    public function destroy(string $transactionId): JsonResponse
    {
        $payment = FahipayPayment::where('transaction_id', $transactionId)->first();

        if (!$payment) {
            return response()->json([
                'error' => 'Payment not found',
            ], 404);
        }

        $payment->delete();

        return response()->json([
            'message' => 'Payment deleted successfully',
        ]);
    }
}