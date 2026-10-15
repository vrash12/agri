@props(['compact' => false])

<img
    {{ $attributes->class(['agrilgu-logo', 'agrilgu-logo--mark' => $compact]) }}
    src="{{ asset($compact ? 'images/branding/eagrilgu-mark-v1.png' : 'images/branding/eagrilgu-wordmark-v1.svg') }}"
    alt="eAgriLGU"
    width="{{ $compact ? 1254 : 2025 }}"
    height="{{ $compact ? 1254 : 777 }}"
    decoding="async"
>
