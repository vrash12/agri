@extends('layouts.app')

@section('title', 'Reports')

@push('styles')
  @include('partials.operations-ui-styles')
@endpush

@section('content')
<div class="module-page">
  <header class="module-header">
    <div>
      <div class="module-eyebrow">Reporting</div>
      <h1>Period reports</h1>
      <p>Monthly, quarterly and annual summaries for your assigned area. A monthly report for each municipality and province is saved here automatically; you can also generate one for any past period. Reports stay inside AgriLGU and are never emailed.</p>
    </div>
    <div class="module-actions">
      <a class="module-button module-button-primary" href="{{ route('reports.create') }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"></path></svg>
        Generate a report
      </a>
    </div>
  </header>

  @if(session('success'))<div class="module-alert">{{ session('success') }}</div>@endif

  <form class="module-panel module-filter" method="GET" action="{{ route('reports.index') }}" aria-label="Filter reports">
    <div class="module-filter-grid">
      <label class="module-field" for="reportPeriodType">
        <span>Period</span>
        <select class="module-input" id="reportPeriodType" name="period_type">
          <option value="">All periods</option>
          @foreach(\App\Models\GeneratedReport::PERIOD_TYPES as $key => $label)
            <option value="{{ $key }}" @selected(($filters['period_type'] ?? '') === $key)>{{ $label }}</option>
          @endforeach
        </select>
      </label>
      <label class="module-field" for="reportSource">
        <span>Created</span>
        <select class="module-input" id="reportSource" name="source">
          <option value="">Automatic and on request</option>
          <option value="scheduled" @selected(($filters['source'] ?? '') === 'scheduled')>Automatic monthly</option>
          <option value="on_demand" @selected(($filters['source'] ?? '') === 'on_demand')>Generated on request</option>
        </select>
      </label>
    </div>
    <div class="module-filter-actions">
      <button class="module-button module-button-primary" type="submit">Apply</button>
      @if(array_filter($filters))<a class="module-button" href="{{ route('reports.index') }}">Clear</a>@endif
    </div>
  </form>

  <section class="module-panel" aria-labelledby="reportArchiveHeading">
    <div class="module-panel-head">
      <div>
        <h2 id="reportArchiveHeading">Archive</h2>
        <p>{{ number_format($reports->total()) }} {{ \Illuminate\Support\Str::plural('report', $reports->total()) }} you can open.</p>
      </div>
    </div>
    @if($reports->isEmpty())
      <div class="module-empty">
        <p>No reports yet. The first automatic monthly report appears after the month ends, or generate one now.</p>
      </div>
    @else
      <div class="module-table-scroll">
        <table class="module-table">
          <thead>
            <tr>
              <th scope="col">Report</th>
              <th scope="col">Period</th>
              <th scope="col">Type</th>
              <th scope="col">Created</th>
            </tr>
          </thead>
          <tbody>
            @foreach($reports as $report)
              <tr>
                <th scope="row"><a href="{{ route('reports.show', $report) }}">{{ $report->title }}</a></th>
                <td>{{ $report->period_start->format('M j, Y') }} – {{ $report->period_end->format('M j, Y') }}</td>
                <td>{{ $report->periodTypeLabel() }}</td>
                <td>{{ $report->sourceLabel() }}<br><small class="module-hint">{{ \App\Support\LocalTime::fromUtc($report->created_at)?->format('M j, Y g:i A') }}</small></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="module-pagination">{{ $reports->links() }}</div>
    @endif
  </section>
</div>
@endsection
