@props(['compact' => false])

<img
    {{ $attributes->class(['agrilgu-logo', 'agrilgu-logo--mark' => $compact]) }}
    src="{{ asset($compact ? 'images/branding/eagrilgu-system-mark.png' : 'images/branding/agrilgu-wordmark-v2.png') }}"
    alt="AgriLGU"
    width="{{ $compact ? 236 : 306 }}"
    height="{{ $compact ? 236 : 104 }}"
    decoding="async"
>
