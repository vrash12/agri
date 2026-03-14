{{-- resources/views/rice_seed_distributions/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Rice Seed Distributions')

@section('content')
<style>
  .rsd-card { border-radius: 16px; overflow: hidden; }
  .rsd-header {
    display:flex; justify-content:space-between; align-items:flex-start; gap:16px;
    padding: 16px;
    background: linear-gradient(180deg, rgba(13,110,253,.06), rgba(13,110,253,0));
    border-bottom: 1px solid rgba(0,0,0,.06);
  }
  .rsd-title { margin:0; font-size: 1.25rem; font-weight: 800; }
  .rsd-sub { margin-top:.5rem; display:flex; flex-wrap:wrap; gap:.5rem; }
  .rsd-pill{
    display:inline-flex; gap:.35rem; align-items:center;
    padding:.35rem .6rem; border-radius:999px;
    border:1px solid rgba(0,0,0,.08);
    background: rgba(255,255,255,.80);
    font-size: .85rem;
  }
  .rsd-pill-soft { background: rgba(25,135,84,.06); border-color: rgba(25,135,84,.16); }
  .rsd-header-actions { display:flex; flex-wrap:wrap; gap:.5rem; }
  .rsd-btn { border-radius: 12px; }

  /* Charts Grid */
  .rsd-grid{
    display:grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 12px;
    margin: 12px 0 0;
  }
  .rsd-chart-card{
    grid-column: span 12;
    border:1px solid rgba(0,0,0,.08);
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 8px 20px rgba(0,0,0,.04);
    overflow: hidden;
  }
  /* 2 columns on desktops */
  @media (min-width: 992px){
    .rsd-chart-card{ grid-column: span 6; }
  }
  /* 3 columns on large screens to fit 9 charts nicely */
  @media (min-width: 1400px){
    .rsd-chart-card{ grid-column: span 4; }
  }
  
  .rsd-chart-head{
    padding: 12px 14px;
    border-bottom:1px solid rgba(0,0,0,.06);
    background: rgba(0,0,0,.015);
  }
  .rsd-chart-title{ font-weight: 800; }
  .rsd-chart-sub{ font-size:.85rem; color: rgba(0,0,0,.55); }
  .rsd-chart-body{
    padding: 10px 14px 14px;
    height: 260px;
  }
  .rsd-chart-body canvas { width: 100% !important; height: 100% !important; }

  /* Table */
  .rsd-table-wrap{
    margin-top: 14px;
    border:1px solid rgba(0,0,0,.08);
    border-radius: 16px;
    overflow:auto;
    box-shadow: 0 8px 20px rgba(0,0,0,.04);
  }
  table.rsd-table{
    width:100%;
    margin:0;
    min-width: 2100px;
    font-size: .88rem;
    border-collapse: separate;
    border-spacing: 0;
  }
  .rsd-table thead th{
    position: sticky;
    top: 0;
    z-index: 3;
    background: #f8f9fa;
    border-bottom: 1px solid rgba(0,0,0,.12);
    padding: 10px 10px;
    white-space: nowrap;
  }
  .rsd-table tbody td{
    border-bottom: 1px solid rgba(0,0,0,.06);
    padding: 10px 10px;
    vertical-align: middle;
  }
  
  /* Solid Backgrounds for Rows */
  .rsd-table tbody tr { background-color: #ffffff; }
  .rsd-table tbody tr:nth-child(odd) { background-color: #fafafa; }
  .rsd-table tbody tr:hover td { background-color: #f0f5ff; }

  /* Sticky first column (Actions) with solid colors to prevent overlap */
  .rsd-sticky-col{
    position: sticky;
    left: 0;
    z-index: 2;
    background-color: #ffffff; 
    border-right: 2px solid rgba(0,0,0,.08);
  }
  /* Match row backgrounds for the sticky column */
  .rsd-table tbody tr:nth-child(odd) .rsd-sticky-col { background-color: #fafafa; }
  .rsd-table tbody tr:hover .rsd-sticky-col { background-color: #f0f5ff; }
  
  /* Elevate top-left header */
  .rsd-table thead th.rsd-sticky-col {
    z-index: 4; 
    background-color: #f8f9fa;
  }

  .rsd-col-actions{
    width: 170px;
    min-width: 170px;
    max-width: 170px;
  }
  .rsd-actions{ display:flex; gap:6px; align-items:center; }
  .rsd-actions form{ margin:0; }

  .rsd-cell{
    max-width: 220px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .rsd-nowrap{ white-space: nowrap; }

  .rsd-yn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width: 28px;
    padding: 2px 8px;
    border-radius: 999px;
    font-weight: 800;
    font-size: .78rem;
    border: 1px solid rgba(0,0,0,.08);
  }
  .rsd-yes{ background: rgba(25,135,84,.12); color: #146c43; border-color: rgba(25,135,84,.20); }
  .rsd-no { background: rgba(220,53,69,.10); color: #b02a37; border-color: rgba(220,53,69,.20); }

  /* Clean Pagination Design */
  .rsd-pagination-container {
    margin-top: 1.25rem;
    padding: 1rem 1.5rem;
    background: #fff;
    border: 1px solid rgba(0,0,0,.08);
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,.02);
  }
  .rsd-pagination-container nav {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
  }
  .rsd-pagination-container p {
    margin: 0 !important;
    color: #6c757d;
    font-size: 0.9rem;
  }
  .rsd-pagination-container div.hidden.sm\:flex-1 {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
  }
  .rsd-pagination-container a, 
  .rsd-pagination-container span[aria-disabled="true"] {
    padding: 0.4rem 0.85rem;
    border-radius: 8px;
    border: 1px solid rgba(0,0,0,.1);
    background: #fff;
    color: #0d6efd;
    text-decoration: none;
    transition: all 0.2s;
    margin: 0 0.15rem;
    display: inline-flex;
    align-items: center;
  }
  .rsd-pagination-container a:hover {
    background: #f0f5ff;
    border-color: rgba(13,110,253,.3);
  }
  .rsd-pagination-container span[aria-current="page"] > span {
    background: #0d6efd;
    color: #fff;
    border-color: #0d6efd;
    padding: 0.4rem 0.85rem;
    border-radius: 8px;
    margin: 0 0.15rem;
    display: inline-flex;
  }
  .rsd-pagination-container svg { width: 1.25rem; height: 1.25rem; }
</style>

<div class="card rsd-card">
  <div class="rsd-header">
    <div>
      <h1 class="rsd-title">Rice Seed Distributions</h1>

      <div class="rsd-sub">
        <span class="rsd-pill">
          Records: <strong>{{ number_format($totalRecords ?? 0) }}</strong>
        </span>
        <span class="rsd-pill rsd-pill-soft">
          Total Kgs: <strong>{{ number_format($totalKgs ?? 0, 2) }}</strong>
        </span>

  
      </div>
    </div>

    <div class="rsd-header-actions">
      <a class="btn btn-primary rsd-btn" href="{{ route('rice-seed-distributions.create') }}">+ Add Record</a>
      <a class="btn btn-outline-secondary rsd-btn" href="{{ route('rice-seed-distributions.import.form') }}">Import Excel</a>
      <a class="btn btn-outline-success rsd-btn" href="{{ route('rice-seed-distributions.export', request()->query()) }}">Export CSV</a>
    </div>
  </div>

  <div class="card-body">
    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- GRAPHS --}}
    <div class="rsd-grid">

      <div class="rsd-chart-card">
        <div class="rsd-chart-head">
          <div class="rsd-chart-title">Top Locations</div>
          <div class="rsd-chart-sub">Top 10 by total kgs</div>
        </div>
        <div class="rsd-chart-body"><canvas id="chartTopLoc"></canvas></div>
      </div>

      <div class="rsd-chart-card">
        <div class="rsd-chart-head">
          <div class="rsd-chart-title">Area by Municipality</div>
          <div class="rsd-chart-sub">Total farm area (ha)</div>
        </div>
        <div class="rsd-chart-body"><canvas id="chartAreaMun"></canvas></div>
      </div>

      <div class="rsd-chart-card">
        <div class="rsd-chart-head">
          <div class="rsd-chart-title">Gender Distribution</div>
          <div class="rsd-chart-sub">Count of recipients</div>
        </div>
        <div class="rsd-chart-body"><canvas id="chartGender"></canvas></div>
      </div>

  

      <div class="rsd-chart-card">
        <div class="rsd-chart-head">
          <div class="rsd-chart-title">Age Groups</div>
          <div class="rsd-chart-sub">Distribution by age bracket</div>
        </div>
        <div class="rsd-chart-body"><canvas id="chartAge"></canvas></div>
      </div>

      <div class="rsd-chart-card">
        <div class="rsd-chart-head">
          <div class="rsd-chart-title">Top Seed Varieties (Claimed)</div>
          <div class="rsd-chart-sub">Top 10 claimed by total kgs</div>
        </div>
        <div class="rsd-chart-body"><canvas id="chartSeedVariety"></canvas></div>
      </div>

      <div class="rsd-chart-card">
        <div class="rsd-chart-head">
          <div class="rsd-chart-title">Crop Establishment</div>
          <div class="rsd-chart-sub">Direct vs Transplanted methods</div>
        </div>
        <div class="rsd-chart-body"><canvas id="chartCropEst"></canvas></div>
      </div>

      <div class="rsd-chart-card">
        <div class="rsd-chart-head">
          <div class="rsd-chart-title">Top Yielding Varieties (Planted)</div>
          <div class="rsd-chart-sub">Top 10 by total production bags</div>
        </div>
        <div class="rsd-chart-body"><canvas id="chartYieldVariety"></canvas></div>
      </div>

      <div class="rsd-chart-card">
        <div class="rsd-chart-head">
          <div class="rsd-chart-title">Seed Class Distribution</div>
          <div class="rsd-chart-sub">Breakdown of seed classes</div>
        </div>
        <div class="rsd-chart-body"><canvas id="chartSeedClass"></canvas></div>
      </div>

    </div>

    {{-- TABLE --}}
    <div class="rsd-table-wrap">
      <table class="rsd-table">
        <thead>
          <tr>
            <th class="rsd-sticky-col rsd-col-actions">Actions</th>

            <th>Date Received</th>
            <th class="text-end">Kgs</th>

            <th>FFRS / RSBSA</th>
            <th>Last</th>
            <th>First</th>
            <th>Middle</th>
            <th>Ext</th>

            <th>Gender</th>
            <th>Birthdate</th>
            <th>Contact #</th>

            <th>Farm Location</th>
            <th>Province</th>
            <th>Municipality</th>
            <th class="text-end">Farm Area (ha)</th>

            <th>Eco-System</th>
            <th>Eco-System Source</th>

            <th>Seed Variety Claimed</th>
            <th class="text-end">Claimed Area</th>
            <th class="text-end">Claimed Seeds</th>
            <th>Lot Series</th>
            <th>Crop Establishment</th>
            <th>Date of Sowing</th>

            <th class="text-end">Avg Wt/Bag</th>
            <th class="text-end">Total Prod</th>
            <th class="text-end">Avg Area Harv</th>
            <th>Seed Variety Planted</th>
            <th>Seed Class</th>

            <th class="text-center">ARB</th>
            <th class="text-center">4Ps</th>
            <th class="text-center">IP</th>
            <th class="text-center">PWD</th>
            <th class="text-center">SC</th>
            <th class="text-center">OFW</th>
          </tr>
        </thead>

        <tbody>
          @forelse($records as $r)
            <tr>
              <td class="rsd-sticky-col rsd-col-actions">
                <div class="rsd-actions">
                  <a class="btn btn-sm btn-outline-primary" href="{{ route('rice-seed-distributions.edit', $r) }}">Edit</a>
                  <form action="{{ route('rice-seed-distributions.destroy', $r) }}" method="POST" onsubmit="return confirm('Delete this record?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                  </form>
                </div>
              </td>

              <td class="rsd-nowrap">{{ optional($r->date_received)->format('Y-m-d') }}</td>
              <td class="text-end rsd-nowrap">{{ number_format((float)$r->kgs_received, 2) }}</td>

              <td class="rsd-nowrap">{{ $r->ffrs }}</td>

              <td class="rsd-cell" title="{{ $r->last_name }}">{{ $r->last_name }}</td>
              <td class="rsd-cell" title="{{ $r->first_name }}">{{ $r->first_name }}</td>
              <td class="rsd-cell" title="{{ $r->middle_name }}">{{ $r->middle_name }}</td>
              <td class="rsd-nowrap">{{ $r->ext_name }}</td>

              <td class="rsd-nowrap">{{ $r->gender ?: '—' }}</td>
              <td class="rsd-nowrap">{{ optional($r->date_of_birth)->format('Y-m-d') }}</td>
              <td class="rsd-nowrap">{{ $r->contact_number }}</td>

              <td class="rsd-cell" title="{{ $r->farm_location }}">{{ $r->farm_location }}</td>
              <td class="rsd-cell" title="{{ $r->farm_province }}">{{ $r->farm_province }}</td>
              <td class="rsd-cell" title="{{ $r->farm_municipality }}">{{ $r->farm_municipality }}</td>
              <td class="text-end rsd-nowrap">{{ $r->farm_area_ha !== null ? number_format((float)$r->farm_area_ha, 2) : '' }}</td>

              <td class="rsd-cell" title="{{ $r->ecosystem }}">{{ $r->ecosystem }}</td>
              <td class="rsd-cell" title="{{ $r->ecosystem_source }}">{{ $r->ecosystem_source }}</td>

              <td class="rsd-cell" title="{{ $r->seed_variety_claimed }}">{{ $r->seed_variety_claimed }}</td>
              <td class="text-end rsd-nowrap">{{ $r->claimed_area_ha !== null ? number_format((float)$r->claimed_area_ha, 2) : '' }}</td>
              <td class="text-end rsd-nowrap">{{ $r->claimed_seeds_kg !== null ? number_format((float)$r->claimed_seeds_kg, 2) : '' }}</td>
              <td class="rsd-cell" title="{{ $r->lot_series }}">{{ $r->lot_series }}</td>
              <td class="rsd-nowrap">{{ $r->crop_establishment }}</td>
              <td class="rsd-cell" title="{{ $r->date_of_sowing_label }}">{{ $r->date_of_sowing_label }}</td>

              <td class="text-end rsd-nowrap">{{ $r->avg_weight_per_bag_kg }}</td>
              <td class="text-end rsd-nowrap">{{ $r->total_production_bags }}</td>
              <td class="text-end rsd-nowrap">{{ $r->avg_area_harvested_ha !== null ? number_format((float)$r->avg_area_harvested_ha, 2) : '' }}</td>
              <td class="rsd-cell" title="{{ $r->seed_variety_planted }}">{{ $r->seed_variety_planted }}</td>
              <td class="rsd-nowrap">{{ $r->seed_class }}</td>

              <td class="text-center">
                @if($r->is_arb) <span class="rsd-yn rsd-yes">Y</span> @else <span class="rsd-yn rsd-no">N</span> @endif
              </td>
              <td class="text-center">
                @if($r->is_4ps) <span class="rsd-yn rsd-yes">Y</span> @else <span class="rsd-yn rsd-no">N</span> @endif
              </td>
              <td class="text-center">
                @if($r->is_ip) <span class="rsd-yn rsd-yes">Y</span> @else <span class="rsd-yn rsd-no">N</span> @endif
              </td>
              <td class="text-center">
                @if($r->is_pwd) <span class="rsd-yn rsd-yes">Y</span> @else <span class="rsd-yn rsd-no">N</span> @endif
              </td>
              <td class="text-center">
                @if($r->is_sc) <span class="rsd-yn rsd-yes">Y</span> @else <span class="rsd-yn rsd-no">N</span> @endif
              </td>
              <td class="text-center">
                @if($r->is_ofw) <span class="rsd-yn rsd-yes">Y</span> @else <span class="rsd-yn rsd-no">N</span> @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="34" class="text-center text-muted" style="padding:24px;">No records found.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    {{-- WRAPPED PAGINATION FOR CLEAN DESIGN --}}
    <div class="rsd-pagination-container">
      {{ $records->links() }}
    </div>
  </div>
</div>

{{-- Use Chart.js UMD build for compatibility --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
  // Ensure charts is an object (not array)
  var charts = @json($charts ?? (object)[]);

  function arr(v){ return (v && v.length) ? v : []; }

  function makeBar(ctx, labels, values, label){
    return new Chart(ctx, {
      type: 'bar',
      data: { labels: labels, datasets: [{ label: label, data: values, backgroundColor: '#0d6efd' }] },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: true } }
      }
    });
  }

  // Top Locations (horizontal bar)
  if (arr(charts.toploc_labels).length && arr(charts.toploc_values).length) {
    new Chart(document.getElementById('chartTopLoc'), {
      type: 'bar',
      data: {
        labels: charts.toploc_labels,
        datasets: [{ label: 'Total Kgs', data: charts.toploc_values, backgroundColor: '#ffc107' }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        scales: { x: { beginAtZero: true } }
      }
    });
  }

  // Gender (doughnut)
  if (arr(charts.gender_labels).length && arr(charts.gender_values).length) {
    new Chart(document.getElementById('chartGender'), {
      type: 'doughnut',
      data: {
        labels: charts.gender_labels,
        datasets: [{ label: 'Count', data: charts.gender_values }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false
      }
    });
  }

  // Eligibility (vertical bar)
  if (arr(charts.elig_labels).length && arr(charts.elig_values).length) {
    makeBar(document.getElementById('chartElig'), charts.elig_labels, charts.elig_values, 'Count');
  }

  // Age Groups (vertical bar)
  if (arr(charts.age_labels).length && arr(charts.age_values).length) {
    makeBar(document.getElementById('chartAge'), charts.age_labels, charts.age_values, 'Count');
  }

  // Top Seed Varieties Claimed (horizontal bar)
  if (arr(charts.seed_variety_labels).length && arr(charts.seed_variety_values).length) {
    new Chart(document.getElementById('chartSeedVariety'), {
      type: 'bar',
      data: {
        labels: charts.seed_variety_labels,
        datasets: [{ label: 'Total Kgs', data: charts.seed_variety_values, backgroundColor: '#20c997' }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        scales: { x: { beginAtZero: true } }
      }
    });
  }

  // Crop Establishment (doughnut)
  if (arr(charts.crop_est_labels).length && arr(charts.crop_est_values).length) {
    new Chart(document.getElementById('chartCropEst'), {
      type: 'doughnut',
      data: {
        labels: charts.crop_est_labels,
        datasets: [{ label: 'Count', data: charts.crop_est_values }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false
      }
    });
  }

  // NEW: Top Yielding Planted Varieties (horizontal bar)
  if (arr(charts.yield_variety_labels).length && arr(charts.yield_variety_values).length) {
    new Chart(document.getElementById('chartYieldVariety'), {
      type: 'bar',
      data: {
        labels: charts.yield_variety_labels,
        datasets: [{ label: 'Total Production Bags', data: charts.yield_variety_values, backgroundColor: '#6f42c1' }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        scales: { x: { beginAtZero: true } }
      }
    });
  }

  // NEW: Seed Class Distribution (doughnut)
  if (arr(charts.seed_class_labels).length && arr(charts.seed_class_values).length) {
    new Chart(document.getElementById('chartSeedClass'), {
      type: 'doughnut',
      data: {
        labels: charts.seed_class_labels,
        datasets: [{ label: 'Count', data: charts.seed_class_values }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false
      }
    });
  }

  // NEW: Area by Municipality (horizontal bar)
  if (arr(charts.area_mun_labels).length && arr(charts.area_mun_values).length) {
    new Chart(document.getElementById('chartAreaMun'), {
      type: 'bar',
      data: {
        labels: charts.area_mun_labels,
        datasets: [{ label: 'Total Area (ha)', data: charts.area_mun_values, backgroundColor: '#fd7e14' }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        scales: { x: { beginAtZero: true } }
      }
    });
  }
</script>
@endsection