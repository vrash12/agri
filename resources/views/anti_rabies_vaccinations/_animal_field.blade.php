@php
  [$label, $type, $required] = $settings;
  $id = 'animal_'.$index.'_'.$field;
  $name = 'animals['.$index.']['.$field.']';
  $inputValue = $row[$field] ?? ($field === 'animal_count' ? 1 : ($field === 'service_type' ? ($defaultServiceType ?? 'vaccination') : ($field === 'vaccination_date' ? $today : '')));
  $choices = match ($type) {
    'species' => $animalTypeOptions ?? [],
    'service' => $serviceTypeOptions ?? [],
    'route' => array_combine(['Oral','Injectable - intramuscular','Injectable - subcutaneous','Topical','In drinking water','Mixed with feed','Spray / dip','Other'], ['Oral','Injectable - intramuscular','Injectable - subcutaneous','Topical','In drinking water','Mixed with feed','Spray / dip','Other']),
    default => [],
  };
  $suggestions = match ($field) {
    'service_name' => $serviceSuggestions[$row['service_type'] ?? ($defaultServiceType ?? 'vaccination')] ?? [],
    'pet_breed' => $breedsByType[$row['pet_type'] ?? ''] ?? [],
    default => null,
  };
@endphp
<div class="module-form-field {{ $type === 'textarea' ? 'module-form-field-full' : '' }}">
  <label for="{{ $id }}">{{ $label }} @if($required)<span class="module-required">*</span>@endif</label>
  @if($choices)
    <select class="module-input {{ $errors->has($prefix.$field) ? 'is-invalid' : '' }}" id="{{ $id }}" name="{{ $name }}" data-field="{{ $field }}" @required($required) aria-describedby="{{ $id }}_error" aria-invalid="{{ $errors->has($prefix.$field) ? 'true' : 'false' }}">
      <option value="">Select {{ strtolower($label) }}</option>
      @foreach($choices as $key => $text)<option value="{{ $key }}" @selected((string) $inputValue === (string) $key)>{{ $text }}</option>@endforeach
    </select>
  @elseif($type === 'textarea')
    <textarea class="module-input {{ $errors->has($prefix.$field) ? 'is-invalid' : '' }}" id="{{ $id }}" name="{{ $name }}" data-field="{{ $field }}" rows="3" maxlength="{{ $settings[3] }}" aria-describedby="{{ $id }}_error" aria-invalid="{{ $errors->has($prefix.$field) ? 'true' : 'false' }}">{{ $inputValue }}</textarea>
  @else
    <input class="module-input {{ $errors->has($prefix.$field) ? 'is-invalid' : '' }}" id="{{ $id }}" name="{{ $name }}" data-field="{{ $field }}" type="{{ $type }}" value="{{ $inputValue }}" @required($required) @if($type === 'number') min="1" max="1000000" step="1" @endif @if($type === 'date' && $field === 'vaccination_date') max="{{ $today }}" @endif @if(isset($settings[3])) maxlength="{{ $settings[3] }}" @endif aria-describedby="{{ $id }}_error" aria-invalid="{{ $errors->has($prefix.$field) ? 'true' : 'false' }}" @if($suggestions !== null) list="{{ $id }}_suggestions" @endif @if($field === 'service_name') placeholder="Enter the actual product or treatment for this row" @endif>
  @endif
  @if($suggestions !== null)<datalist id="{{ $id }}_suggestions" data-suggestions="{{ $field }}">@foreach($suggestions as $suggestion)<option value="{{ $suggestion }}"></option>@endforeach</datalist>@endif
  @if($field === 'animal_count')<p class="module-hint">For two dogs treated alike, select Dog and enter 2. Use separate rows for different details.</p>@endif
  <span class="animal-batch-error" id="{{ $id }}_error">{{ $errors->first($prefix.$field) }}</span>
</div>
