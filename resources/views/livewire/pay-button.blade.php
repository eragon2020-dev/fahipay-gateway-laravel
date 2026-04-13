<div>
    @if($paymentUrl)
        <script>
            window.location.href = '{{ $paymentUrl }}';
        </script>
    @endif
    
    <button 
        type="button" 
        class="btn btn-primary"
        wire:click="initiatePayment"
        wire:loading.attr="disabled"
        {{ $attributes->except(['transactionId', 'amount', 'description', 'redirectUrl']) }}
    >
        <span wire:loading.remove wire:target="initiatePayment">
            {{ $slot ?: 'Pay Now' }}
        </span>
        <span wire:loading wire:target="initiatePayment">
            Processing...
        </span>
        @if($amount > 0)
            - MVR {{ number_format($amount, 2) }}
        @endif
    </button>

    @if($errorMessage)
        <div class="text-danger mt-2">
            {{ $errorMessage }}
        </div>
    @endif
</div>