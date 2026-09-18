@include('partials.form-feedback')
@include('partials.record-version')

@php
  $value = fn ($key, $default = null) => old($key, $record->{$key} ?? $default);
  $selectedFarmer = (int) $value('farmer_id', 0);
@endphp

<div class="module-panel">
  <div class="module-panel-head">
    <div>
      <h2>What was harvested</h2>
      <p>The commodity, how much of it, and the year it is reported in. Everything else is optional.</p>
    </div>
  </div>

  <div class="module-form-grid">
    <x-module.field name="commodity" label="Commodity" :required="true">
      <select class="module-input" id="commodity" name="commodity" required aria-describedby="commodity_error">
        <option value="">Choose a commodity</option>
        @foreach($commodityOptions as $key => $label)
          <option value="{{ $key }}" @selected((string) $value('commodity') === (string) $key)>{{ $label }}</option>
        @endforeach
      </select>
    </x-module.field>

    <x-module.field name="variety" label="Variety" hint="As the farmer or the sheet names it.">
      <input class="module-input" id="variety" type="text" name="variety" maxlength="120"
        value="{{ $value('variety') }}" aria-describedby="variety_hint variety_error">
    </x-module.field>

    <x-module.field name="quantity" label="Quantity harvested" :required="true">
      <input class="module-input" id="quantity" type="number" name="quantity" min="0" step="0.001" required
        value="{{ $value('quantity') }}" aria-describedby="quantity_error">
    </x-module.field>

    <x-module.field name="quantity_unit" label="Counted in" :required="true"
      hint="Units are never added together in reports, so record what was actually counted.">
      <select class="module-input" id="quantity_unit" name="quantity_unit" required
        aria-describedby="quantity_unit_hint quantity_unit_error">
        @foreach($unitOptions as $key => $label)
          <option value="{{ $key }}" @selected((string) $value('quantity_unit', 'kg') === (string) $key)>{{ $label }}</option>
        @endforeach
      </select>
    </x-module.field>
  </div>
</div>

<div class="module-panel">
  <div class="module-panel-head">
    <div>
      <h2>When it is reported</h2>
      <p>The year is what the production report groups by. The season and the exact date are optional.</p>
    </div>
  </div>

  <div class="module-form-grid">
    <x-module.field name="harvest_year" label="Harvest year" :required="true">
      <input class="module-input" id="harvest_year" type="number" name="harvest_year" min="1990" max="{{ $currentYear + 1 }}" step="1"
        required value="{{ $value('harvest_year', $currentYear) }}" aria-describedby="harvest_year_error">
    </x-module.field>

    <x-module.field name="season" label="Season">
      <select class="module-input" id="season" name="season" aria-describedby="season_error">
        <option value="">Not recorded</option>
        @foreach($seasonOptions as $key => $label)
          <option value="{{ $key }}" @selected((string) $value('season') === (string) $key)>{{ $label }}</option>
        @endforeach
      </select>
    </x-module.field>

    <x-module.field name="date_harvested" label="Date harvested"
      hint="Leave blank if only the season is known.">
      <input class="module-input" id="date_harvested" type="date" name="date_harvested"
        max="{{ now()->toDateString() }}"
        value="{{ $value('date_harvested') instanceof \Illuminate\Support\Carbon ? $value('date_harvested')->format('Y-m-d') : $value('date_harvested') }}"
        aria-describedby="date_harvested_hint date_harvested_error">
    </x-module.field>

    <x-module.field name="area_harvested_ha" label="Area harvested (ha)">
      <input class="module-input" id="area_harvested_ha" type="number" name="area_harvested_ha" min="0" step="0.0001"
        value="{{ $value('area_harvested_ha') }}" aria-describedby="area_harvested_ha_error">
    </x-module.field>
  </div>
</div>

<details class="module-more" @if($selectedFarmer || $errors->hasAny(['farmer_id', 'farm_plot_id', 'municipality_id', 'notes'])) open @endif>
  <summary>Who and where <span style="color:var(--module-muted);font-size:12px;font-weight:700">Optional</span></summary>
  <div class="module-more-content">
    <p class="module-hint">
      A harvest does not need a farmer. A municipality reporting its corn for a season has a
      real figure and no single farmer to attach it to, and that is worth recording.
    </p>

    <div class="module-form-grid">
      <x-module.field name="farmer_id" label="Farmer">
        <select class="module-input" id="farmer_id" name="farmer_id" aria-describedby="farmer_id_error">
          <option value="">No individual farmer</option>
          @foreach($farmers as $farmer)
            <option value="{{ $farmer->id }}" @selected($selectedFarmer === (int) $farmer->id)>
              {{ trim(collect([$farmer->last_name, $farmer->first_name, $farmer->middle_name, $farmer->ext_name])->filter()->implode(' ')) }}{{ $farmer->ffrs ? ' · '.$farmer->ffrs : '' }}
            </option>
          @endforeach
        </select>
      </x-module.field>

      <x-module.field name="farm_plot_id" label="Parcel"
        hint="Only the chosen farmer's mapped parcels. Save the farmer first to see theirs.">
        <select class="module-input" id="farm_plot_id" name="farm_plot_id" aria-describedby="farm_plot_id_hint farm_plot_id_error">
          <option value="">No specific parcel</option>
          @foreach($plots as $plot)
            <option value="{{ $plot->id }}" @selected((int) $value('farm_plot_id', 0) === (int) $plot->id)>
              {{ $plot->name ?: 'Parcel #'.$plot->id }}{{ $plot->area_ha ? ' · '.number_format((float) $plot->area_ha, 4).' ha' : '' }}
            </option>
          @endforeach
        </select>
      </x-module.field>

      @if($canChooseMunicipality)
        <x-module.field name="municipality_id" label="Municipality"
          hint="Used only when no farmer is chosen; otherwise the farmer's municipality owns the record.">
          <select class="module-input" id="municipality_id" name="municipality_id" aria-describedby="municipality_id_hint municipality_id_error">
            <option value="">Choose a municipality</option>
            @foreach($municipalities as $municipality)
              <option value="{{ $municipality->id }}" @selected((int) $value('municipality_id', 0) === (int) $municipality->id)>{{ $municipality->name }}</option>
            @endforeach
          </select>
        </x-module.field>
      @endif

      <x-module.field name="notes" label="Notes" :full="true">
        <textarea class="module-input" id="notes" name="notes" rows="3" maxlength="1000"
          aria-describedby="notes_error">{{ $value('notes') }}</textarea>
      </x-module.field>
    </div>
  </div>
</details>

<div class="module-actions">
  <a class="module-button" href="{{ route('harvest-records.index') }}">Cancel</a>
  <button class="module-button module-button-primary" type="submit">{{ $buttonText }}</button>
</div>
