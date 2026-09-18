@extends('layouts.app')

@section('title', 'Rice Seed Distribution Sheet')

@push('styles')
  @include('partials.operations-ui-styles')
@endpush

@section('content')
@include('rice_seed_distributions.batches._styles')

@php
    // Every heading below is built from the stored season and year on the batch,
    // so a sheet from another season prints its own band rather than a fixed one.
    $totalsRow = $totals ? app(App\Support\RiceSeedDistributionSheet::class)->totalsRow($totals, $columns) : [];
@endphp

<div class="module-page rice-sheet-page">
  <header class="module-header rice-print-screen-only">
    <div>
      <div class="module-eyebrow">Assistance distribution</div>
      <h1>{{ $batch->reference }}</h1>
      <p>
        {{ optional($batch->municipality)->name ?: 'Municipality not recorded' }} &middot;
        {{ $batch->plantingSeasonHeading() ?: 'Planting season not recorded' }} planting &middot;
        {{ $batch->harvestSeasonHeading() ? $batch->harvestSeasonHeading().' harvest reporting' : 'Harvest not yet reported' }}
      </p>
    </div>
    <div class="module-actions">
      <button class="module-button module-button-primary" type="button" onclick="window.print()">Print or save as PDF</button>
      @if($canExport)
        <a class="module-button" href="{{ route('rice-distribution-batches.export', $batch) }}">Export Excel</a>
      @endif
      @can('update', $batch)
        <a class="module-button" href="{{ route('rice-distribution-batches.edit', $batch) }}">Edit sheet</a>
      @endcan
      <a class="module-button" href="{{ route('rice-distribution-batches.index') }}">Back to sheets</a>
    </div>
  </header>

  @include('partials.form-feedback')

  @if($releases->isEmpty())
    <div class="module-empty rice-print-screen-only">
      <strong>No releases on this sheet yet</strong>
      <span>Add existing releases from the assistance register to this sheet. Releases keep working without one.</span>
      <a class="module-button" href="{{ route('rice-seed-distributions.index') }}">Open the assistance register</a>
    </div>
  @else
    <section class="module-panel rice-print-panel">
      <div class="rice-print-scroll">
        <div class="rice-print-sheet">
          <header class="rice-print-head">
            @foreach($titles as $line)
              <p>{{ $line }}</p>
            @endforeach
          </header>

          <table class="module-table rice-print-table">
            <caption class="sr-only">
              Rice seed distribution sheet for {{ $batch->reference }}, showing farmer profile, seed distribution,
              production monitoring, other records, and a space for each recipient's signature.
            </caption>
            <thead>
              {{-- Two header rows: the grouped bands, then the columns inside them.
                   thead repeats on every printed page via the print stylesheet. --}}
              <tr>
                @foreach($groups as $group)
                  <th scope="colgroup" colspan="{{ count($group['columns']) }}">{{ $group['heading'] }}</th>
                @endforeach
              </tr>
              <tr>
                @foreach($columns as $column)
                  <th scope="col" @class(['rice-print-sign' => (bool) ($column['blank'] ?? false)])>{{ $column['label'] }}</th>
                @endforeach
              </tr>
            </thead>
            <tbody>
              @foreach($rows as $row)
                <tr>
                  @foreach($columns as $column)
                    {{-- A blank column is the handwritten signature space and stays empty. --}}
                    <td @class(['rice-print-sign' => (bool) ($column['blank'] ?? false)])>
                      {{ ($column['blank'] ?? false) ? '' : ($row[$column['key']] ?? '') }}
                    </td>
                  @endforeach
                </tr>
              @endforeach
            </tbody>
            @if($totalsRow)
              <tfoot>
                <tr>
                  @foreach($columns as $index => $column)
                    <td class="rice-sheet-total">{{ $totalsRow[$index] ?? '' }}</td>
                  @endforeach
                </tr>
              </tfoot>
            @endif
          </table>

          <div class="rice-print-signatories">
            <div>
              <p>Prepared by</p>
              <p>&nbsp;</p>
              <p>_______________________________</p>
            </div>
            <div>
              <p>Certified correct</p>
              <p>&nbsp;</p>
              <p>_______________________________</p>
            </div>
          </div>

          <p class="rice-print-footnote">
            Registered rice area is the area registered for rice and is not the farmer's total farm area.
            Seed bag weight and harvest bag weight are recorded separately.
          </p>
        </div>
      </div>
    </section>

    <div class="module-form-body rice-print-screen-only">{{ $releases->links() }}</div>
  @endif
</div>
@endsection
