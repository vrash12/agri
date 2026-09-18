@props(['compact' => false])

<img
    {{ $attributes->class(['agrigov-logo', 'agrigov-logo--mark' => $compact]) }}
    src="{{ asset($compact ? 'images/branding/agrigov-mark-v1.png' : 'images/branding/agrigov-wordmark-v2.png') }}"
    alt="AgriGOV"
    width="{{ $compact ? 1254 : 2025 }}"
    height="{{ $compact ? 1254 : 776 }}"
    decoding="async"
>
