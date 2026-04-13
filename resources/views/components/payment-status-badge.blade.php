@props([
    'status' => 'pending',
    'large' => false,
])

@php
    $statusClasses = [
        'pending' => 'bg-warning text-dark',
        'completed' => 'bg-success',
        'failed' => 'bg-danger',
        'cancelled' => 'bg-secondary',
        'unknown' => 'bg-dark',
    ];
    
    $statusLabels = [
        'pending' => 'Pending',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
        'unknown' => 'Unknown',
    ];
    
    $class = $statusClasses[$status] ?? 'bg-secondary';
    $label = $statusLabels[$status] ?? $status;
    $sizeClass = $large ? 'px-3 py-2' : 'px-2 py-1';
@endphp

<span class="badge {{ $class }} {{ $sizeClass }}">
    {{ $label }}
</span>