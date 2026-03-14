@extends('layouts.app')

@section('title', 'Farmer Records')

@section('content')
  <div class="card">
    <div class="card-header" style="align-items:flex-start;">
      <div>
        <h1 class="h1" style="margin:0;">Distribution Records</h1>

        <p class="p" style="margin-top:8px;">
          Farmer:
          <strong>{{ $farmer->last_name }}, {{ $farmer->first_name }} {{ $farmer->middle_name ?? '' }}</strong>
          @if(!empty($farmer->ffrs))
            • <span style="font-weight:900;">FFRS:</span> <span class="td-mono">{{ $farmer->ffrs }}</span>
          @endif
        </p>

        <p class="p" style="margin-top:6px;">
          <strong>Farm Location:</strong> {{ $farmer->farm_location ?? '—' }}
          @if(!empty($farmer->farm_municipality)) • {{ $farmer->farm_municipality }} @endif
          @if(!empty($farmer->farm_province)) • {{ $farmer->farm_province }} @endif
        </p>

        <p class="p" style="margin-top:6px;">
          <strong>Total Records:</strong> {{ $totalRecords }} •
          <strong>Total Kgs:</strong> {{ number_format((float)$totalKgs, 2) }}
        </p>
      </div>

      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a class="btn btn-soft" style="box-shadow:none;" href="{{ route('farmers.index') }}">
          ← Back to Farmers
        </a>

        <a class="btn" href="{{ route('rice-seed-distributions.create', ['farmer_id' => $farmer->id]) }}">
          + Add Distribution Record
        </a>
      </div>
    </div>

    {{-- Filters --}}
    <div style="padding:16px; border-bottom:1px solid var(--border);">
      <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
        <div style="flex:1; min-width:240px;">
          <label>Search (within this farmer)</label>
          <input class="input" name="q" value="{{ request('q') }}" placeholder="Variety / lot / sowing label / planted">
        </div>

        <div style="width:170px;">
          <label>Rows</label>
          <select name="per_page">
            @foreach([10,25,50,100] as $n)
              <option value="{{ $n }}" @selected((int)request('per_page', $perPage) === $n)>{{ $n }}</option>
            @endforeach
          </select>
        </div>

        <div style="width:170px;">
          <label>Received From</label>
          <input class="input" type="date" name="received_from" value="{{ request('received_from') }}">
        </div>

        <div style="width:170px;">
          <label>Received To</label>
          <input class="input" type="date" name="received_to" value="{{ request('received_to') }}">
        </div>

        <button class="btn btn-soft" style="box-shadow:none;" type="submit">Apply</button>

        <a class="btn btn-soft" style="box-shadow:none;"
           href="{{ route('farmers.records', $farmer->id) }}">
          Reset
        </a>
      </form>
    </div>

    {{-- Records Table --}}
    <div style="overflow:auto;">
      <table>
        <thead>
          <tr>
            <th style="width:60px;">No.</th>
            <th>Date Received</th>
            <th>Kgs Received</th>
            <th>Seed Variety Claimed</th>
            <th>Claimed Area (ha)</th>
            <th>Claimed Seeds (kg)</th>
            <th>Lot Series</th>
            <th style="text-align:right; white-space:nowrap;">Actions</th>
          </tr>
        </thead>

        <tbody>
        @forelse($records as $r)
          <tr>
            <td style="font-weight:900;">
              {{ ($records->currentPage() - 1) * $records->perPage() + $loop->iteration }}
            </td>

            <td>{{ $r->date_received ? \Illuminate\Support\Carbon::parse($r->date_received)->format('Y-m-d') : '—' }}</td>
            <td style="font-weight:900;">{{ number_format((float)$r->kgs_received, 2) }}</td>

            <td>{{ $r->seed_variety_claimed ?? '—' }}</td>
            <td>{{ $r->claimed_area_ha ?? '—' }}</td>
            <td>{{ $r->claimed_seeds_kg ?? '—' }}</td>

            <td style="max-width:320px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"
                title="{{ $r->lot_series ?? '' }}">
              {{ $r->lot_series ?? '—' }}
            </td>

            <td style="text-align:right; white-space:nowrap;">
              <a class="btn btn-soft" style="padding:8px 10px; box-shadow:none;"
                 href="{{ route('rice-seed-distributions.edit', $r) }}">
                Edit
              </a>

              <form method="POST" action="{{ route('rice-seed-distributions.destroy', $r) }}"
                    style="display:inline;" onsubmit="return confirm('Delete this record?')">
                @csrf
                @method('DELETE')
                <button class="btn btn-danger" style="padding:8px 10px;" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="8" style="padding:18px; color:var(--muted);">
              No distribution records found for this farmer.
            </td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div style="padding:14px 16px;">
      {{ $records->appends(request()->query())->links() }}
    </div>
  </div>
@endsection

@push('styles')
<style>
  .td-mono{
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono","Courier New", monospace;
    font-size: 12px;
  }
</style>
@endpush
