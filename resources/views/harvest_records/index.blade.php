@extends('layouts.app')

@section('title', 'Harvest Records')

@push('styles')
  @include('partials.operations-ui-styles')
  @include('harvest_records._styles')
@endpush

@php
  $selectedMunicipality = collect($municipalities)->firstWhere('id', (int) ($filters['municipality_id'] ?? 0));
  $scopeLabel = $selectedMunicipality?->name ?? (auth()->user()->municipality?->name ?: 'All municipalities');

  $hasFilters = collect($filters)
      ->filter(fn ($v, $k) => filled($v) && ! ($k === 'per_page' && (int) $v === 20))
      ->isNotEmpty();

  $farmerName = fn ($farmer) => $farmer
      ? trim(collect([$farmer->last_name, $farmer->first_name, $farmer->middle_name, $farmer->ext_name])->filter()->implode(' '))
      : null;
@endphp

@section('content')
<div class="module-page">
  <header class="module-header">
    <div>
      <div class="module-eyebrow">Production recording</div>
      <h1>Harvest records</h1>
      <p>What was actually harvested, across every commodity. This is what the production report reads.</p>
    </div>
    <div class="module-actions">
      <a class="module-button" href="{{ route('harvest-records.export', request()->query()) }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5M4 19h16"></path></svg>
        Export current view
      </a>
      @if($canManageOperations)
        <a class="module-button module-button-primary" href="{{ route('harvest-records.create') }}">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"></path></svg>
          Record harvest
        </a>
      @else
        <span class="module-badge module-badge-green">Read-only oversight</span>
      @endif
    </div>
  </header>

  @if(session('success'))<div class="module-alert">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="module-alert module-alert-error">{{ session('error') }}</div>@endif

  <section class="module-kpis" aria-label="Harvest summary">
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Harvests recorded</span></div>
      <strong>{{ number_format($summary['records']) }}</strong>
      <small>{{ number_format($summary['projected']) }} from assistance releases</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Commodities</span></div>
      <strong>{{ number_format($summary['commodities']) }}</strong>
      <small>Distinct commodities in this view</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Area harvested</span></div>
      <strong>{{ number_format($summary['area'], 2) }}</strong>
      <small>Hectares, where an area was recorded</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Quantity</span></div>
      @if(count($summary['by_unit']))
        {{-- Never one total. Sacks and kilograms are different measurements, and one
             figure through both would be a number nobody could stand behind. --}}
        <div class="harvest-unit-totals">
          @foreach($summary['by_unit'] as $unit)
            <span class="harvest-unit-total">
              <strong>{{ number_format($unit['total'], 2) }}</strong>
              <small>{{ $unit['unit'] }} · {{ number_format($unit['records']) }} {{ Str::plural('record', $unit['records']) }}</small>
            </span>
          @endforeach
        </div>
      @else
        <strong>—</strong>
        <small>Nothing recorded in this view</small>
      @endif
    </article>
  </section>

  <section class="module-panel">
    <div class="module-panel-head">
      <div><h2>Find a harvest</h2><p>These filters also apply to the figures above and to the export.</p></div>
      @if($hasFilters)<span class="module-panel-tag">Filtered view</span>@endif
    </div>

    <form method="GET" action="{{ route('harvest-records.index') }}">
      <div class="module-form-grid">
        <div class="module-field">
          <label for="harvestSearch">Search</label>
          <input class="module-input" id="harvestSearch" type="search" name="q" value="{{ $filters['q'] }}"
            placeholder="Variety, notes, farmer name, or FFRS">
        </div>

        @if($canChooseMunicipality)
          <div class="module-field">
            <label for="harvestMunicipality">Municipality</label>
            <select class="module-input" id="harvestMunicipality" name="municipality_id">
              <option value="">All municipalities</option>
              @foreach($municipalities as $municipality)
                <option value="{{ $municipality->id }}" @selected((string) $filters['municipality_id'] === (string) $municipality->id)>{{ $municipality->name }}</option>
              @endforeach
            </select>
          </div>
        @endif

        <div class="module-field">
          <label for="harvestCommodity">Commodity</label>
          <select class="module-input" id="harvestCommodity" name="commodity">
            <option value="">All commodities</option>
            @foreach($commodityOptions as $key => $label)
              <option value="{{ $key }}" @selected($filters['commodity'] === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="module-field">
          <label for="harvestYear">Year</label>
          <select class="module-input" id="harvestYear" name="harvest_year">
            <option value="">All years</option>
            @foreach($yearOptions as $year)
              <option value="{{ $year }}" @selected((string) $filters['harvest_year'] === (string) $year)>{{ $year }}</option>
            @endforeach
          </select>
        </div>

        <div class="module-field">
          <label for="harvestSeason">Season</label>
          <select class="module-input" id="harvestSeason" name="season">
            <option value="">Any season</option>
            @foreach($seasonOptions as $key => $label)
              <option value="{{ $key }}" @selected($filters['season'] === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="module-field">
          <label for="harvestSource">Where it came from</label>
          <select class="module-input" id="harvestSource" name="source">
            <option value="">Any source</option>
            <option value="direct" @selected($filters['source'] === 'direct')>Entered directly</option>
            <option value="projected" @selected($filters['source'] === 'projected')>From an assistance release</option>
          </select>
        </div>

        <div class="module-field">
          <label for="harvestPerPage">Rows per page</label>
          <select class="module-input" id="harvestPerPage" name="per_page">
            @foreach([20, 50, 100] as $number)
              <option value="{{ $number }}" @selected((int) $filters['per_page'] === $number)>{{ $number }} rows</option>
            @endforeach
          </select>
        </div>
      </div>

      <div class="module-actions">
        @if($hasFilters)<a class="module-button" href="{{ route('harvest-records.index') }}">Clear</a>@endif
        <button class="module-button module-button-primary" type="submit">Apply filters</button>
      </div>
    </form>
  </section>

  <section class="module-panel">
    <div class="module-table-tools">
      <div>
        <strong>Recorded harvests</strong>
        <span>{{ number_format($records->total()) }} {{ Str::plural('record', $records->total()) }} · {{ $scopeLabel }}</span>
      </div>
    </div>

    @if($records->isNotEmpty())
      <div class="module-table-scroll">
        <table class="module-table">
          <thead>
            <tr>
              <th>Commodity</th>
              <th>Period</th>
              <th>Quantity</th>
              <th>Farmer</th>
              <th>Area</th>
              <th>Source</th>
              <th><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody>
            @foreach($records as $harvest)
              <tr>
                <td>
                  <strong>{{ $harvest->commodityLabel() }}</strong>
                  @if($harvest->variety)<br><small>{{ $harvest->variety }}</small>@endif
                </td>
                <td>
                  {{ $harvest->periodLabel() }}
                  @if($harvest->date_harvested)<br><small>{{ $harvest->date_harvested->format('d M Y') }}</small>@endif
                </td>
                <td>
                  <span class="harvest-quantity">
                    <strong>{{ number_format((float) $harvest->quantity, 2) }}</strong>
                    <small>{{ $harvest->quantityUnitLabel() }}</small>
                  </span>
                </td>
                <td>
                  {{ $farmerName($harvest->farmer) ?? '—' }}
                  @unless($harvest->farmer)<br><small>{{ $harvest->municipality?->name }} total</small>@endunless
                </td>
                <td>{{ $harvest->area_harvested_ha ? number_format((float) $harvest->area_harvested_ha, 4).' ha' : '—' }}</td>
                <td>
                  @if($harvest->isProjected())
                    <span class="harvest-source projected">Assistance release</span>
                  @else
                    <span class="harvest-source direct">Entered directly</span>
                  @endif
                </td>
                <td>
                  @if($canManageOperations)
                    @if($harvest->isProjected())
                      {{-- Editable at its source only. Offering an edit here would take the
                           operator to a form whose next release save silently discards it. --}}
                      <a class="module-button module-button-small"
                        href="{{ route('rice-seed-distributions.edit', $harvest->rice_seed_distribution_id) }}">
                        Open release
                      </a>
                    @else
                      <div class="module-row-actions">
                        <a class="module-button module-button-small" href="{{ route('harvest-records.edit', $harvest) }}">Edit</a>
                        <form method="POST" action="{{ route('harvest-records.destroy', $harvest) }}"
                          onsubmit="return confirm('Delete this {{ addslashes($harvest->commodityLabel()) }} harvest for {{ $harvest->periodLabel() }}?')">
                          @csrf
                          @method('DELETE')
                          <button class="module-button module-button-small" type="submit">Delete</button>
                        </form>
                      </div>
                    @endif
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      @include('partials.pagination', ['paginator' => $records])
    @else
      <div class="harvest-empty">
        <strong>No harvests recorded in this view</strong>
        @if($hasFilters)
          <p>Nothing matches the current filters. <a href="{{ route('harvest-records.index') }}">Clear them</a> to see everything.</p>
        @else
          <p>
            Rice harvested from an assistance release appears here automatically once its
            production section is filled in. Every other commodity is recorded here.
          </p>
        @endif
      </div>
    @endif
  </section>
</div>
@endsection
