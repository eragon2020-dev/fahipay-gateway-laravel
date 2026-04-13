@props([
    'amount' => 0,
    'transactionId' => '',
    'description' => '',
    'showAmount' => true,
    'color' => 'primary',
    'size' => 'md',
    'loadingText' => 'Processing...',
])

@php
    $sizes = [
        'sm' => 'btn-sm',
        'md' => '',
        'lg' => 'btn-lg',
    ];
    
    $colors = [
        'primary' => 'btn-primary',
        'success' => 'btn-success',
        'dark' => 'btn-dark',
    ];
    
    $sizeClass = $sizes[$size] ?? '';
    $colorClass = $colors[$color] ?? 'btn-primary';
@endphp

<form method="POST" action="{{ route('fahipay.payment.initiate') }}" style="display: inline;">
    @csrf
    <input type="hidden" name="amount" value="{{ $amount }}">
    <input type="hidden" name="transaction_id" value="{{ $transactionId }}">
    @if($description)
    <input type="hidden" name="description" value="{{ $description }}">
    @endif
    
    <button type="submit" 
            class="btn {{ $colorClass }} {{ $sizeClass }}"
            onclick="this.disabled=true; this.innerText='{{ $loadingText }}'; this.form.submit();"
            {{ $attributes->except(['amount', 'transactionId', 'description', 'showAmount', 'color', 'size', 'loadingText']) }}>
        {{ $slot }}
        @if($showAmount && $amount > 0)
            - MVR {{ number_format($amount, 2) }}
        @endif
    </button>
</form>