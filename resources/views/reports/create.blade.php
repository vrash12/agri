@extends('layouts.app')

@section('title', 'Generate Report')

@push('styles')
  @include('partials.operations-ui-styles')
  <style>
    [data-report-form] { display: grid; gap: 16px; }
    [data-report-form] .module-form-grid { padding: 20px; }
    [data-report-form] .module-panel > .module-hint { padding: 0 20px 16px; }
  </style>
@endpush

@section('content')
@php($defaultPeriod = \App\Support\LocalTime::now()->startOfMonth()->subMonth())
<div class="module-page">
  <header class="module-header">
    <div>
      <div class="module-eyebrow">Reporting</div>
      <h1>Generate a period report</h1>
      <p>Builds a summary of saved records for one period and saves it to the archive. Figures are counts and same-unit quantities only — no names or identifiers.</p>
    </div>
    <div class="module-actions">
      <a class="module-button" href="{{ route('reports.index') }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>Back to reports
      </a>
    </div>
  </header>

  @include('partials.form-feedback')

  <form method="POST" action="{{ route('reports.store') }}" data-report-form>
    @csrf
    <div class="module-panel">
      <div class="module-panel-head">
        <div>
          <h2>Period</h2>
          <p>A period that has not ended yet is marked as partial.</p>
        </div>
      </div>
      <div class="module-form-grid">
        <x-module.field name="period_type" label="Report type" :required="true">
          <select class="module-input" id="period_type" name="period_type" required aria-describedby="period_type_error" data-period-type>
            @foreach(\App\Models\GeneratedReport::PERIOD_TYPES as $key => $label)
              <option value="{{ $key }}" @selected(old('period_type', 'month') === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </x-module.field>

        <x-module.field name="year" label="Year" :required="true">
          <select class="module-input" id="year" name="year" required aria-describedby="year_error">
            @foreach($years as $year)
              <option value="{{ $year }}" @selected((int) old('year', $defaultPeriod->year) === $year)>{{ $year }}</option>
            @endforeach
          </select>
        </x-module.field>

        <div data-period-part="month">
          <x-module.field name="month" label="Month">
            <select class="module-input" id="month" name="month" aria-describedby="month_error">
              @foreach(range(1, 12) as $month)
                <option value="{{ $month }}" @selected((int) old('month', $defaultPeriod->month) === $month)>{{ \Carbon\Carbon::create(2000, $month, 1)->format('F') }}</option>
              @endforeach
            </select>
          </x-module.field>
        </div>

        <div data-period-part="quarter">
          <x-module.field name="quarter" label="Quarter">
            <select class="module-input" id="quarter" name="quarter" aria-describedby="quarter_error">
              @foreach([1 => 'Q1 · Jan–Mar', 2 => 'Q2 · Apr–Jun', 3 => 'Q3 · Jul–Sep', 4 => 'Q4 · Oct–Dec'] as $quarter => $label)
                <option value="{{ $quarter }}" @selected((int) old('quarter', 1) === $quarter)>{{ $label }}</option>
              @endforeach
            </select>
          </x-module.field>
        </div>
      </div>
    </div>

    <div class="module-panel">
      <div class="module-panel-head">
        <div>
          <h2>Area</h2>
          <p>{{ $areaLabel }}</p>
        </div>
      </div>
      @if($municipalities->isNotEmpty())
        <div class="module-form-grid">
          <x-module.field name="municipality_id" label="Cover" hint="Your whole assigned area, or one municipality in it.">
            <select class="module-input" id="municipality_id" name="municipality_id" aria-describedby="municipality_id_hint municipality_id_error">
              <option value="">My whole assigned area</option>
              @foreach($municipalities as $municipality)
                <option value="{{ $municipality->id }}" @selected((int) old('municipality_id') === (int) $municipality->id)>{{ $municipality->name }}</option>
              @endforeach
            </select>
          </x-module.field>
        </div>
      @else
        <p class="module-hint">The report covers your assigned municipality.</p>
      @endif
    </div>

    <div class="module-form-actions">
      <button class="module-button module-button-primary" type="submit" data-report-submit>Generate and save</button>
    </div>
  </form>
</div>
@endsection

@push('scripts')
  <script>
    (() => {
      const form = document.querySelector('[data-report-form]');
      const type = form?.querySelector('[data-period-type]');
      if (!form || !type) return;
      // Show only the month or quarter field the chosen type needs; the server
      // validates the same rule, so this is convenience, not enforcement.
      const sync = () => form.querySelectorAll('[data-period-part]').forEach(part => {
        const active = part.dataset.periodPart === type.value;
        part.hidden = !active;
        part.querySelectorAll('select').forEach(select => { select.disabled = !active; });
      });
      type.addEventListener('change', sync);
      sync();
      form.addEventListener('submit', () => {
        const button = form.querySelector('[data-report-submit]');
        if (button) { button.disabled = true; button.textContent = 'Generating…'; }
      });
    })();
  </script>
@endpush
