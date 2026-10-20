@props(['compact' => false])

<img
    {{ $attributes->class(['agrilgu-logo', 'agrilgu-logo--mark' => $compact]) }}
    src="{{ asset($compact ? 'images/branding/eagrilgu-system-mark.png' : 'images/branding/eagrilgu-wordmark-v3.png') }}"
    alt="eAgriLGU"
    width="{{ $compact ? 236 : 365 }}"
    height="{{ $compact ? 236 : 104 }}"
    decoding="async"
>
