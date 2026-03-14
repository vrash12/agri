{{-- resources/views/farmers/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Farmers')

@section('content')
  @php
    // Build a lightweight dataset for the map (current page/list only)
    $farmersMapData = collect($farmers)->map(function ($f) {
      return [
        'id' => $f->id,
        'last_name' => $f->last_name,
        'first_name' => $f->first_name,
        'middle_name' => $f->middle_name,
        'ffrs' => $f->ffrs,
        'date_of_birth' => $f->date_of_birth,
        'gender' => $f->gender,
        // IMPORTANT: maps.blade.php expects "location"
        'location' => $f->farm_location,
        'records_count' => (int) ($f->records_count ?? 0),
        'total_kgs' => (float) ($f->total_kgs ?? 0),
        'last_received' => $f->last_received,
      ];
    })->values();
  @endphp

  <div class="card farmers-card">
    <div class="card-header farmers-header">
      <div class="farmers-header-left">
        <h1 class="h1" style="margin:0;">Farmers Dashboard</h1>
        <p class="p" style="margin:30px 0 0;">
          Unique list of farmers based on recorded distributions.
        </p>

        {{-- GLOBAL STATISTICS --}}
        <div class="farmers-stats">
          <div class="stat-pill">
            Total Farmers:
            <span class="stat-num" style="color:#0b1220;">{{ number_format($totalFarmers) }}</span>
          </div>
          <div class="stat-pill pill-green">
            Total Kgs Distributed:
            <span class="stat-num">{{ number_format($totalKgs, 2) }}</span>
          </div>
          <div class="stat-pill stat-soft" title="Source: Rice Seed Distribution records">
            Data source: distributions
          </div>
        </div>
      </div>

      <div class="farmers-header-actions">
        <a class="btn farmers-add" href="{{ route('rice-seed-distributions.create') }}">
          + Add Distribution Record
        </a>
      </div>
    </div>

    <div class="farmers-body">

      {{-- ===== Charts Section ===== --}}
      <div class="charts-grid">
        <div class="chart-card">
          <h3 class="chart-title">Gender Distribution</h3>
          <div class="chart-wrapper">
            <canvas id="genderChart"></canvas>
          </div>
        </div>

        <div class="chart-card">
          <h3 class="chart-title">Top 10 Farm Locations</h3>
          <div class="chart-wrapper">
            <canvas id="locationChart"></canvas>
          </div>
        </div>
      </div>

      {{-- ===== Google Maps 3D Map Module ===== --}}
      @include('farmers.maps', [
        'farmersMapData' => $farmersMapData,
      ])

      <div class="table-shell">
        <table id="farmersTable" class="display farmers-table" style="width:100%;">
          <thead>
            <tr>
              <th style="width:60px;">No.</th>
              <th>Last Name</th>
              <th>First Name</th>
              <th>Middle Name</th>
              <th>FFRS No.</th>
              <th>Date of Birth</th>
              <th>Gender</th>
              <th>Location of Farm</th>
              <th>Total Records</th>
              <th>Total Kgs</th>
              <th>Last Received</th>
              <th class="th-action">Action</th>
            </tr>
          </thead>

          <tbody>
          @forelse($farmers as $f)
            @php
              $middle = $f->middle_name ?? '—';
              $ffrs = $f->ffrs ?? '—';
              $dob = $f->date_of_birth ?? '—';
              $gender = $f->gender ?? '—';
              $loc = $f->farm_location ?? '—';
              $records = (int) ($f->records_count ?? 0);
              $kgsRaw = (float) ($f->total_kgs ?? 0);
              $kgsDisplay = number_format($kgsRaw, 2);
              $last = $f->last_received ?? '—';
            @endphp

            <tr
              id="farmer-row-{{ $f->id }}"
              data-farmer-id="{{ $f->id }}"
              data-location="{{ e((string) $loc) }}"
            >
              <td class="td-no"></td>
              <td class="td-strong">{{ $f->last_name }}</td>
              <td>{{ $f->first_name }}</td>
              <td>{{ $middle }}</td>
              <td class="td-mono">{{ $ffrs }}</td>
              <td>{{ $dob }}</td>
              <td>
                <span class="badge {{ in_array(strtolower((string)$gender), ['male','m']) ? 'badge-green' : (in_array(strtolower((string)$gender), ['female','f']) ? 'badge-yellow' : 'badge-gray') }}">
                  {{ $gender }}
                </span>
              </td>
              <td>{{ $loc }}</td>
              <td class="td-strong" data-order="{{ $records }}">
                <span class="pill pill-gray">{{ $records }}</span>
              </td>
              <td class="td-strong" data-order="{{ $kgsRaw }}">
                <span class="pill pill-green">{{ $kgsDisplay }}</span>
              </td>
              <td data-order="{{ $last }}">{{ $last }}</td>
              <td class="td-action">
                <a class="btn btn-soft btn-sm" href="{{ route('farmers.records', $f->id) }}">
                  View Records
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="12" class="td-empty">
                No farmers found.
              </td>
            </tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
    // Chart data
    window.__genderStats   = @json($genderStats ?? []);
    window.__locationStats = @json($locationStats ?? []);

    // Map data (maps.blade.php will keep existing values if already set)
    window.__farmersMapData = window.__farmersMapData || @json($farmersMapData);
  </script>
@endsection

@push('styles')
<style>
  .farmers-header{ align-items: flex-start; }
  .farmers-header-left{ min-width: 260px; }
  .farmers-header-actions{
    display:flex;
    align-items:flex-start;
    justify-content:flex-end;
  }
  .farmers-stats{
    display:flex;
    gap: 10px;
    flex-wrap:wrap;
    margin-top: 10px;
  }
  .stat-pill{
    display:inline-flex;
    align-items:center;
    gap: 8px;
    padding: 7px 10px;
    border-radius: 999px;
    border: 1px solid var(--border);
    background: #fff;
    font-size: 12px;
    font-weight: 900;
    color: #0b1220;
  }
  .stat-soft{ background: rgba(2,6,23,.03); font-weight: 800; color: var(--muted); }
  .stat-num{ color: var(--green); font-weight: 900; }
  .farmers-body{ padding: 14px 16px 18px; }

  /* Charts Grid CSS */
  .charts-grid {
    display: grid;
    grid-template-columns: 1fr 2.5fr;
    gap: 14px;
    margin-bottom: 14px;
  }
  @media (max-width: 900px) {
    .charts-grid { grid-template-columns: 1fr; }
  }
  .chart-card {
    border: 1px solid var(--border);
    border-radius: 16px;
    background: #fff;
    padding: 16px;
    display: flex;
    flex-direction: column;
  }
  .chart-title {
    margin: 0 0 12px 0;
    font-size: 14px;
    font-weight: 900;
    color: #0b1220;
  }
  .chart-wrapper {
    position: relative;
    height: 220px;
    width: 100%;
  }

  .table-shell{
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    background: #fff;
  }
  .dt-top, .dt-bottom{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap: 12px;
    flex-wrap: wrap;
    padding: 10px 4px;
  }
  .dt-top{ padding-top: 0; }
  .dt-bottom{ padding-bottom: 0; }
  .dataTables_filter label, .dataTables_length label{
    display:flex; align-items:center; gap: 10px; font-weight: 900; color:#0b1220;
  }
  .dataTables_filter input{ min-width: 260px; }
  @media (max-width: 700px){
    .dataTables_filter input{ min-width: 180px; }
  }
  table.farmers-table thead th{
    background: #f8fafc; font-weight: 900; border-bottom: 1px solid var(--border);
  }
  table.farmers-table tbody tr:hover td{ background: rgba(34,197,94,.04); }
  .td-no{ font-weight: 900; }
  .td-strong{ font-weight: 900; }
  .td-mono{
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 12px; color:#0b1220;
  }
  .th-action, .td-action{ text-align:right; white-space: nowrap; }
  .btn-sm{ padding: 8px 10px; border-radius: 12px; box-shadow: none; }
  .badge{
    display:inline-flex; align-items:center; padding: 4px 10px; border-radius: 999px;
    border: 1px solid var(--border); font-size: 12px; font-weight: 900;
    text-transform: uppercase; letter-spacing: .2px;
  }
  .badge-green{ border-color: rgba(34,197,94,.25); background: rgba(34,197,94,.10); color: var(--green); }
  .badge-yellow{ border-color: rgba(250,204,21,.35); background: rgba(250,204,21,.18); color:#854d0e; }
  .badge-gray{ background: rgba(2,6,23,.03); color: var(--muted); }
  .pill{
    display:inline-flex; align-items:center; justify-content:center; padding: 4px 10px;
    border-radius: 999px; border: 1px solid var(--border); font-weight: 900; font-size: 12px;
  }
  .pill-green{ border-color: rgba(34,197,94,.25); background: rgba(34,197,94,.10); color: var(--green); }
  .pill-gray{ background: rgba(2,6,23,.03); color: #0b1220; }
  .td-empty{ padding: 18px !important; color: var(--muted); }
  .row-highlight td{ background: rgba(59,130,246,.10) !important; }
</style>
@endpush

@push('scripts')
{{-- Include Chart.js --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
  $(function () {
    // --------------------------------------------------------------
    // 1) Charts
    // --------------------------------------------------------------
    var genderCtx = document.getElementById('genderChart');
    if (genderCtx && window.__genderStats && Object.keys(window.__genderStats).length > 0) {
      new Chart(genderCtx, {
        type: 'doughnut',
        data: {
          labels: Object.keys(window.__genderStats),
          datasets: [{
            data: Object.values(window.__genderStats),
            backgroundColor: ['#22c55e', '#eab308', '#94a3b8', '#3b82f6'],
            borderWidth: 2,
            borderColor: '#ffffff',
            hoverOffset: 4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'right', labels: { font: { size: 11, family: 'ui-sans-serif, system-ui' } } }
          },
          cutout: '70%'
        }
      });
    }

    var locationCtx = document.getElementById('locationChart');
    if (locationCtx && window.__locationStats && Object.keys(window.__locationStats).length > 0) {
      new Chart(locationCtx, {
        type: 'bar',
        data: {
          labels: Object.keys(window.__locationStats),
          datasets: [{
            label: 'Total Farmers',
            data: Object.values(window.__locationStats),
            backgroundColor: 'rgba(34,197,94,0.85)',
            borderRadius: 4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            y: { beginAtZero: true, ticks: { precision: 0 } },
            x: { ticks: { autoSkip: false, maxRotation: 45, minRotation: 45, font: { size: 10 } } }
          }
        }
      });
    }

    // --------------------------------------------------------------
    // 2) DataTable
    // --------------------------------------------------------------
    var already = $.fn.DataTable.isDataTable('#farmersTable');
    var table = already
      ? $('#farmersTable').DataTable()
      : $('#farmersTable').DataTable({
          pageLength: 10,
          lengthMenu: [10, 25, 50, 100],
          order: [[1, 'asc']],
          autoWidth: false,
          deferRender: true,
          columnDefs: [
            { orderable: false, targets: [0, 11] },
            { searchable: false, targets: [0, 11] }
          ],
          dom: '<"dt-top"lf>rt<"dt-bottom"ip><"clear">',
          language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ farmers",
            infoEmpty: "Showing 0 to 0 of 0 farmers",
            zeroRecords: "No matching farmers found"
          }
        });

    // Row numbering
    table.on('order.dt search.dt draw.dt', function () {
      var info = table.page.info();
      table.column(0, { search: 'applied', order: 'applied' }).nodes().each(function (cell, i) {
        cell.innerHTML = '<span style="font-weight:900;">' + (info.start + i + 1) + '</span>';
      });
    }).draw();

    // --------------------------------------------------------------
    // 3) FIX: Row click -> open farmer on the 3D map (delegated on TABLE)
    //    (DataTables may redraw/replace tbody; binding on tbody can break.)
    // --------------------------------------------------------------
    $('#farmersTable')
      .off('click.__rowToMap')
      .on('click.__rowToMap', 'tbody tr', function (e) {
        // Don't hijack clicks on buttons/links/inputs inside the row
        if ($(e.target).closest('a,button,input,select,textarea,label').length) return;

        var id = this && this.dataset ? this.dataset.farmerId : null;
        if (!id) return;

        if (typeof window.__openFarmer3d === 'function') {
          window.__openFarmer3d(String(id));
        } else if (typeof window.__mapToast === 'function') {
          window.__mapToast('Map is still loading… try again in a moment.', 'warn');
        } else {
          console.warn('Map not ready yet: __openFarmer3d not defined.');
        }
      });
  });
</script>
@endpush