@extends('layouts.app')

@section('title', $report->title)

@push('styles')
  @include('partials.operations-ui-styles')
  <style>
    .report-sections { display: grid; gap: 16px; }
    .report-figures { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin: 0; padding: 16px; }
    .report-figures div { padding: 12px 14px; border: 1px solid var(--module-border, #dfe7dc); border-radius: 12px; background: var(--module-surface, #fff); }
    .report-figures dt { font-size: 12px; color: var(--module-muted); }
    .report-figures dd { margin: 4px 0 0; font-size: 20px; font-weight: 700; color: var(--module-ink, #18311f); }
    .report-note { font-size: 13px; color: var(--module-muted); margin: 8px 0 0; }
    @media print {
      .app-sidebar, .app-topbar, .module-actions, .office-assistant, nav { display: none !important; }
      .module-page { padding: 0 !important; }
      .module-panel { break-inside: avoid; box-shadow: none !important; }
    }
  </style>
@endpush

@php
  $summary = $report->summary ?? [];
  $totals = $summary['totals'] ?? [];
  $labels = $summary['labels'] ?? [];
  $figure = fn ($value, int $decimals = 0) => $value === null ? 'Not recorded' : number_format((float) $value, $decimals);
  $registry = $totals['registry'] ?? [];
  $assistance = $totals['assistance'] ?? [];
  $harvests = $totals['harvests'] ?? [];
  $health = $totals['animal_health'] ?? [];
  $machinery = $totals['machinery'] ?? [];
@endphp

@section('content')
<div class="module-page">
  <header class="module-header">
    <div>
      <div class="module-eyebrow">{{ $report->periodTypeLabel() }} report · {{ $report->sourceLabel() }}</div>
      <h1>{{ $report->title }}</h1>
      <p>{{ $report->period_start->format('F j, Y') }} – {{ $report->period_end->format('F j, Y') }} · {{ number_format((int) ($summary['municipality_count'] ?? 0)) }} {{ \Illuminate\Support\Str::plural('municipality', (int) ($summary['municipality_count'] ?? 0)) }}.
        Generated {{ \App\Support\LocalTime::fromUtc($summary['generated_at'] ?? $report->created_at)?->format('M j, Y g:i A') }}.</p>
    </div>
    <div class="module-actions">
      <a class="module-button" href="{{ route('reports.index') }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>All reports
      </a>
      <a class="module-button" href="{{ route('reports.download', $report) }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5M4 19h16"></path></svg>Download CSV
      </a>
      <button class="module-button module-button-primary" type="button" onclick="window.print()">Print or save as PDF</button>
    </div>
  </header>

  @if(session('success'))<div class="module-alert">{{ session('success') }}</div>@endif
  @if(! empty($summary['partial']))
    <div class="module-alert module-alert-error" role="note">This period had not ended when the report was generated. Records saved later are not included; generate it again after the period ends for complete figures.</div>
  @endif

  <div class="report-sections">
    <section class="module-panel" aria-labelledby="reportRegistry">
      <div class="module-panel-head"><div><h2 id="reportRegistry">Farmer registry and mapping</h2><p>Current records when the report was generated, plus farmers added during the period.</p></div></div>
      <dl class="report-figures">
        <div><dt>Registered farmers</dt><dd>{{ $figure($registry['farmers'] ?? 0) }}</dd></div>
        <div><dt>Added in period</dt><dd>{{ $figure($registry['new_farmers'] ?? 0) }}</dd></div>
        <div><dt>With a mapped parcel</dt><dd>{{ $figure($registry['mapped_farmers'] ?? 0) }}</dd></div>
        <div><dt>Mapping coverage</dt><dd>{{ isset($registry['mapping_coverage']) ? $figure($registry['mapping_coverage'], 1).'%' : 'Not recorded' }}</dd></div>
        <div><dt>Missing FFRS reference</dt><dd>{{ $figure($registry['missing_ffrs'] ?? 0) }}</dd></div>
        <div><dt>Missing farm location</dt><dd>{{ $figure($registry['missing_location'] ?? 0) }}</dd></div>
      </dl>
    </section>

    <section class="module-panel" aria-labelledby="reportAssistance">
      <div class="module-panel-head"><div><h2 id="reportAssistance">Assistance released in the period</h2><p>Releases are hand-overs, not production. Beneficiaries need a valid same-municipality farmer link.</p></div></div>
      <dl class="report-figures">
        <div><dt>Releases</dt><dd>{{ $figure($assistance['releases'] ?? 0) }}</dd></div>
        <div><dt>Unique beneficiaries</dt><dd>{{ $figure($assistance['beneficiaries'] ?? 0) }}</dd></div>
        <div><dt>Reach</dt><dd>{{ isset($assistance['reach']) ? $figure($assistance['reach'], 1).'%' : 'Not recorded' }}</dd></div>
      </dl>
      @if(! empty($assistance['categories']))
        <div class="module-table-scroll">
          <table class="module-table">
            <caption class="sr-only">Assistance by category</caption>
            <thead><tr><th scope="col">Category</th><th scope="col" class="module-numeric">Releases</th><th scope="col" class="module-numeric">Beneficiaries</th><th scope="col">Quantity, by unit</th></tr></thead>
            <tbody>
              @foreach($assistance['categories'] as $category => $row)
                <tr>
                  <th scope="row">{{ $labels['categories'][$category] ?? 'Category not recorded' }}</th>
                  <td class="module-numeric">{{ $figure($row['releases'] ?? 0) }}</td>
                  <td class="module-numeric">{{ $figure($row['beneficiaries'] ?? 0) }}</td>
                  <td>{{ collect($row['quantities'] ?? [])->map(fn ($quantity, $unit) => $figure($quantity, 2).' '.($labels['release_units'][$unit] ?? 'unit not recorded'))->implode(' · ') }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
      @if(($assistance['undated'] ?? 0) > 0)<p class="report-note">{{ $figure($assistance['undated']) }} releases in this area have no release date and cannot be placed in any period.</p>@endif
    </section>

    <section class="module-panel" aria-labelledby="reportHarvests">
      <div class="module-panel-head"><div><h2 id="reportHarvests">Harvests dated in the period</h2><p>Each commodity and unit is kept separate; sacks and kilograms are never added together.</p></div></div>
      <dl class="report-figures"><div><dt>Harvest records</dt><dd>{{ $figure($harvests['records'] ?? 0) }}</dd></div></dl>
      @if(! empty($harvests['production']))
        <div class="module-table-scroll">
          <table class="module-table">
            <caption class="sr-only">Production by commodity and unit</caption>
            <thead><tr><th scope="col">Commodity</th><th scope="col">Unit</th><th scope="col" class="module-numeric">Records</th><th scope="col" class="module-numeric">Quantity</th></tr></thead>
            <tbody>
              @foreach($harvests['production'] as $key => $row)
                <tr>
                  <th scope="row">{{ $labels['commodities'][strtok((string) $key, '|')] ?? 'Commodity not recorded' }}</th>
                  <td>{{ $labels['harvest_units'][substr((string) strstr((string) $key, '|'), 1)] ?? 'Unit not recorded' }}</td>
                  <td class="module-numeric">{{ $figure($row['records'] ?? 0) }}</td>
                  <td class="module-numeric">{{ $figure($row['quantity'] ?? 0, 3) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
      @if(($harvests['undated'] ?? 0) > 0)<p class="report-note">{{ $figure($harvests['undated']) }} harvest records for this year have no harvest date, so they are not counted inside the period.</p>@endif
    </section>

    <section class="module-panel" aria-labelledby="reportHealth">
      <div class="module-panel-head"><div><h2 id="reportHealth">Animal Health services in the period</h2><p>One service record can cover many animals, so the two are counted separately.</p></div></div>
      <dl class="report-figures">
        <div><dt>Services</dt><dd>{{ $figure($health['services'] ?? 0) }}</dd></div>
        <div><dt>Animals covered</dt><dd>{{ $figure($health['animals'] ?? 0) }}</dd></div>
        @foreach($health['types'] ?? [] as $type => $row)
          <div><dt>{{ $labels['services'][$type] ?? 'Service not recorded' }}</dt><dd>{{ $figure($row['services'] ?? 0) }}</dd></div>
        @endforeach
      </dl>
    </section>

    <section class="module-panel" aria-labelledby="reportMachinery">
      <div class="module-panel-head"><div><h2 id="reportMachinery">Machinery inventory</h2><p>Current inventory status when the report was generated.</p></div></div>
      <dl class="report-figures">
        <div><dt>Units</dt><dd>{{ $figure($machinery['units'] ?? 0) }}</dd></div>
        @foreach($machinery['availability'] ?? [] as $status => $count)
          <div><dt>{{ $labels['availability'][$status] ?? 'Status not recorded' }}</dt><dd>{{ $figure($count) }}</dd></div>
        @endforeach
      </dl>
    </section>

    @if(! empty($summary['breakdown']))
      <section class="module-panel" aria-labelledby="reportBreakdown">
        <div class="module-panel-head"><div><h2 id="reportBreakdown">By municipality</h2><p>Side-by-side counts in alphabetical order. No combined score or ranking is calculated.</p></div></div>
        <div class="module-table-scroll">
          <table class="module-table">
            <caption class="sr-only">Figures by municipality</caption>
            <thead><tr><th scope="col">Municipality</th><th scope="col" class="module-numeric">Farmers</th><th scope="col" class="module-numeric">Mapping coverage</th><th scope="col" class="module-numeric">Releases</th><th scope="col" class="module-numeric">Beneficiaries</th><th scope="col" class="module-numeric">Harvest records</th><th scope="col" class="module-numeric">Animal Health services</th></tr></thead>
            <tbody>
              @foreach($summary['breakdown'] as $row)
                <tr>
                  <th scope="row">{{ $row['municipality'] }}</th>
                  <td class="module-numeric">{{ $figure($row['farmers']) }}</td>
                  <td class="module-numeric">{{ $row['mapping_coverage'] === null ? 'Not recorded' : $figure($row['mapping_coverage'], 1).'%' }}</td>
                  <td class="module-numeric">{{ $figure($row['releases']) }}</td>
                  <td class="module-numeric">{{ $figure($row['beneficiaries']) }}</td>
                  <td class="module-numeric">{{ $figure($row['harvest_records']) }}</td>
                  <td class="module-numeric">{{ $figure($row['animal_services']) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </section>
    @endif

    <p class="report-note">Counts describe records saved in AgriLGU, not every event in the area. A missing record cannot establish that something did not happen. This archived report does not change when records are edited later.</p>
  </div>
</div>
@endsection
