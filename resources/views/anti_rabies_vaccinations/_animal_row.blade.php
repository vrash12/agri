@php
  $row = $row ?? [];
  $prefix = 'animals.'.$index.'.';
  $fields = [
    'pet_type' => ['Animal species', 'species', true],
    'animal_count' => ['Number served', 'number', true],
    'pet_name' => ['Animal name or group ID', 'text', false, 120],
    'service_type' => ['Service', 'service', true],
    'service_name' => ['Product, medicine or treatment', 'text', true, 150],
    'vaccination_date' => ['Service date', 'date', true],
  ];
  $optional = [
    'pet_breed' => ['Breed / strain', 'text', false, 120],
    'pet_color' => ['Color / markings', 'text', false, 80],
    'dosage' => ['Dosage / amount', 'text', false, 120],
    'administration_route' => ['Administration route', 'route', false],
    'administered_by' => ['Administered by', 'text', false, 120],
    'diagnosis' => ['Diagnosis or reason', 'text', false, 255],
    'next_service_date' => ['Next service / follow-up', 'date', false],
    'treatment_notes' => ['Service notes', 'textarea', false, 3000],
  ];
@endphp
<fieldset class="animal-batch-row" data-animal-row>
  <legend>Animal or group <span data-row-number>{{ is_numeric($index) ? $index + 1 : '' }}</span></legend>
  <div class="animal-row-head"><p>Use one row per species. Separate animals when their details or treatment differ.</p><button type="button" class="module-button" data-remove-animal hidden>Remove this row</button></div>
  <div class="module-form-grid">
    @foreach($fields as $field => $settings)
      @include('anti_rabies_vaccinations._animal_field', ['settings' => $settings])
    @endforeach
  </div>
  <details class="module-more" @if(collect(array_keys($optional))->contains(fn ($key) => filled($row[$key] ?? null) || $errors->has($prefix.$key))) open @endif>
    <summary>Identification, dosage and follow-up <span class="module-hint">Optional</span></summary>
    <div class="module-more-content"><div class="module-form-grid">
      @foreach($optional as $field => $settings)
        @include('anti_rabies_vaccinations._animal_field', ['settings' => $settings])
      @endforeach
    </div></div>
  </details>
</fieldset>
