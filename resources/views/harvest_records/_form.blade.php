@include('partials.form-feedback')
@include('partials.record-version')

@php
  $value = fn ($key, $default = null) => old($key, $record->{$key} ?? $default);
  $selectedFarmer = (int) $value('farmer_id', $selectedFarmerId ?? 0);
  $selectedCropSeason = (int) $value('parcel_crop_season_id', $defaultCropSeasonId ?? 0);
  $selectedAreaKey = (string) old('planted_area_key', $record?->planted_area_key ?? ($defaultAreaKey ?? ''));
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
          <option value="{{ $key }}" @selected((string) $value('commodity', $defaultCommodity ?? null) === (string) $key)>{{ $label }}</option>
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
        required value="{{ $value('harvest_year', $defaultHarvestYear ?? $currentYear) }}" aria-describedby="harvest_year_error">
    </x-module.field>

    <x-module.field name="season" label="Season">
      <select class="module-input" id="season" name="season" aria-describedby="season_error">
        <option value="">Not recorded</option>
        @foreach($seasonOptions as $key => $label)
          <option value="{{ $key }}" @selected((string) $value('season', $defaultHarvestSeason ?? null) === (string) $key)>{{ $label }}</option>
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

<details class="module-more" @if($selectedFarmer || $errors->hasAny(['farmer_id', 'farm_plot_id', 'parcel_crop_season_id', 'planted_area_key', 'municipality_id', 'notes'])) open @endif>
  <summary>Who and where <span style="color:var(--module-muted);font-size:12px;font-weight:700">Optional</span></summary>
  <div class="module-more-content">
    <p class="module-hint">
      A harvest does not need a farmer. A municipality reporting its corn for a season has a
      real figure and no single farmer to attach it to, and that is worth recording.
    </p>

    <div class="module-form-grid">
      @include('partials.farmer-picker', [
        'name' => 'farmer_id',
        'label' => 'Farmer',
        'options' => $farmerOptions,
        'selected' => $selectedFarmer ?: null,
        'municipalitySelect' => $canChooseMunicipality ? 'municipality_id' : null,
        'browseUrl' => $browseUrl,
        'browsingAll' => $browsingAll,
      ])

      <x-module.field name="farm_plot_id" label="Parcel"
        hint="Only the chosen farmer's mapped parcels. Save the farmer first to see theirs.">
        <select class="module-input" id="farm_plot_id" name="farm_plot_id" aria-describedby="farm_plot_id_hint farm_plot_id_error">
          <option value="">No specific parcel</option>
          @foreach($plots as $plot)
            <option value="{{ $plot->id }}" @selected((int) $value('farm_plot_id', $defaultPlotId ?? 0) === (int) $plot->id)>
              {{ $plot->name ?: 'Parcel #'.$plot->id }}{{ $plot->area_ha ? ' · '.number_format((float) $plot->area_ha, 4).' ha' : '' }}
            </option>
          @endforeach
        </select>
      </x-module.field>

      <x-module.field name="parcel_crop_season_id" label="Planted crop section"
        hint="Optional. Choose a saved section to show which planted area this harvest came from.">
        <select class="module-input" id="parcel_crop_season_id" name="parcel_crop_season_id"
          aria-describedby="parcel_crop_season_id_hint parcel_crop_season_id_error">
          <option value="" data-plot-id="">No specific planted section</option>
          @foreach($cropSections as $section)
            <option value="{{ $section['season_id'] }}" data-plot-id="{{ $section['plot_id'] }}"
              data-area-key="{{ $section['area_key'] }}"
              @selected($selectedCropSeason === (int) $section['season_id'] && $selectedAreaKey === $section['area_key'])>
              {{ $section['crop_label'] }} · {{ $section['year'] }} {{ strtoupper($section['season']) }}
              @if($section['name']) · {{ $section['name'] }}@endif
              @if($section['variety']) · {{ $section['variety'] }}@endif
            </option>
          @endforeach
        </select>
        <input type="hidden" id="planted_area_key" name="planted_area_key" value="{{ $selectedAreaKey }}">
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

@once
  @push('scripts')
    <script>
      (() => {
        const plot = document.getElementById('farm_plot_id');
        const section = document.getElementById('parcel_crop_season_id');
        const key = document.getElementById('planted_area_key');
        if (!plot || !section || !key) return;
        const options = Array.from(section.options);
        const sync = () => {
          const plotId = plot.value;
          let selected = false;
          options.forEach(option => {
            const visible = !option.dataset.plotId || option.dataset.plotId === plotId;
            option.hidden = !visible;
            option.disabled = !visible;
            if (!visible && option.selected) option.selected = false;
            if (visible && option.selected && option.dataset.areaKey) selected = true;
          });
          if (!selected) {
            section.value = '';
            key.value = '';
          } else {
            key.value = section.selectedOptions[0]?.dataset.areaKey || '';
          }
        };
        plot.addEventListener('change', sync);
        section.addEventListener('change', () => { key.value = section.selectedOptions[0]?.dataset.areaKey || ''; });
        sync();
      })();
    </script>
  @endpush
@endonce
