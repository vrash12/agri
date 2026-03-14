{{-- resources/views/anti_rabies_vaccinations/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Anti-Rabies Vaccination')

@push('styles')
<style>
  /* Better responsive chart grid (doesn't collapse too early) */
  .grid-charts{
    display:grid;
    gap: 12px;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  }
  .chartbox{
    height: 240px; /* ✅ important: Chart.js needs container height */
    position: relative;
  }
  .chartbox canvas{
    width: 100% !important;
    height: 100% !important;
    display:block;
  }
</style>
@endpush

@section('content')
  <div class="card">
    <div class="card-header">
      <div>
        <h1 class="h1">Anti-Rabies Vaccination</h1>
        <p class="p">Manage anti-rabies vaccination records with stats and charts.</p>
      </div>

      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a class="btn" href="{{ route('anti-rabies-vaccinations.create') }}">+ Add Record</a>
      </div>
    </div>

    <div style="padding:16px;">
      @if (session('success'))
        <div style="margin-bottom:12px; padding:12px 14px; border-radius:14px; background: rgba(16,185,129,.10); border:1px solid rgba(16,185,129,.25); color:#065f46;">
          {{ session('success') }}
        </div>
      @endif

      {{-- =========================
          KPI CARDS
         ========================= --}}
      <div class="grid grid-3" style="margin-bottom:14px;">
        <div class="card" style="padding:14px;">
          <div style="font-weight:900; font-size:12px; color: var(--muted);">Total Vaccinations</div>
          <div style="font-weight:900; font-size:26px; margin-top:6px;">{{ number_format($totalVaccinations ?? 0) }}</div>
          <div style="font-size:12px; color: var(--muted); margin-top:4px;">Based on current filters</div>
        </div>

        <div class="card" style="padding:14px;">
          <div style="font-weight:900; font-size:12px; color: var(--muted);">Unique Owners</div>
          <div style="font-weight:900; font-size:26px; margin-top:6px;">{{ number_format($uniqueOwners ?? 0) }}</div>
          <div style="font-size:12px; color: var(--muted); margin-top:4px;">Distinct owner names</div>
        </div>

        <div class="card" style="padding:14px;">
          <div style="font-weight:900; font-size:12px; color: var(--muted);">Unique Pets</div>
          <div style="font-weight:900; font-size:26px; margin-top:6px;">{{ number_format($uniquePets ?? 0) }}</div>
          <div style="font-size:12px; color: var(--muted); margin-top:4px;">
            Latest: {{ $latestVaccinationDate ? \Carbon\Carbon::parse($latestVaccinationDate)->format('Y-m-d') : '—' }}
          </div>
        </div>
      </div>

      {{-- =========================
          FILTERS  (✅ Removed Year)
         ========================= --}}
      <form method="GET" action="{{ route('anti-rabies-vaccinations.index') }}" style="margin-bottom:12px;">
        <div class="grid grid-3">
          <div class="field">
            <label>Search</label>
            <input class="input" name="q" value="{{ $q ?? '' }}" placeholder="Owner / Pet / Breed">
          </div>

          <div class="field">
            <label>Pet Type</label>
            <select class="input js-select" name="pet_type">
              <option value="">— All —</option>
              @foreach(($petTypeOptions ?? ['Dog','Cat']) as $t)
                <option value="{{ $t }}" @selected(($petType ?? '') === $t)>{{ $t }}</option>
              @endforeach
            </select>
          </div>

          <div class="field">
            <label>Barangay</label>
            <select class="input js-select" name="barangay">
              <option value="">— All —</option>
              @foreach(($barangayOptions ?? []) as $b)
                <option value="{{ $b }}" @selected(($barangay ?? '') === $b)>{{ $b }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:10px;">
          <a class="btn btn-soft" href="{{ route('anti-rabies-vaccinations.index') }}">Reset</a>
          <button class="btn" type="submit">Filter</button>
        </div>
      </form>

      {{-- =========================
          CHARTS (fixed sizing)
         ========================= --}}
      <div class="grid-charts" style="margin-bottom:14px;">
        <div class="card" style="padding:14px;">
          <div style="font-weight:900; font-size:13px; margin-bottom:8px;">Vaccinations by Year</div>
          <div class="chartbox"><canvas id="chartYear"></canvas></div>
        </div>

        <div class="card" style="padding:14px;">
          <div style="font-weight:900; font-size:13px; margin-bottom:8px;">Pet Type (Dog vs Cat)</div>
          <div class="chartbox"><canvas id="chartPetType"></canvas></div>
        </div>

        <div class="card" style="padding:14px;">
          <div style="font-weight:900; font-size:13px; margin-bottom:8px;">Top Barangays (Top 10)</div>
          <div class="chartbox"><canvas id="chartBarangay"></canvas></div>
        </div>

        <div class="card" style="padding:14px;">
          <div style="font-weight:900; font-size:13px; margin-bottom:8px;">Top Breeds (Top 10)</div>
          <div class="chartbox"><canvas id="chartBreed"></canvas></div>
        </div>

        <div class="card" style="padding:14px;">
          <div style="font-weight:900; font-size:13px; margin-bottom:8px;">
            Monthly Trend ({{ $chartYear ?? now()->year }})
          </div>
          <div class="chartbox"><canvas id="chartMonthly"></canvas></div>
        </div>

        <div class="card" style="padding:14px;">
          <div style="font-weight:900; font-size:13px; margin-bottom:8px;">Owner Age Groups</div>
          <div class="chartbox"><canvas id="chartAge"></canvas></div>
        </div>
      </div>

      {{-- =========================
          TABLE (✅ Removed Year column)
         ========================= --}}
      <div style="overflow:auto;">
        <table class="display js-datatable" style="width:100%;">
          <thead>
            <tr>
              <th>Owner</th>
              <th>Barangay</th>
              <th>Birthday</th>
              <th>Pet Type</th>
              <th>Pet</th>
              <th>Breed</th>
              <th>Color</th>
              <th>Vaccination Date</th>
              <th style="text-align:right;">Actions</th>
            </tr>
          </thead>

          <tbody>
            @forelse($records as $r)
              <tr>
                <td>{{ $r->owner_name }}</td>
                <td>{{ $r->barangay }}</td>
                <td>{{ optional($r->birthday)->format('Y-m-d') }}</td>
                <td>{{ $r->pet_type }}</td>
                <td>{{ $r->pet_name }}</td>
                <td>{{ $r->pet_breed }}</td>
                <td>{{ $r->pet_color }}</td>
                <td>{{ optional($r->vaccination_date)->format('Y-m-d') }}</td>
                <td style="text-align:right; white-space:nowrap;">
                  <a class="btn btn-soft" href="{{ route('anti-rabies-vaccinations.edit', $r->id) }}">Edit</a>

                  <form method="POST" action="{{ route('anti-rabies-vaccinations.destroy', $r->id) }}" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger" type="submit" onclick="return confirm('Delete this record?')">
                      Delete
                    </button>
                  </form>
                </td>
              </tr>
            @empty
              {{-- ✅ DO NOT use colspan with DataTables: keep 9 TDs --}}
              <tr>
                <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                <td style="padding:16px; color: var(--muted); text-align:right;">No records found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div style="margin-top:14px;">
        {{ $records->links() }}
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  {{-- Chart.js --}}
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

  <script>
    (function () {
      const yearLabels = @json($yearChartLabels ?? []);
      const yearData   = @json($yearChartData ?? []);

      const petTypeLabels = @json($petTypeChartLabels ?? ['Dog','Cat']);
      const petTypeData   = @json($petTypeChartData ?? [0,0]);

      const barangayLabels = @json($barangayChartLabels ?? []);
      const barangayData   = @json($barangayChartData ?? []);

      const breedLabels = @json($breedChartLabels ?? []);
      const breedData   = @json($breedChartData ?? []);

      const monthlyLabels = @json($monthlyChartLabels ?? []);
      const monthlyData   = @json($monthlyChartData ?? []);

      const ageLabels = @json($ageChartLabels ?? []);
      const ageData   = @json($ageChartData ?? []);

      function safeChart(id, type, data, options) {
        const el = document.getElementById(id);
        if (!el) return;
        return new Chart(el, { type, data, options: options || {} });
      }

      safeChart('chartYear', 'bar', {
        labels: yearLabels,
        datasets: [{ label: 'Vaccinations', data: yearData }]
      }, {
        responsive: true,
        maintainAspectRatio: false
      });

      safeChart('chartPetType', 'doughnut', {
        labels: petTypeLabels,
        datasets: [{ label: 'Count', data: petTypeData }]
      }, {
        responsive: true,
        maintainAspectRatio: false
      });

      safeChart('chartBarangay', 'bar', {
        labels: barangayLabels,
        datasets: [{ label: 'Vaccinations', data: barangayData }]
      }, {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y'
      });

      safeChart('chartBreed', 'bar', {
        labels: breedLabels,
        datasets: [{ label: 'Vaccinations', data: breedData }]
      }, {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y'
      });

      safeChart('chartMonthly', 'line', {
        labels: monthlyLabels,
        datasets: [{ label: 'Vaccinations', data: monthlyData, tension: 0.25 }]
      }, {
        responsive: true,
        maintainAspectRatio: false
      });

      safeChart('chartAge', 'bar', {
        labels: ageLabels,
        datasets: [{ label: 'Owners (count)', data: ageData }]
      }, {
        responsive: true,
        maintainAspectRatio: false
      });
    })();
  </script>
@endpush