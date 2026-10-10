@props(['compact' => false])

<img
    {{ $attributes->class(['agrilgu-logo', 'agrilgu-logo--mark' => $compact]) }}
    src="{{ asset($compact ? 'images/branding/agrilgu-mark-v1.png' : 'images/branding/agrilgu-wordmark-v1.png') }}"
    alt="AgriLGU"
    width="{{ $compact ? 1254 : 2025 }}"
    height="{{ $compact ? 1254 : 777 }}"
    decoding="async"
>
