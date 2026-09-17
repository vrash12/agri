@props([
    'name',
    'label',
    'required' => false,
    'hint' => null,
    'full' => false,
    'id' => null,
])

{{--
  One field of an operations form: the wrapper, label, required marker, hint and
  validation message. The control itself is the slot, so a text input, a select
  with option groups, or a textarea all keep their own markup and attributes.

  This existed as the same handwritten block 95 times across 10 forms, which is how
  the error element ended up as a <div> in some fields and a <span> in others, and
  how several fields lost the aria-describedby that ties the message to the input.

  Element ids follow a fixed convention so the control in the slot can point at
  them: "<name>_hint" and "<name>_error", or the same from an explicit :id. An
  aria-describedby naming an id that is not on the page is ignored by browsers, so
  a control may reference its error id whether or not the field is currently
  invalid. See DESIGN_SYSTEM.md section 13.
--}}

@php
    $fieldId = $id ?? $name;
    $hintId = $fieldId.'_hint';
    $errorId = $fieldId.'_error';
@endphp

<div {{ $attributes->class(['module-form-field', 'module-form-field-full' => $full]) }}>
    <label for="{{ $fieldId }}">{{ $label }}@if($required) <span class="module-required">*</span>@endif</label>
    {{ $slot }}
    @if($hint)
        <div class="module-hint" id="{{ $hintId }}">{{ $hint }}</div>
    @endif
    @error($name)
        <span class="module-hint module-required" id="{{ $errorId }}">{{ $message }}</span>
    @enderror
</div>
