@extends('layouts.app')

@section('title', 'Rice Seed Distribution Sheets')

@push('styles')
  @include('partials.operations-ui-styles')
@endpush

@section('content')
@include('rice_seed_distributions.batches._styles')

<div class="module-page rice-sheet-page">
  <header class="module-header">
    <div>
      <div class="module-eyebrow">Assistance distribution</div>
      <h1>Rice seed distribution sheets</h1>
      <p>A sheet groups releases that are already in the assistance register so they can be printed on one form. Releases stay usable without one.</p>
    </div>
    <div class="module-actions">
      <a class="module-button" href="{{ route('rice-seed-distributions.index') }}">Assistance register</a>
      @if($canManage)
        <a class="module-button module-button-primary" href="{{ route('rice-distribution-batches.create', ['municipality_id' => $selectedMunicipalityId]) }}">New sheet</a>
      @else
        <span class="module-badge module-badge-green">Read-only oversight</span>
      @endif
    </div>
  </header>

  @include('partials.form-feedback')

  <section class="module-panel">
    <div class="module-panel-head"><h2>Find a sheet</h2></div>
    <form method="GET" action="{{ route('rice-distribution-batches.index') }}" class="module-form-body">
      <div class="module-form-grid">
        <x-module.field name="q" label="Program or reference" hint="Matches any part of the reference.">
          <input class="module-input" id="q" type="search" name="q" value="{{ $q }}" maxlength="120"
            placeholder="e.g. Rice Seed Program" aria-describedby="q_hint q_error">
        </x-module.field>

        <x-module.field name="planting_year" label="Planting year">
          <input class="module-input" id="planting_year" type="number" name="planting_year"
            value="{{ $plantingYear }}" min="1990" max="{{ now()->year + 1 }}" step="1"
            aria-describedby="planting_year_error">
        </x-module.field>

        @if($canChooseMunicipality)
          <x-module.field name="municipality_id" label="Municipality">
            <select class="module-input" id="municipality_id" name="municipality_id" aria-describedby="municipality_id_error">
              <option value="">All authorized offices</option>
              @foreach(($municipalities ?? []) as $municipality)
                <option value="{{ $municipality->id }}" @selected((string) $selectedMunicipalityId === (string) $municipality->id)>
                  {{ $municipality->name }}
                </option>
              @endforeach
            </select>
          </x-module.field>
        @endif
      </div>

      <div class="module-filter-actions">
        <span>Totals below follow this view.</span>
        <div class="module-filter-buttons">
          @if(filled($q) || filled($plantingYear) || filled($selectedMunicipalityId))
            <a class="module-button" href="{{ route('rice-distribution-batches.index') }}">Clear filters</a>
          @endif
          <button class="module-button module-button-primary" type="submit">Apply filters</button>
        </div>
      </div>
    </form>
  </section>

  <section class="module-panel">
    <div class="module-panel-head"><h2>Sheets</h2></div>

    @if($batches->isNotEmpty())
      <div class="module-table-scroll rice-sheet-register">
        <table class="module-table">
          <caption class="sr-only">Rice seed distribution sheets you are authorized to see</caption>
          <thead>
            <tr>
              <th scope="col">Reference</th>
              <th scope="col">Municipality</th>
              <th scope="col">Planting</th>
              <th scope="col">Harvest reporting</th>
              <th scope="col">Releases</th>
              <th scope="col">Total seed (kg)</th>
              <th scope="col">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($batches as $batch)
              <tr>
                <td data-label="Reference" role="cell">
                  <strong>{{ $batch->reference }}</strong>
                  @if($batch->notes)<small>{{ Str::limit($batch->notes, 70) }}</small>@endif
                </td>
                <td data-label="Municipality" role="cell">{{ optional($batch->municipality)->name ?: 'Not recorded' }}</td>
                <td data-label="Planting" role="cell">{{ $batch->plantingSeasonHeading() ?: 'Not recorded' }}</td>
                <td data-label="Harvest reporting" role="cell">
                  {{-- Left explicitly empty until a harvest is reported; never inferred. --}}
                  {{ $batch->harvestSeasonHeading() ?: 'Not yet reported' }}
                </td>
                <td data-label="Releases" role="cell">{{ number_format((int) $batch->releases_count) }}</td>
                <td data-label="Total seed (kg)" role="cell">
                  {{ number_format((float) ($batch->releases_kgs_sum ?? 0), 2) }}
                  @if((int) ($batch->non_kilogram_releases_count ?? 0) > 0)
                    {{-- Stated rather than folded in: nothing here converts pieces or
                         sacks into kilograms, so those releases cannot join this total. --}}
                    <small>{{ number_format((int) $batch->non_kilogram_releases_count) }} release{{ $batch->non_kilogram_releases_count === 1 ? '' : 's' }} recorded in another unit, not counted here</small>
                  @endif
                </td>
                <td class="rice-sheet-actions" role="cell">
                  <div class="module-row-actions">
                    <a class="module-button module-button-small" href="{{ route('rice-distribution-batches.sheet', $batch) }}"
                      aria-label="Open the sheet for {{ $batch->reference }}">Open sheet</a>
                    @can('update', $batch)
                      <a class="module-button module-button-small" href="{{ route('rice-distribution-batches.edit', $batch) }}"
                        aria-label="Edit {{ $batch->reference }}">Edit</a>
                    @endcan
                    @can('delete', $batch)
                      @if((int) $batch->releases_count === 0)
                        <form method="POST" action="{{ route('rice-distribution-batches.destroy', $batch) }}"
                          onsubmit="return confirm('Delete this sheet? The releases it grouped are not deleted.')">
                          @csrf @method('DELETE')
                          <button class="module-button module-button-danger module-button-small" type="submit"
                            aria-label="Delete {{ $batch->reference }}">Delete</button>
                        </form>
                      @else
                        {{-- Explained rather than hidden, so staff know why it is unavailable. --}}
                        <button class="module-button module-button-small" type="button" disabled
                          title="Remove its {{ $batch->releases_count }} release(s) from this sheet first.">Delete</button>
                      @endif
                    @endcan
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="module-form-body">{{ $batches->links() }}</div>
    @else
      <div class="module-empty">
        <span class="module-empty-icon">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4zM4 9h16M9 9v10"></path></svg>
        </span>
        <strong>{{ (filled($q) || filled($plantingYear)) ? 'No sheets match these filters' : 'No sheets in this workspace yet' }}</strong>
        <span>
          {{ (filled($q) || filled($plantingYear))
              ? 'Try a different reference or year, or clear the filters.'
              : 'Create a sheet to group releases that were already recorded, then print or export the form.' }}
        </span>
        @if(filled($q) || filled($plantingYear))
          <a class="module-button" href="{{ route('rice-distribution-batches.index') }}">Clear filters</a>
        @elseif($canManage)
          <a class="module-button module-button-primary" href="{{ route('rice-distribution-batches.create') }}">New sheet</a>
        @endif
      </div>
    @endif
  </section>
</div>
@endsection
