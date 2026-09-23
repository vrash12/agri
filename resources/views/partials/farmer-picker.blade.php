{{--
  The beneficiary picker, shared by the assistance and harvest forms.

  Renders only the farmer already chosen and searches the registry on the server for
  the rest. Before this, both forms serialised every farmer the account could see:
  724 KB on the assistance form and 299 KB on the harvest form for Ramos's 1,546
  beneficiaries, on every load.

  Parameters:
    $name                field name, e.g. 'farmer_id'
    $label               visible label
    $options             Collection from App\Support\FarmerPicker::initialOptions()
    $selected            currently selected farmer id, or null
    $required            whether a farmer must be chosen          (default false)
    $withProfile         ask the endpoint for the preview fields  (default false)
    $municipalitySelect  id of a municipality select that narrows the search, or null
    $browseUrl           link that re-renders this form with the whole registry
    $browsingAll         whether that link is already in effect
    $hint                extra guidance appended to the picker's own status line
--}}

@php
    $fieldId = $name;
    $required = $required ?? false;
    $withProfile = $withProfile ?? false;
    $municipalitySelect = $municipalitySelect ?? null;
    $browseUrl = $browseUrl ?? null;
    $browsingAll = $browsingAll ?? false;
    $hint = $hint ?? null;
    $selected = $selected === null || $selected === '' ? null : (string) $selected;
@endphp

<div class="module-form-field module-form-field-full farmer-picker">
    <label for="{{ $fieldId }}">{{ $label }}@if($required) <span class="module-required">*</span>@endif</label>

    <select
        class="module-input"
        id="{{ $fieldId }}"
        name="{{ $name }}"
        @if($required) required @endif
        data-farmer-picker
        data-endpoint="{{ route('farmers.picker') }}"
        @if($withProfile) data-profile="1" @endif
        @if($municipalitySelect) data-municipality-select="{{ $municipalitySelect }}" @endif
        data-placeholder="Type an AgriGOV ID, name, FFRS or RSBSA number"
        aria-describedby="{{ $fieldId }}_picker_help {{ $fieldId }}_error"
    >
        @unless($required)
            <option value="">{{ $emptyLabel ?? 'No individual farmer' }}</option>
        @endunless

        @foreach($options as $option)
            @php
                $attributes = collect($option['dataset'])
                    ->map(fn ($datum, $key) => 'data-'.\Illuminate\Support\Str::kebab($key).'="'.e($datum).'"')
                    ->implode(' ');
            @endphp
            <option value="{{ $option['value'] }}" {!! $attributes !!} @selected($selected === (string) $option['value'])>{{ $option['label'] }}</option>
        @endforeach
    </select>

    <div class="module-hint" id="{{ $fieldId }}_picker_help">
        @if($browsingAll)
            Showing the whole registry.
        @else
            Type at least {{ \App\Support\FarmerPicker::MINIMUM_TERM }} characters of an AgriGOV ID, name, FFRS or RSBSA number.
        @endif
    </div>

    @if($hint)
        <div class="module-hint">{{ $hint }}</div>
    @endif

    @if($browseUrl && ! $browsingAll)
        {{-- The escape hatch. For an operator who would rather scroll than type, and
             for a browser that is not running the picker. It costs a page load and
             the full list, which is the point of it not being the default. --}}
        <div class="module-hint">
            <a href="{{ $browseUrl }}">Browse the whole registry instead</a>
        </div>
    @endif

    @error($name)
        <span class="module-hint module-required" id="{{ $fieldId }}_error">{{ $message }}</span>
    @enderror
</div>

@once
    @push('scripts')
        <script src="{{ asset('js/farmer-picker.js') }}" defer></script>
    @endpush
@endonce
