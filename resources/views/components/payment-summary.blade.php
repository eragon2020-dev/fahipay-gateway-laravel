@props([
    'payment' => null,
    'transactionId' => '',
    'amount' => 0,
    'status' => 'pending',
    'showTimestamp' => true,
])

@php
    if ($payment) {
        $transactionId = $payment->transaction_id;
        $amount = $payment->amount;
        $status = $payment->status instanceof \Fahipay\Gateway\Enums\PaymentStatus 
            ? $payment->status->value 
            : $payment->status;
    }
@endphp

<div class="card">
    <div class="card-body">
        <h5 class="card-title">Payment Details</h5>
        
        <table class="table table-borderless">
            <tr>
                <td><strong>Transaction ID</strong></td>
                <td>{{ $transactionId }}</td>
            </tr>
            <tr>
                <td><strong>Amount</strong></td>
                <td>MVR {{ number_format($amount, 2) }}</td>
            </tr>
            <tr>
                <td><strong>Status</strong></td>
                <td>
                    <x-fahipay::payment-status-badge :status="$status" />
                </td>
            </tr>
            @if($showTimestamp && $payment?->created_at)
            <tr>
                <td><strong>Date</strong></td>
                <td>{{ $payment->created_at->format('d M Y, h:i A') }}</td>
            </tr>
            @endif
            @if($payment?->approval_code)
            <tr>
                <td><strong>Approval Code</strong></td>
                <td>{{ $payment->approval_code }}</td>
            </tr>
            @endif
        </table>
    </div>
</div>