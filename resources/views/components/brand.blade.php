@props(['compact' => false])

<img
    {{ $attributes->class(['agrilgu-logo', 'agrilgu-logo--mark' => $compact]) }}
    src="{{ asset($compact ? 'images/branding/eagrilgu-system-mark.png' : 'images/branding/eagrilgu-wordmark-v4.png') }}"
    alt="eAgriLGU"
    width="{{ $compact ? 236 : 350 }}"
    height="{{ $compact ? 236 : 104 }}"
    decoding="async"
>
