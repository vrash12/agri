{{-- resources/views/farmers/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Farmers')

@section('content')
  @php
  $farmersMapData = collect($farmers->items())->map(function ($f) {
    return [
      'id' => $f->id,
      'last_name' => $f->last_name,
      'first_name' => $f->first_name,
      'middle_name' => $f->middle_name,
      'ffrs' => $f->ffrs,
      'date_of_birth' => $f->date_of_birth,
      'gender' => $f->gender,
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

    // Map data
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

    table.on('order.dt search.dt draw.dt', function () {
      var info = table.page.info();
      table.column(0, { search: 'applied', order: 'applied' }).nodes().each(function (cell, i) {
        cell.innerHTML = '<span style="font-weight:900;">' + (info.start + i + 1) + '</span>';
      });
    }).draw();

    // --------------------------------------------------------------
    // 3) Row click -> open farmer on the 3D map
    // --------------------------------------------------------------
    $('#farmersTable')
      .off('click.__rowToMap')
      .on('click.__rowToMap', 'tbody tr', function (e) {
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

@push('scripts')
<script>
  var GOOGLE_MAPS_API_KEY = window.__gmapsApiKey || "";
  var GOOGLE_MAPS_MAP_ID  = window.__gmapsMapId  || "YOUR_REAL_MAP_ID_HERE";

  (function (g) {
    var h, a, k,
      p = "The Google Maps JavaScript API",
      c = "google",
      l = "importLibrary",
      q = "__ib__",
      m = document,
      b = window;

    b = b[c] || (b[c] = {});
    var d = b.maps || (b.maps = {});
    var r = d.__libset || (d.__libset = {});
    var e = new URLSearchParams();

    function addLib(lib) { if (lib) r[lib] = true; }

    function u() {
      if (h) return h;
      h = new Promise(function (resolve, reject) {
        a = m.createElement("script");
        var libs = Object.keys(r).join(",");
        e.set("libraries", libs);

        for (k in g) {
          e.set(k.replace(/[A-Z]/g, function (t) { return "_" + t[0].toLowerCase(); }), g[k]);
        }

        e.set("callback", c + ".maps." + q);
        a.src = "https://maps." + c + "apis.com/maps/api/js?" + e.toString();
        d[q] = resolve;
        a.onerror = function () { reject(Error(p + " could not load.")); };

        var nonceEl = m.querySelector("script[nonce]");
        a.nonce = nonceEl ? nonceEl.nonce : "";

        m.head.appendChild(a);
      });

      return h;
    }

    if (!d[l]) {
      d[l] = function () {
        var args = arguments;
        addLib(args[0]);
        return u().then(function () {
          return d[l].apply(d, args);
        });
      };
    } else {
      console.warn(p + " only loads once. Ignoring:", g);
    }
  })({
    key: GOOGLE_MAPS_API_KEY,
    v: "beta",
    mapIds: GOOGLE_MAPS_MAP_ID
  });
</script>

<script>
  (function () {
    var toastTimer = null;

    function showToast(msg, kind) {
      var el = document.getElementById('mapToast');
      if (!el) return;
      el.className = 'map-toast is-show' + (kind ? (' is-' + kind) : '');
      el.textContent = msg;

      if (toastTimer) clearTimeout(toastTimer);
      toastTimer = setTimeout(function () {
        el.className = 'map-toast';
        el.textContent = '';
      }, 2600);
    }

    function setText(id, value) {
      var el = document.getElementById(id);
      if (el) el.textContent = (value == null || value === '') ? '—' : String(value);
    }

    function setHref(id, href) {
      var el = document.getElementById(id);
      if (el) el.setAttribute('href', href || '#');
    }

    window.__mapToast = showToast;
    window.__mapUiSetText = setText;
    window.__mapUiSetHref = setHref;

    function bindRowClickToMap() {
      if (!window.jQuery) return;
      $('#farmersTable')
        .off('click.__rowToMap')
        .on('click.__rowToMap', 'tbody tr', function (e) {
          if ($(e.target).closest('a,button,input,select,textarea,label').length) return;
          var id = this && this.dataset ? this.dataset.farmerId : null;
          if (!id) return;
          if (typeof window.__openFarmer3d === 'function') window.__openFarmer3d(String(id));
        });
    }

    function bindButtons() {
      var btnFit = document.getElementById('recenterMapBtn');
      if (btnFit) btnFit.addEventListener('click', function () {
        if (typeof window.__fit3dToVisibleMarkers === "function") window.__fit3dToVisibleMarkers();
      });

      var btnReset = document.getElementById('resetMapBtn');
      if (btnReset) btnReset.addEventListener('click', function () {
        if (typeof window.__reset3dMap === "function") window.__reset3dMap();
      });

      var btnClear = document.getElementById('clearSelectionBtn');
      if (btnClear) btnClear.addEventListener('click', function () {
        if (typeof window.__clearFarmerSelection === "function") window.__clearFarmerSelection();
      });

      var btnFocus = document.getElementById('focusSelectedBtn');
      if (btnFocus) btnFocus.addEventListener('click', function () {
        if (typeof window.__focusSelectedFarmer === "function") window.__focusSelectedFarmer();
      });

      var btnDownloadSelectedPlot = document.getElementById('downloadSelectedPlotBtn');
      if (btnDownloadSelectedPlot) btnDownloadSelectedPlot.addEventListener('click', function () {
        if (typeof window.__downloadCurrentPlotSheet === "function") window.__downloadCurrentPlotSheet();
      });

      var btnPrintSelectedPlot = document.getElementById('printSelectedPlotBtn');
      if (btnPrintSelectedPlot) btnPrintSelectedPlot.addEventListener('click', function () {
        if (typeof window.__printCurrentPlotSheet === "function") window.__printCurrentPlotSheet();
      });

      var tMarkers = document.getElementById('toggleMarkers');
      if (tMarkers) tMarkers.addEventListener('change', function () {
        if (typeof window.__applyMarkerVisibility === "function") window.__applyMarkerVisibility();
      });

      var tPlots = document.getElementById('togglePlots');
      if (tPlots) tPlots.addEventListener('change', function () {
        if (typeof window.__applyPlotVisibility === "function") window.__applyPlotVisibility();
        if (typeof window.__setPlotsLoadingEnabled === "function") window.__setPlotsLoadingEnabled(!!tPlots.checked);
      });
    }

    $(function () {
      bindRowClickToMap();
      bindButtons();

      initFarmersMap3D().catch(function (e) {
        console.error(e);
        var statusEl = document.getElementById('mapStatus');
        var statusSmall = document.getElementById('mapStatusSmall');
        if (statusEl) statusEl.textContent = '3D map failed to load.';
        if (statusSmall) statusSmall.textContent = 'Check your API key / Map ID.';
        showToast('3D map failed to load. Check API key and Map ID.', 'bad');
      });
    });
  })();

  async function initFarmersMap3D() {
    var statusEl = document.getElementById('mapStatus');
    var statusSmallEl = document.getElementById('mapStatusSmall');
    var progressBar = document.getElementById('mapProgressBar');
    var hintEl = document.getElementById('mapHint');
    var plotModeBadge = document.getElementById('plotModeBadge');
    var selectionChipEl = document.getElementById('mapSelectionChip');
    var selectionChipTextEl = document.getElementById('mapSelectionChipText');
    var workflowStepEl = document.getElementById('plotWorkflowStep');
    var workflowTextEl = document.getElementById('plotWorkflowText');
    var mapGeocodedPillEl = document.getElementById('mapGeocodedPill');
    var mapSelectedPillEl = document.getElementById('mapSelectedPill');
    var plotNameInputEl = document.getElementById('plotNameInput');

    var data = Array.isArray(window.__farmersMapData) ? window.__farmersMapData : [];
    var farmersById = new Map();
    for (var i = 0; i < data.length; i++) farmersById.set(String(data[i].id), data[i]);

    var DEFAULT_CENTER = { lat: 15.667267, lng: 120.624936 };
    var DEFAULT_RANGE = 45000;
    var PLOT_CONCURRENCY = 2;

    function setStatus(main, small) {
      if (statusEl) statusEl.textContent = main || '';
      if (statusSmallEl) statusSmallEl.textContent = small || '';
    }

    function setProgress(pct) {
      if (!progressBar) return;
      var v = Math.max(0, Math.min(100, pct || 0));
      progressBar.style.width = v.toFixed(0) + '%';
    }

    function toast(msg, kind) {
      if (typeof window.__mapToast === "function") window.__mapToast(msg, kind);
    }

    function csrfToken() {
      var el = document.querySelector('meta[name="csrf-token"]');
      return el ? el.getAttribute('content') : '';
    }

    function escapeHtml(str) {
      str = String(str == null ? "" : str);
      return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function escapeXml(str) {
      str = String(str == null ? "" : str);
      return str.replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
    }

    function safeFilename(str) {
      return String(str || 'plot')
        .trim()
        .replace(/[^\w\-]+/g, '_')
        .replace(/^_+|_+$/g, '') || 'plot';
    }

    function formatName(f) {
      var fn = (f.first_name || '').trim();
      var ln = (f.last_name || '').trim();
      var nm = (fn + ' ' + ln).trim();
      return nm || (f.last_name || 'Farmer');
    }

    function getFarmerGlyph(f) {
      var fn = String(f && f.first_name ? f.first_name : '').trim();
      var ln = String(f && f.last_name ? f.last_name : '').trim();
      var a = fn ? fn.charAt(0).toUpperCase() : '';
      var b = ln ? ln.charAt(0).toUpperCase() : '';
      return (a + b) || 'F';
    }

    function setWorkflow(stepText, bodyHtml) {
      if (workflowStepEl) workflowStepEl.textContent = stepText || '';
      if (workflowTextEl) workflowTextEl.innerHTML = bodyHtml || '';
    }

    function updateSelectionChip(f) {
      if (!selectionChipEl || !selectionChipTextEl) return;
      if (!f) {
        selectionChipEl.style.display = 'none';
        selectionChipTextEl.textContent = '';
        return;
      }
      selectionChipTextEl.textContent = formatName(f);
      selectionChipEl.style.display = '';
    }

    function updateSelectionPill(f) {
      if (!mapSelectedPillEl) return;
      mapSelectedPillEl.textContent = f ? ('Selected: ' + formatName(f)) : 'No selection';
    }

    function updateGeocodedPill(geocoded, total) {
      if (!mapGeocodedPillEl) return;
      mapGeocodedPillEl.textContent = geocoded + ' / ' + total + ' geocoded';
    }

    function setWorkflowFarmer(name) {
      window.__mapUiSetText && window.__mapUiSetText('workflowFarmer', name || '—');
    }

    function setWorkflowCorners(n) {
      window.__mapUiSetText && window.__mapUiSetText('workflowCorners', Number(n || 0));
    }

    function setWorkflowArea(ha) {
      window.__mapUiSetText && window.__mapUiSetText('workflowArea', (Number(ha || 0)).toFixed(2) + ' ha');
    }

    function syncWorkflowUi() {
      var f = selectedFarmerId ? (farmersById.get(String(selectedFarmerId)) || dataById.get(String(selectedFarmerId))) : null;

      if (!f && !plotMode) {
        setWorkflow('Step 1', 'Select a farmer first, then click <b>Plot Land</b>.');
        setWorkflowFarmer('—');
        setWorkflowCorners(0);
        setWorkflowArea(0);
        return;
      }

      if (f && !plotMode) {
        setWorkflow('Step 2', 'Click <b>Plot Land</b> to start. A starter rectangle will appear automatically around the selected farmer.');
        setWorkflowFarmer(formatName(f));
        setWorkflowCorners(0);
        setWorkflowArea(0);
        return;
      }

      if (plotMode && plotVertices.length) {
        if (selectedVertexIndex >= 0) {
          setWorkflow('Step 4', 'Corner <b>' + (selectedVertexIndex + 1) + '</b> is selected. Click the map to move it, or use <b>Arrow keys</b> for fine adjustment.');
        } else {
          setWorkflow('Step 3', 'Review the draft. Click a corner dot to adjust it, then press <b>Save</b> when ready.');
        }
      } else if (plotMode) {
        setWorkflow('Step 3', 'Draft mode is active. Use <b>New rectangle</b> if you want a fresh starter plot.');
      }

      setWorkflowFarmer(f ? formatName(f) : '—');
      setWorkflowCorners(plotVertices.length || 0);
      setWorkflowArea(estimateAreaHa(plotVertices));
    }

    function toLatLng(pos) {
      var lat = (typeof pos.lat === "function") ? pos.lat() : pos.lat;
      var lng = (typeof pos.lng === "function") ? pos.lng() : pos.lng;
      return { lat: lat, lng: lng };
    }

    function haversineMeters(a, b) {
      var R = 6371000;
      function toRad(x) { return (x * Math.PI) / 180; }
      var dLat = toRad(b.lat - a.lat);
      var dLng = toRad(b.lng - a.lng);
      var lat1 = toRad(a.lat);
      var lat2 = toRad(b.lat);
      var s = Math.pow(Math.sin(dLat / 2), 2) + Math.cos(lat1) * Math.cos(lat2) * Math.pow(Math.sin(dLng / 2), 2);
      return 2 * R * Math.asin(Math.sqrt(s));
    }

    function clamp(n, min, max) {
      return Math.max(min, Math.min(max, n));
    }

    function flyTo(lat, lng, range, durationMillis) {
      durationMillis = durationMillis == null ? 900 : durationMillis;
      map3d.flyCameraTo({
        endCamera: {
          center: { lat: lat, lng: lng, altitude: 200 },
          tilt: map3d.tilt || 67.5,
          heading: map3d.heading || 0,
          range: range
        },
        durationMillis: durationMillis
      });
    }

    function zoomToRing(ring, multiplier, minRange, maxRange) {
      if (!ring || ring.length < 3) return;
      var b = { minLat: Infinity, maxLat: -Infinity, minLng: Infinity, maxLng: -Infinity };
      extendBounds(b, ring);
      var centerLat = (b.minLat + b.maxLat) / 2;
      var centerLng = (b.minLng + b.maxLng) / 2;
      var diag = haversineMeters({ lat: b.minLat, lng: b.minLng }, { lat: b.maxLat, lng: b.maxLng });
      var range = clamp(diag * (multiplier || 4), minRange || 700, maxRange || 15000);
      flyTo(centerLat, centerLng, range, 900);
    }

    function highlightRow(id) {
      var highlighted = document.querySelectorAll('#farmersTable tbody tr.row-highlight');
      for (var i = 0; i < highlighted.length; i++) highlighted[i].classList.remove('row-highlight');

      var row = document.getElementById("farmer-row-" + id);
      if (row) {
        row.classList.add('row-highlight');
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    }

    function estimateAreaHa(points) {
      if (!points || points.length < 3) return 0;

      var lat0 = 0, lng0 = 0;
      for (var i = 0; i < points.length; i++) {
        lat0 += points[i].lat;
        lng0 += points[i].lng;
      }
      lat0 /= points.length;
      lng0 /= points.length;

      var R = 6371000;
      function toRad(x) { return x * Math.PI / 180; }
      var lat0r = toRad(lat0);

      var xy = [];
      for (var j = 0; j < points.length; j++) {
        var x = toRad(points[j].lng - lng0) * R * Math.cos(lat0r);
        var y = toRad(points[j].lat - lat0) * R;
        xy.push({ x: x, y: y });
      }

      var area = 0;
      for (var k = 0; k < xy.length; k++) {
        var p1 = xy[k];
        var p2 = xy[(k + 1) % xy.length];
        area += (p1.x * p2.y - p2.x * p1.y);
      }

      return (Math.abs(area) / 2) / 10000;
    }

    function hexToRgba(hex, alpha) {
      if (!hex) return "rgba(34,197,94," + alpha + ")";
      hex = String(hex).trim();

      if (/^#([0-9a-fA-F]{3})$/.test(hex)) {
        var r = parseInt(hex[1] + hex[1], 16);
        var g = parseInt(hex[2] + hex[2], 16);
        var b = parseInt(hex[3] + hex[3], 16);
        return "rgba(" + r + "," + g + "," + b + "," + alpha + ")";
      }

      if (/^#([0-9a-fA-F]{6})$/.test(hex)) {
        var r6 = parseInt(hex.slice(1,3), 16);
        var g6 = parseInt(hex.slice(3,5), 16);
        var b6 = parseInt(hex.slice(5,7), 16);
        return "rgba(" + r6 + "," + g6 + "," + b6 + "," + alpha + ")";
      }

      return "rgba(34,197,94," + alpha + ")";
    }

    function hexAlpha(hex, aHex) {
      hex = String(hex || '').trim();
      if (!hex) return '#22c55e' + aHex;
      if (hex[0] !== '#') hex = '#' + hex;
      if (/^#([0-9a-fA-F]{3})$/.test(hex)) {
        hex = '#' + hex[1] + hex[1] + hex[2] + hex[2] + hex[3] + hex[3];
      }
      if (/^#([0-9a-fA-F]{6})$/.test(hex)) return hex + aHex;
      if (/^#([0-9a-fA-F]{8})$/.test(hex)) return hex.slice(0, 7) + aHex;
      return '#22c55e' + aHex;
    }

    function normalizeHexColor(hex) {
      hex = String(hex || '').trim();
      if (!hex) return '#3b82f6';
      if (hex[0] !== '#') hex = '#' + hex;
      if (/^#([0-9a-fA-F]{3})$/.test(hex)) {
        return '#' + hex[1] + hex[1] + hex[2] + hex[2] + hex[3] + hex[3];
      }
      if (/^#([0-9a-fA-F]{6})$/.test(hex)) return hex;
      if (/^#([0-9a-fA-F]{8})$/.test(hex)) return hex.slice(0, 7);
      return '#3b82f6';
    }

    function hexToRgb(hex) {
      hex = normalizeHexColor(hex);
      return {
        r: parseInt(hex.slice(1, 3), 16),
        g: parseInt(hex.slice(3, 5), 16),
        b: parseInt(hex.slice(5, 7), 16)
      };
    }

    function normalizePolygonRing(ringRaw) {
      if (!ringRaw) return [];

      if (typeof ringRaw === "string") {
        var s = ringRaw.trim();
        if (!s) return [];
        try { ringRaw = JSON.parse(s); } catch(e) { return []; }
      }

      if (ringRaw && typeof ringRaw === "object" && !Array.isArray(ringRaw) && ringRaw.coordinates) {
        try {
          var coords = ringRaw.coordinates;
          if (Array.isArray(coords) && Array.isArray(coords[0])) {
            ringRaw = coords[0].map(function(pt){
              return Array.isArray(pt) ? { lng: pt[0], lat: pt[1] } : pt;
            });
          }
        } catch(e) { return []; }
      }

      if (!Array.isArray(ringRaw)) return [];

      var ring = [];
      for (var i = 0; i < ringRaw.length; i++) {
        var p = ringRaw[i];

        if (Array.isArray(p)) {
          if (p.length >= 2) {
            var a0 = parseFloat(p[0]), a1 = parseFloat(p[1]);
            if (isFinite(a0) && isFinite(a1)) {
              var latGuess = a0, lngGuess = a1;
              if (Math.abs(a0) > 90 && Math.abs(a1) <= 90) {
                lngGuess = a0;
                latGuess = a1;
              }
              ring.push({ lat: latGuess, lng: lngGuess });
            }
          }
          continue;
        }

        if (p && typeof p === "object") {
          var lat = (p.lat != null) ? parseFloat(p.lat) : ((p.latitude != null) ? parseFloat(p.latitude) : NaN);
          var lng = (p.lng != null) ? parseFloat(p.lng) : ((p.longitude != null) ? parseFloat(p.longitude) : NaN);
          if (isFinite(lat) && isFinite(lng)) ring.push({ lat: lat, lng: lng });
        }
      }

      ring = ring.filter(function(pt){
        return pt && isFinite(pt.lat) && isFinite(pt.lng);
      });

      if (ring.length < 3) return [];

      var f = ring[0];
      var l = ring[ring.length - 1];
      var eps = 1e-10;
      if (Math.abs(f.lat - l.lat) > eps || Math.abs(f.lng - l.lng) > eps) {
        ring.push({ lat: f.lat, lng: f.lng });
      }

      return ring;
    }

    function sameLatLng(a, b) {
      if (!a || !b) return false;
      return Math.abs(Number(a.lat) - Number(b.lat)) < 1e-10 &&
             Math.abs(Number(a.lng) - Number(b.lng)) < 1e-10;
    }

    function openRing(ring) {
      ring = Array.isArray(ring) ? ring.slice() : [];
      if (ring.length > 1 && sameLatLng(ring[0], ring[ring.length - 1])) {
        ring.pop();
      }
      return ring;
    }

    function ringCentroid(ring) {
      ring = openRing(ring);
      if (!ring.length) return { lat: 0, lng: 0 };

      var lat = 0;
      var lng = 0;
      for (var i = 0; i < ring.length; i++) {
        lat += Number(ring[i].lat || 0);
        lng += Number(ring[i].lng || 0);
      }

      return {
        lat: lat / ring.length,
        lng: lng / ring.length
      };
    }

    function midpointLatLng(a, b) {
      return {
        lat: (Number(a.lat) + Number(b.lat)) / 2,
        lng: (Number(a.lng) + Number(b.lng)) / 2
      };
    }

    function offsetPointOutward(pt, center, meters) {
      meters = Number(meters || 8);

      var dLat = Number(pt.lat) - Number(center.lat);
      var dLng = Number(pt.lng) - Number(center.lng);
      var len = Math.sqrt((dLat * dLat) + (dLng * dLng));

      if (!len) {
        return { lat: Number(pt.lat), lng: Number(pt.lng) };
      }

      var unitLat = dLat / len;
      var unitLng = dLng / len;

      var latPerMeter = 1 / 111320;
      var lngPerMeter = 1 / (111320 * Math.max(0.25, Math.cos(Number(center.lat) * Math.PI / 180)));

      return {
        lat: Number(pt.lat) + (unitLat * meters * latPerMeter),
        lng: Number(pt.lng) + (unitLng * meters * lngPerMeter)
      };
    }

    function createGuideMarker(position, opts) {
      opts = opts || {};

      var marker = new Marker3DInteractiveElement({
        altitudeMode: AltitudeMode.CLAMP_TO_GROUND,
        position: position,
        title: opts.title || '',
        sizePreserved: true,
        drawsWhenOccluded: true
      });

      marker.replaceChildren(new PinElement({
        scale: opts.scale == null ? 0.52 : opts.scale,
        background: opts.background || '#ef4444',
        borderColor: opts.borderColor || '#991b1b',
        glyphColor: opts.glyphColor || '#ffffff',
        glyphText: opts.glyphText || ''
      }));

      marker.zIndex = opts.zIndex == null ? 980 : opts.zIndex;
      return marker;
    }

    function attachGuideMarkers(markers) {
      if (!markers || !markers.length) return;
      for (var i = 0; i < markers.length; i++) {
        try {
          if (markers[i] && !markers[i].isConnected) map3d.append(markers[i]);
        } catch (e) {}
      }
    }

    function detachGuideMarkers(markers) {
      if (!markers || !markers.length) return;
      for (var i = 0; i < markers.length; i++) {
        try {
          if (markers[i] && markers[i].isConnected) map3d.removeChild(markers[i]);
        } catch (e) {}
      }
    }

    function buildGuideMarkersFromRing(ring, opts) {
      opts = opts || {};
      var pts = openRing(ring);
      var markers = [];

      if (pts.length < 2) return markers;

      var center = ringCentroid(pts);

      for (var i = 0; i < pts.length; i++) {
        var cornerPos = offsetPointOutward(pts[i], center, opts.cornerOffsetMeters || 8);

        var cornerMarker = createGuideMarker(cornerPos, {
          title: 'Corner guide ' + (i + 1),
          scale: opts.cornerScale == null ? 0.54 : opts.cornerScale,
          background: opts.cornerBackground || '#ef4444',
          borderColor: opts.cornerBorderColor || '#991b1b',
          glyphText: opts.cornerGlyphText || '',
          zIndex: 985
        });

        if (typeof opts.onCornerClick === 'function') {
          cornerMarker.addEventListener('gmp-click', (function (index) {
            return function (ev) {
              if (ev && ev.stopPropagation) ev.stopPropagation();
              opts.onCornerClick(index);
            };
          })(i));
        }

        markers.push(cornerMarker);

        var next = pts[(i + 1) % pts.length];
        var mid = midpointLatLng(pts[i], next);

        var midMarker = createGuideMarker(mid, {
          title: 'Side guide ' + (i + 1),
          scale: opts.midScale == null ? 0.44 : opts.midScale,
          background: opts.midBackground || '#dc2626',
          borderColor: opts.midBorderColor || '#7f1d1d',
          glyphText: opts.midGlyphText || '',
          zIndex: 970
        });

        markers.push(midMarker);
      }

      return markers;
    }

    function extendBounds(b, ring) {
      for (var i = 0; i < ring.length; i++) {
        var p = ring[i];
        if (!p) continue;
        b.minLat = Math.min(b.minLat, p.lat);
        b.maxLat = Math.max(b.maxLat, p.lat);
        b.minLng = Math.min(b.minLng, p.lng);
        b.maxLng = Math.max(b.maxLng, p.lng);
      }
    }

    function zoomToPlots(plots) {
      if (!plots || !plots.length) return;
      var b = { minLat: Infinity, maxLat: -Infinity, minLng: Infinity, maxLng: -Infinity };
      var any = false;

      for (var i = 0; i < plots.length; i++) {
        var ring = normalizePolygonRing(plots[i].polygon_json || plots[i].polygon || plots[i].polygonJson);
        if (!ring || ring.length < 3) continue;
        extendBounds(b, ring);
        any = true;
      }

      if (!any) return;

      var centerLat = (b.minLat + b.maxLat) / 2;
      var centerLng = (b.minLng + b.maxLng) / 2;
      var diag = haversineMeters({ lat: b.minLat, lng: b.minLng }, { lat: b.maxLat, lng: b.maxLng });
      var range = clamp(diag * 4.0, 700, 15000);
      flyTo(centerLat, centerLng, range, 900);
    }

    function setPlotModeUi(on) {
      var module = document.getElementById('farmersMapModule');
      if (!module) return;
      if (on) module.classList.add('is-plot-mode');
      else module.classList.remove('is-plot-mode');
      syncWorkflowUi();
    }

    var moduleEl = document.getElementById('farmersMapModule');
    var stageEl  = document.querySelector('#farmersMapModule .farmers-map-stage');
    var cursorEl = document.getElementById('plotCursor');

    function updateFakeCursor(e) {
      if (!moduleEl || !cursorEl || !stageEl) return;
      if (!moduleEl.classList.contains('is-plot-mode')) return;

      var r = stageEl.getBoundingClientRect();
      var x = e.clientX - r.left;
      var y = e.clientY - r.top;
      x = Math.max(0, Math.min(r.width, x));
      y = Math.max(0, Math.min(r.height, y));
      cursorEl.style.left = x + "px";
      cursorEl.style.top  = y + "px";
    }

    if (stageEl) {
      stageEl.addEventListener('pointermove', updateFakeCursor, true);
      stageEl.addEventListener('mousemove', updateFakeCursor, true);
      stageEl.addEventListener('mouseenter', function (e) { updateFakeCursor(e); }, true);
      stageEl.addEventListener('mouseleave', function () {
        if (!cursorEl) return;
        cursorEl.style.left = "-9999px";
        cursorEl.style.top  = "-9999px";
      }, true);
    }

    if (!GOOGLE_MAPS_API_KEY || !GOOGLE_MAPS_MAP_ID || GOOGLE_MAPS_MAP_ID === "YOUR_REAL_MAP_ID_HERE") {
      setStatus('Map error.', 'Check API key or Map ID.');
      return;
    }

    var maps3d = await google.maps.importLibrary("maps3d");
    var markerLib = await google.maps.importLibrary("marker");

    var Map3DElement = maps3d.Map3DElement;
    var MapMode = maps3d.MapMode;
    var AltitudeMode = maps3d.AltitudeMode;
    var Marker3DInteractiveElement = maps3d.Marker3DInteractiveElement;
    var PopoverElement = maps3d.PopoverElement;
    var Polyline3DElement = maps3d.Polyline3DElement;
    var Polygon3DElement = maps3d.Polygon3DElement;
    var Polyline3DInteractiveElement = maps3d.Polyline3DInteractiveElement;
    var Polygon3DInteractiveElement = maps3d.Polygon3DInteractiveElement;
    var PinElement = markerLib.PinElement;

    var host = document.getElementById("farmersMap");
    if (!host) return;
    host.innerHTML = "";

    var map3d = new Map3DElement({
      mapId: GOOGLE_MAPS_MAP_ID,
      center: { lat: DEFAULT_CENTER.lat, lng: DEFAULT_CENTER.lng, altitude: 220 },
      tilt: 67.5,
      heading: 15,
      range: DEFAULT_RANGE,
      mode: MapMode.HYBRID,
      gestureHandling: "GREEDY"
    });
    host.appendChild(map3d);

    var geocoder = new google.maps.Geocoder();

    var markersById = new Map();
    var dataById = new Map();
    var geocodePromisesById = new Map();

    var popover = new PopoverElement({ open: false });
    map3d.append(popover);

    var selectedFarmerId = null;
    var selectedVertexIndex = -1;

    var plotsCacheByFarmerId = new Map();
    var plotFetchPromisesByFarmerId = new Map();
    var savedPlotOverlays = [];
    var draftGuideMarkers = [];

    var plotsLoadingEnabled = true;
    var plotQueue = [];
    var plotQueueSet = new Set();
    var plotInFlight = 0;

    function buildMarkerPin(f, isSelected) {
      return new PinElement({
        scale: isSelected ? 1.15 : 1.0,
        background: isSelected ? '#1d4ed8' : '#3b82f6',
        borderColor: isSelected ? '#0f172a' : '#1d4ed8',
        glyphColor: '#ffffff',
        glyphText: getFarmerGlyph(f)
      });
    }

    function applyMarkerPin(marker, f, isSelected) {
      if (!marker || !PinElement) return;
      marker.replaceChildren(buildMarkerPin(f, !!isSelected));
      marker.zIndex = isSelected ? 999 : 1;
    }

    // UPDATED: Controls individual marker visibility. Now keeps all unselected markers hidden!
    function refreshAllMarkerPins() {
      var t = document.getElementById('toggleMarkers');
      var on = !t || t.checked;
      markersById.forEach(function (marker, id) {
        var isSelected = String(id) === String(selectedFarmerId || '');
        var farmer = dataById.get(String(id)) || farmersById.get(String(id));
        applyMarkerPin(marker, farmer, isSelected);
        
        // VISIBILITY LOGIC: Marker is only visible if it's the selected one AND the toggle is on.
        marker.style.display = (on && isSelected) ? "" : "none";
      });
    }

    function refreshSavedGuideMarkers() {
      var togglePlotsEl = document.getElementById('togglePlots');
      var showPlots = !togglePlotsEl || !!togglePlotsEl.checked;

      for (var i = 0; i < savedPlotOverlays.length; i++) {
        var item = savedPlotOverlays[i];
        if (!item || !item.guides || !item.guides.length) continue;

        var shouldShow = showPlots &&
          !!selectedFarmerId &&
          String(item.farmerId) === String(selectedFarmerId);

        if (shouldShow) attachGuideMarkers(item.guides);
        else detachGuideMarkers(item.guides);
      }
    }

    // Apply the localized visibility correctly via the toggle change handler
    window.__applyMarkerVisibility = function () {
      refreshAllMarkerPins();
    };

    window.__setPlotsLoadingEnabled = function(on){
      plotsLoadingEnabled = !!on;
      if (plotsLoadingEnabled) runPlotQueue();
    };

    function queuePlotFetch(farmerId) {
      farmerId = String(farmerId);
      if (!farmerId) return;
      if (plotsCacheByFarmerId.has(farmerId)) return;
      if (plotQueueSet.has(farmerId)) return;

      plotQueue.push(farmerId);
      plotQueueSet.add(farmerId);
      runPlotQueue();
    }

    function runPlotQueue() {
      if (!plotsLoadingEnabled) return;

      while (plotInFlight < PLOT_CONCURRENCY && plotQueue.length) {
        (function (fid) {
          plotQueueSet.delete(fid);
          plotInFlight++;

          fetchPlotsForFarmer(fid).then(function(plots){
            renderPlotsForFarmer(fid, plots);
            if (typeof window.__applyPlotVisibility === "function") window.__applyPlotVisibility();
          }).finally(function(){
            plotInFlight--;
            runPlotQueue();
          });
        })(String(plotQueue.shift()));
      }
    }

    function fetchPlotsForFarmer(farmerId, opts) {
      opts = opts || {};
      farmerId = String(farmerId);

      if (!opts.force && plotsCacheByFarmerId.has(farmerId)) {
        return Promise.resolve(plotsCacheByFarmerId.get(farmerId));
      }

      if (!opts.force && plotFetchPromisesByFarmerId.has(farmerId)) {
        return plotFetchPromisesByFarmerId.get(farmerId);
      }

      var req = fetch("/farmers/" + encodeURIComponent(farmerId) + "/plots", {
        method: "GET",
        headers: { "Accept": "application/json" }
      }).then(function(r){
        if (!r.ok) return { plots: [] };
        return r.json();
      }).then(function(json){
        var plots = (json && json.plots) ? json.plots : [];
        plotsCacheByFarmerId.set(farmerId, plots);
        return plots;
      }).catch(function(){
        plotsCacheByFarmerId.set(farmerId, []);
        return [];
      }).finally(function () {
        plotFetchPromisesByFarmerId.delete(farmerId);
      });

      plotFetchPromisesByFarmerId.set(farmerId, req);
      return req;
    }

    function clearPlotsForFarmer(farmerId) {
      farmerId = String(farmerId);
      var keep = [];

      for (var i = 0; i < savedPlotOverlays.length; i++) {
        var it = savedPlotOverlays[i];
        if (String(it.farmerId) !== farmerId) {
          keep.push(it);
          continue;
        }

        try { if (it.poly && it.poly.isConnected) map3d.removeChild(it.poly); } catch(e){}
        try { if (it.line && it.line.isConnected) map3d.removeChild(it.line); } catch(e){}
        detachGuideMarkers(it.guides || []);
      }

      savedPlotOverlays = keep;
    }

    function setPlotHoverCursor(on) {
      if (moduleEl && moduleEl.classList.contains('is-plot-mode')) return;
      var cursor = on ? 'pointer' : '';
      if (stageEl) stageEl.style.cursor = cursor;
      if (host) host.style.cursor = cursor;
    }

    function bindClickablePlotOverlay(outline, poly, farmerId, plotLabel, styleOpts) {
      styleOpts = styleOpts || {};

      function applyHoverState() {
        if (plotMode) return;
        if (poly) {
          poly.fillColor = styleOpts.fillHover || styleOpts.fillSoft;
          poly.strokeColor = styleOpts.strokeHover || styleOpts.strokeStrong;
          poly.strokeWidth = styleOpts.polyHoverWidth || 10;
        }
        if (outline) {
          outline.strokeColor = styleOpts.strokeHover || styleOpts.strokeStrong;
          outline.strokeWidth = styleOpts.lineHoverWidth || 12;
          outline.outerWidth = styleOpts.lineHoverOuterWidth || 0.7;
        }
        setPlotHoverCursor(true);
        setStatus('Plot ready', 'Click this plot to select ' + (plotLabel || 'farmer'));
      }

      function resetHoverState() {
        if (poly) {
          poly.fillColor = styleOpts.fillSoft;
          poly.strokeColor = styleOpts.strokeStrong;
          poly.strokeWidth = styleOpts.polyWidth || 8;
        }
        if (outline) {
          outline.strokeColor = styleOpts.strokeStrong;
          outline.strokeWidth = styleOpts.lineWidth || 10;
          outline.outerWidth = styleOpts.lineOuterWidth || 0.45;
        }
        setPlotHoverCursor(false);
        if (!plotMode) {
          if (selectedFarmerId) setStatus('Selected plot owner', 'Click the map or another plot to change selection.');
          else setStatus('Ready', 'Hover a plot or marker, then click to select a farmer.');
        }
      }

      function handlePlotOverlayClick(ev) {
        if (ev && ev.stopPropagation) ev.stopPropagation();
        if (plotMode) return;
        window.__openFarmer3d(String(farmerId));
      }

      var targets = [outline, poly];
      for (var i = 0; i < targets.length; i++) {
        var target = targets[i];
        if (!target || typeof target.addEventListener !== 'function') continue;

        target.addEventListener('gmp-click', handlePlotOverlayClick);
        target.addEventListener('click', handlePlotOverlayClick);
        target.addEventListener('mouseenter', applyHoverState);
        target.addEventListener('mouseleave', resetHoverState);
        target.addEventListener('mouseover', applyHoverState);
        target.addEventListener('mouseout', resetHoverState);
        target.addEventListener('gmp-mouseenter', applyHoverState);
        target.addEventListener('gmp-mouseleave', resetHoverState);
      }
    }

    function renderPlotsForFarmer(farmerId, plots) {
      farmerId = String(farmerId);
      clearPlotsForFarmer(farmerId);

      if (!plots || !plots.length || !Polygon3DInteractiveElement) return;

      var show = true;
      var t = document.getElementById('togglePlots');
      if (t) show = t.checked;

      for (var i = 0; i < plots.length; i++) {
        var pl = plots[i];
        var ring = normalizePolygonRing(pl.polygon_json || pl.polygon || pl.polygonJson);
        if (!ring || ring.length < 3) continue;

        var strokeHex = pl.color ? String(pl.color) : "#22c55e";
        var strokeStrong = hexAlpha(strokeHex, "CC");
        var fillSoft = hexToRgba(strokeHex, 0.20);

        var outline = null;
        if (Polyline3DInteractiveElement) {
          outline = new Polyline3DInteractiveElement({
            path: ring,
            strokeColor: strokeStrong,
            outerColor: "#ffffff",
            strokeWidth: 10,
            outerWidth: 0.45,
            altitudeMode: AltitudeMode.CLAMP_TO_GROUND,
            drawsOccludedSegments: true
          });
          if (show) map3d.append(outline);
        }

        var poly = new Polygon3DInteractiveElement({
          path: ring,
          strokeColor: strokeStrong,
          strokeWidth: 8,
          fillColor: fillSoft,
          altitudeMode: AltitudeMode.CLAMP_TO_GROUND,
          drawsOccludedSegments: true
        });
        if (show) map3d.append(poly);

        bindClickablePlotOverlay(outline, poly, farmerId, (pl.name || ('Plot #' + pl.id)), {
          strokeStrong: strokeStrong,
          strokeHover: hexAlpha(strokeHex, 'FF'),
          fillSoft: fillSoft,
          fillHover: hexToRgba(strokeHex, 0.32),
          polyWidth: 8,
          polyHoverWidth: 10,
          lineWidth: 10,
          lineHoverWidth: 12,
          lineOuterWidth: 0.45,
          lineHoverOuterWidth: 0.7
        });

        var guides = [];
        if (String(selectedFarmerId || '') === farmerId) {
          guides = buildGuideMarkersFromRing(ring, {
            cornerOffsetMeters: 8,
            cornerScale: 0.54,
            midScale: 0.44
          });
          if (show) attachGuideMarkers(guides);
        }

        savedPlotOverlays.push({
          farmerId: farmerId,
          plotId: pl.id,
          poly: poly,
          line: outline,
          ring: ring,
          guides: guides
        });
      }

      refreshSavedGuideMarkers();
    }

    function syncSelectedPanel(f) {
      if (!f) {
        window.__mapUiSetText && window.__mapUiSetText('selName', '—');
        window.__mapUiSetText && window.__mapUiSetText('selFfrs', '—');
        window.__mapUiSetText && window.__mapUiSetText('selLocation', '—');
        window.__mapUiSetText && window.__mapUiSetText('selRecords', '0');
        window.__mapUiSetText && window.__mapUiSetText('selKgs', '0.00');
        window.__mapUiSetText && window.__mapUiSetText('selLast', '—');
        window.__mapUiSetHref && window.__mapUiSetHref('viewRecordsBtn', '#');
        updateSelectionChip(null);
        updateSelectionPill(null);
        return;
      }

      window.__mapUiSetText && window.__mapUiSetText('selName', formatName(f));
      window.__mapUiSetText && window.__mapUiSetText('selFfrs', f.ffrs || '—');
      window.__mapUiSetText && window.__mapUiSetText('selLocation', (f.location || '').trim() || '—');
      window.__mapUiSetText && window.__mapUiSetText('selRecords', Number(f.records_count || 0));
      window.__mapUiSetText && window.__mapUiSetText('selKgs', (Number(f.total_kgs || 0)).toFixed(2));
      window.__mapUiSetText && window.__mapUiSetText('selLast', f.last_received || '—');

      var base = window.__farmersRecordsBaseUrl || "/farmers";
      window.__mapUiSetHref && window.__mapUiSetHref(
        'viewRecordsBtn',
        base.replace(/\/$/, '') + "/" + encodeURIComponent(String(f.id)) + "/records"
      );

      updateSelectionChip(f);
      updateSelectionPill(f);
    }

    function updatePlotCount(n) {
      var el = document.getElementById('plotCountPill');
      if (el) el.textContent = (n || 0) + ((n === 1) ? ' plot' : ' plots');
    }

    function updatePlotTotalArea(ha) {
      var el = document.getElementById('plotAreaTotalPill');
      if (el) el.textContent = (Number(ha || 0)).toFixed(2) + ' ha total';
    }

    function sumPlotAreas(plots) {
      var sum = 0;
      for (var i = 0; i < plots.length; i++) {
        var ring = normalizePolygonRing(plots[i].polygon_json || plots[i].polygon || plots[i].polygonJson);
        var ha = (plots[i].area_ha != null ? plots[i].area_ha : (plots[i].areaHa != null ? plots[i].areaHa : null));
        if (ha == null) ha = estimateAreaHa(ring);
        sum += Number(ha || 0);
      }
      return sum;
    }

    function buildStaticPlotMapUrl(plot) {
      var ring = normalizePolygonRing(plot.polygon_json || plot.polygon || plot.polygonJson);
      if (!ring || ring.length < 3) throw new Error('Plot has no valid polygon.');

      var pts = openRing(ring);
      var colorHex = normalizeHexColor(plot.color || '#3b82f6');
      var colorNoHash = colorHex.replace('#', '').toUpperCase();
      var strokeColor = '0x' + colorNoHash + 'FF';
      var fillColor = '0x' + colorNoHash + '26';

      var params = [
        'size=640x420',
        'scale=2',
        'format=png',
        'maptype=hybrid',
        'key=' + encodeURIComponent(GOOGLE_MAPS_API_KEY)
      ];

      if (window.__gmapsMapId) {
        params.push('map_id=' + encodeURIComponent(window.__gmapsMapId));
      }

      var pathBits = [
        'fillcolor:' + fillColor,
        'color:' + strokeColor,
        'weight:4'
      ];

      for (var i = 0; i < pts.length; i++) {
        pathBits.push(Number(pts[i].lat).toFixed(6) + ',' + Number(pts[i].lng).toFixed(6));
      }

      pathBits.push(Number(pts[0].lat).toFixed(6) + ',' + Number(pts[0].lng).toFixed(6));
      params.push('path=' + encodeURIComponent(pathBits.join('|')));

      var labels = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
      for (var j = 0; j < pts.length && j < labels.length; j++) {
        params.push(
          'markers=' + encodeURIComponent(
            'color:red|label:' + labels.charAt(j) + '|' +
            Number(pts[j].lat).toFixed(6) + ',' + Number(pts[j].lng).toFixed(6)
          )
        );
      }

      var centroid = ringCentroid(pts);
      params.push(
        'markers=' + encodeURIComponent(
          'color:blue|label:F|' +
          Number(centroid.lat).toFixed(6) + ',' + Number(centroid.lng).toFixed(6)
        )
      );

      return 'https://maps.googleapis.com/maps/api/staticmap?' + params.join('&');
    }

    function buildSuggestedPlotName(plots) {
      var f = selectedFarmerId ? (farmersById.get(String(selectedFarmerId)) || dataById.get(String(selectedFarmerId))) : null;
      if (!f) return 'e.g., North Field';

      var parts = [];
      if ((f.last_name || '').trim()) parts.push((f.last_name || '').trim());
      else parts.push(formatName(f));

      var nextNo = ((plots && plots.length) ? plots.length : 0) + 1;
      return 'e.g., ' + parts.join(' ') + ' Plot ' + nextNo;
    }

    function syncSuggestedPlotName(plots, fillIfEmpty) {
      if (!plotNameInputEl) return;
      plotNameInputEl.placeholder = buildSuggestedPlotName(plots);
      if (fillIfEmpty && !String(plotNameInputEl.value || '').trim()) {
        plotNameInputEl.value = buildSuggestedPlotName(plots).replace(/^e\.g\.,\s*/i, '');
      }
    }

    function findSelectedFarmerPlots() {
      if (!selectedFarmerId) return [];
      return plotsCacheByFarmerId.get(String(selectedFarmerId)) || [];
    }

    function getPlotById(plotId) {
      var plots = findSelectedFarmerPlots();
      for (var i = 0; i < plots.length; i++) {
        if (String(plots[i].id) === String(plotId)) return plots[i];
      }
      return null;
    }

    function focusPlotById(plotId) {
      var plots = findSelectedFarmerPlots();
      for (var i = 0; i < plots.length; i++) {
        if (String(plots[i].id) !== String(plotId)) continue;
        var ring = normalizePolygonRing(plots[i].polygon_json || plots[i].polygon || plots[i].polygonJson);
        if (ring && ring.length >= 3) zoomToRing(ring, 4.2, 700, 18000);
        return;
      }
    }

    function buildPlotSheetSvg(farmer, plot) {
      var ring = normalizePolygonRing(plot.polygon_json || plot.polygon || plot.polygonJson);
      if (!ring || ring.length < 3) throw new Error('Plot has no valid polygon.');

      var plotColor = normalizeHexColor(plot.color || '#3b82f6');
      var rgb = hexToRgb(plotColor);
      var areaHa = plot.area_ha != null ? Number(plot.area_ha) : estimateAreaHa(ring);

      var centroidLat = 0;
      var centroidLng = 0;
      for (var c = 0; c < ring.length; c++) {
        centroidLat += ring[c].lat;
        centroidLng += ring[c].lng;
      }
      centroidLat /= ring.length;
      centroidLng /= ring.length;

      var width = 1600;
      var height = 1000;

      var mapX = 70, mapY = 170, mapW = 920, mapH = 470;
      var rightX = 1040, rightY = 170, rightW = 490, rightH = 740;

      var minLat = Infinity, maxLat = -Infinity, minLng = Infinity, maxLng = -Infinity;
      for (var j = 0; j < ring.length; j++) {
        minLat = Math.min(minLat, ring[j].lat);
        maxLat = Math.max(maxLat, ring[j].lat);
        minLng = Math.min(minLng, ring[j].lng);
        maxLng = Math.max(maxLng, ring[j].lng);
      }

      var pad = 70;
      var innerW = mapW - (pad * 2);
      var innerH = mapH - (pad * 2);

      function proj(pt) {
        var xNorm = (pt.lng - minLng) / ((maxLng - minLng) || 1);
        var yNorm = (maxLat - pt.lat) / ((maxLat - minLat) || 1);
        return {
          x: mapX + pad + (xNorm * innerW),
          y: mapY + pad + (yNorm * innerH)
        };
      }

      var points = [];
      for (var k = 0; k < ring.length; k++) points.push(proj(ring[k]));

      var polyPoints = points.map(function (p) {
        return p.x.toFixed(2) + ',' + p.y.toFixed(2);
      }).join(' ');

      var cx = 0, cy = 0;
      for (var n = 0; n < points.length; n++) {
        cx += points[n].x;
        cy += points[n].y;
      }
      cx /= points.length;
      cy /= points.length;

      var rows = '';
      var maxRows = Math.min(ring.length, 10);
      for (var r = 0; r < maxRows; r++) {
        var y = 835 + (r * 28);
        rows += ''
          + '<line x1="85" y1="' + (y + 12) + '" x2="975" y2="' + (y + 12) + '" stroke="#dbe4ef" stroke-width="1"/>'
          + '<text x="110" y="' + y + '" font-size="16" font-weight="700" fill="#0f172a">P' + (r + 1) + '</text>'
          + '<text x="250" y="' + y + '" font-size="16" fill="#0f172a" font-family="monospace">' + ring[r].lat.toFixed(6) + '</text>'
          + '<text x="550" y="' + y + '" font-size="16" fill="#0f172a" font-family="monospace">' + ring[r].lng.toFixed(6) + '</text>';
      }

      var pointDots = '';
      for (var p = 0; p < points.length; p++) {
        pointDots += ''
          + '<circle cx="' + points[p].x.toFixed(2) + '" cy="' + points[p].y.toFixed(2) + '" r="9" fill="#ffffff" stroke="' + plotColor + '" stroke-width="4"/>'
          + '<text x="' + (points[p].x + 16).toFixed(2) + '" y="' + (points[p].y - 12).toFixed(2) + '" font-size="18" font-weight="700" fill="' + plotColor + '">P' + (p + 1) + '</text>';
      }

      var createdAt = plot.created_at ? String(plot.created_at).split('T')[0] : '—';
      var farmerName = farmer ? formatName(farmer) : 'Selected Farmer';
      var farmerLocation = farmer && farmer.location ? farmer.location : '—';
      var farmerFfrs = farmer && farmer.ffrs ? farmer.ffrs : '—';
      var plotName = plot.name || ('Plot #' + plot.id);

      var svg = '';
      svg += '<svg xmlns="http://www.w3.org/2000/svg" width="' + width + '" height="' + height + '" viewBox="0 0 ' + width + ' ' + height + '">';
      svg += '<rect width="100%" height="100%" fill="#f4f7fb"/>';
      svg += '<rect x="20" y="20" width="1560" height="960" rx="28" fill="#f8fbff" stroke="#d7e3f1" stroke-width="2"/>';

      svg += '<text x="60" y="90" font-size="42" font-weight="800" fill="#0f172a">Printable Land Plot Sheet</text>';
      svg += '<text x="60" y="125" font-size="20" fill="#64748b">Generated from your saved polygon data for download and printing.</text>';

      svg += '<rect x="60" y="145" width="135" height="34" rx="17" fill="#e8f0ff"/>';
      svg += '<text x="128" y="168" font-size="15" font-weight="700" text-anchor="middle" fill="#2563eb">LAND PLOT</text>';

      svg += '<rect x="' + mapX + '" y="' + mapY + '" width="' + mapW + '" height="' + mapH + '" rx="24" fill="#edf3f8" stroke="#d7e3f1" stroke-width="2"/>';

      for (var gx = mapX + 25; gx < mapX + mapW; gx += 55) {
        svg += '<line x1="' + gx + '" y1="' + (mapY + 15) + '" x2="' + gx + '" y2="' + (mapY + mapH - 15) + '" stroke="#d7e3f1" stroke-width="1"/>';
      }
      for (var gy = mapY + 15; gy < mapY + mapH; gy += 55) {
        svg += '<line x1="' + (mapX + 15) + '" y1="' + gy + '" x2="' + (mapX + mapW - 15) + '" y2="' + gy + '" stroke="#d7e3f1" stroke-width="1"/>';
      }

      svg += '<text x="85" y="210" font-size="34" font-weight="800" fill="#0f172a">Plot preview</text>';
      svg += '<text x="85" y="242" font-size="18" fill="#64748b">Polygon preview exported from the selected farmer plot.</text>';

      svg += '<polygon points="' + polyPoints + '" fill="rgba(' + rgb.r + ',' + rgb.g + ',' + rgb.b + ',0.18)" stroke="' + plotColor + '" stroke-width="6"/>';
      svg += pointDots;
      svg += '<circle cx="' + cx.toFixed(2) + '" cy="' + cy.toFixed(2) + '" r="10" fill="#10b981" stroke="#ffffff" stroke-width="3"/>';
      svg += '<text x="' + (cx + 18).toFixed(2) + '" y="' + (cy + 6).toFixed(2) + '" font-size="20" font-weight="700" fill="#047857">Centroid</text>';

      svg += '<text x="' + (mapX + mapW - 70) + '" y="' + (mapY + 55) + '" font-size="28" font-weight="800" text-anchor="middle" fill="#0f172a">N</text>';
      svg += '<polygon points="' + (mapX + mapW - 70) + ',' + (mapY + 68) + ' ' + (mapX + mapW - 90) + ',' + (mapY + 110) + ' ' + (mapX + mapW - 50) + ',' + (mapY + 110) + '" fill="#0f172a"/>';

      svg += '<rect x="70" y="670" width="920" height="250" rx="22" fill="#ffffff" stroke="#dbe4ef" stroke-width="2"/>';
      svg += '<text x="85" y="710" font-size="30" font-weight="800" fill="#0f172a">Coordinates</text>';
      svg += '<text x="85" y="738" font-size="17" fill="#64748b">Corner points stored in polygon_json.</text>';

      svg += '<rect x="82" y="760" width="895" height="42" rx="16" fill="#e8f0ff"/>';
      svg += '<text x="110" y="787" font-size="18" font-weight="700" fill="#2563eb">Point</text>';
      svg += '<text x="250" y="787" font-size="18" font-weight="700" fill="#2563eb">Latitude</text>';
      svg += '<text x="550" y="787" font-size="18" font-weight="700" fill="#2563eb">Longitude</text>';
      svg += rows;

      svg += '<rect x="' + rightX + '" y="' + rightY + '" width="' + rightW + '" height="' + rightH + '" rx="24" fill="#ffffff" stroke="#dbe4ef" stroke-width="2"/>';
      svg += '<text x="' + (rightX + 25) + '" y="' + (rightY + 45) + '" font-size="34" font-weight="800" fill="#0f172a">Plot summary</text>';

      svg += '<rect x="' + (rightX + 25) + '" y="' + (rightY + 80) + '" width="205" height="90" rx="18" fill="#fbfdff" stroke="#dbe4ef"/>';
      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 110) + '" font-size="16" font-weight="700" fill="#64748b">Plot Name</text>';
      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 145) + '" font-size="22" font-weight="800" fill="#0f172a">' + escapeXml(plotName) + '</text>';

      svg += '<rect x="' + (rightX + 255) + '" y="' + (rightY + 80) + '" width="210" height="90" rx="18" fill="#fbfdff" stroke="#dbe4ef"/>';
      svg += '<text x="' + (rightX + 270) + '" y="' + (rightY + 110) + '" font-size="16" font-weight="700" fill="#64748b">Plot Color</text>';
      svg += '<rect x="' + (rightX + 270) + '" y="' + (rightY + 124) + '" width="28" height="28" rx="6" fill="' + plotColor + '"/>';
      svg += '<text x="' + (rightX + 308) + '" y="' + (rightY + 145) + '" font-size="24" font-weight="800" fill="#0f172a">' + plotColor + '</text>';

      svg += '<rect x="' + (rightX + 25) + '" y="' + (rightY + 190) + '" width="205" height="90" rx="18" fill="#fbfdff" stroke="#dbe4ef"/>';
      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 220) + '" font-size="16" font-weight="700" fill="#64748b">Area</text>';
      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 255) + '" font-size="24" font-weight="800" fill="#0f172a">' + areaHa.toFixed(2) + ' ha</text>';

      svg += '<rect x="' + (rightX + 255) + '" y="' + (rightY + 190) + '" width="210" height="90" rx="18" fill="#fbfdff" stroke="#dbe4ef"/>';
      svg += '<text x="' + (rightX + 270) + '" y="' + (rightY + 220) + '" font-size="16" font-weight="700" fill="#64748b">Vertices</text>';
      svg += '<text x="' + (rightX + 270) + '" y="' + (rightY + 255) + '" font-size="24" font-weight="800" fill="#0f172a">' + ring.length + ' corners</text>';

      svg += '<rect x="' + (rightX + 25) + '" y="' + (rightY + 305) + '" width="' + (rightW - 50) + '" height="405" rx="18" fill="#fbfdff" stroke="#dbe4ef"/>';
      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 345) + '" font-size="16" font-weight="700" fill="#64748b">Farmer</text>';
      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 377) + '" font-size="24" font-weight="800" fill="#0f172a">' + escapeXml(farmerName) + '</text>';
      svg += '<line x1="' + (rightX + 40) + '" y1="' + (rightY + 395) + '" x2="' + (rightX + rightW - 40) + '" y2="' + (rightY + 395) + '" stroke="#dbe4ef"/>';

      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 430) + '" font-size="16" font-weight="700" fill="#64748b">FFRS</text>';
      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 462) + '" font-size="22" fill="#0f172a">' + escapeXml(farmerFfrs) + '</text>';
      svg += '<line x1="' + (rightX + 40) + '" y1="' + (rightY + 480) + '" x2="' + (rightX + rightW - 40) + '" y2="' + (rightY + 480) + '" stroke="#dbe4ef"/>';

      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 515) + '" font-size="16" font-weight="700" fill="#64748b">Location</text>';
      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 547) + '" font-size="20" fill="#0f172a">' + escapeXml(farmerLocation) + '</text>';
      svg += '<line x1="' + (rightX + 40) + '" y1="' + (rightY + 565) + '" x2="' + (rightX + rightW - 40) + '" y2="' + (rightY + 565) + '" stroke="#dbe4ef"/>';

      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 600) + '" font-size="16" font-weight="700" fill="#64748b">Centroid Lat</text>';
      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 632) + '" font-size="22" fill="#0f172a" font-family="monospace">' + centroidLat.toFixed(6) + '</text>';

      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 675) + '" font-size="16" font-weight="700" fill="#64748b">Centroid Lng</text>';
      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 707) + '" font-size="22" fill="#0f172a" font-family="monospace">' + centroidLng.toFixed(6) + '</text>';

      svg += '<text x="' + (rightX + 25) + '" y="' + (rightY + 735) + '" font-size="16" fill="#64748b">Created: ' + escapeXml(createdAt) + '</text>';

      svg += '<text x="60" y="955" font-size="16" fill="#64748b">Generated from the selected plot. Suitable for download or print.</text>';
      svg += '<text x="1530" y="955" font-size="16" font-weight="700" text-anchor="end" fill="#0f172a">PNG export</text>';
      svg += '</svg>';

      return svg;
    }

    function svgToPngBlob(svgMarkup, width, height) {
      width = width || 1600;
      height = height || 1000;

      return new Promise(function (resolve, reject) {
        var blob = new Blob([svgMarkup], { type: 'image/svg+xml;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var img = new Image();

        img.onload = function () {
          try {
            var canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;

            var ctx = canvas.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

            canvas.toBlob(function (pngBlob) {
              URL.revokeObjectURL(url);
              if (!pngBlob) return reject(new Error('Could not create PNG.'));
              resolve(pngBlob);
            }, 'image/png', 1);
          } catch (err) {
            URL.revokeObjectURL(url);
            reject(err);
          }
        };

        img.onerror = function () {
          URL.revokeObjectURL(url);
          reject(new Error('Could not render SVG.'));
        };

        img.src = url;
      });
    }

    async function downloadPlotSheetPng(farmer, plot) {
      var svg = buildPlotSheetSvg(farmer, plot);
      var pngBlob = await svgToPngBlob(svg, 1600, 1000);

      var a = document.createElement('a');
      var url = URL.createObjectURL(pngBlob);
      var fileBase = safeFilename((farmer ? formatName(farmer) : 'farmer') + '_' + (plot.name || ('plot_' + plot.id)));

      a.href = url;
      a.download = fileBase + '.png';
      document.body.appendChild(a);
      a.click();
      a.remove();

      setTimeout(function () {
        URL.revokeObjectURL(url);
      }, 1500);
    }

    function buildPrintablePlotHtml(farmer, plot) {
      var ring = normalizePolygonRing(plot.polygon_json || plot.polygon || plot.polygonJson);
      if (!ring || ring.length < 3) throw new Error('Plot has no valid polygon.');

      var farmerName = farmer ? formatName(farmer) : 'Selected Farmer';
      var farmerFfrs = farmer && farmer.ffrs ? farmer.ffrs : '—';
      var farmerLocation = farmer && farmer.location ? farmer.location : '—';
      var plotName = plot.name || ('Plot #' + plot.id);
      var areaHa = plot.area_ha != null ? Number(plot.area_ha) : estimateAreaHa(ring);
      var createdAt = plot.created_at ? String(plot.created_at).split('T')[0] : '—';
      var centroid = ringCentroid(openRing(ring));
      var colorHex = normalizeHexColor(plot.color || '#3b82f6');
      var staticMapUrl = buildStaticPlotMapUrl(plot);

      var rows = '';
      for (var i = 0; i < ring.length; i++) {
        rows += `
          <tr>
            <td style="padding:8px 10px;border-bottom:1px solid #e5e7eb;font-weight:700;">P${i + 1}</td>
            <td style="padding:8px 10px;border-bottom:1px solid #e5e7eb;font-family:monospace;">${Number(ring[i].lat).toFixed(6)}</td>
            <td style="padding:8px 10px;border-bottom:1px solid #e5e7eb;font-family:monospace;">${Number(ring[i].lng).toFixed(6)}</td>
          </tr>
        `;
      }

      return `<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Printable Land Plot Sheet</title>

<style>
html,body{margin:0;padding:0;background:#eef2f7;font-family:Arial,Helvetica,sans-serif;color:#0f172a;}
.page{width:1200px;max-width:calc(100vw - 40px);margin:24px auto;background:#f8fbff;border:1px solid #dbe4ef;border-radius:20px;padding:22px 22px 18px;box-sizing:border-box;}
.title{font-size:34px;font-weight:800;margin:0 0 8px;}
.sub{font-size:14px;color:#64748b;margin:0 0 12px;}
.badge{display:inline-block;background:#e8f0ff;color:#2563eb;font-weight:800;font-size:12px;border-radius:999px;padding:6px 10px;margin-bottom:14px;}
.grid{display:grid;grid-template-columns:2fr 1fr;gap:18px;align-items:start;}
.card{background:#fff;border:1px solid #dbe4ef;border-radius:16px;padding:14px;box-sizing:border-box;}
.card-title{font-size:18px;font-weight:800;margin:0 0 6px;}
.card-sub{font-size:12px;color:#64748b;margin:0 0 12px;}
.mapimg{display:block;width:100%;height:auto;border:1px solid #dbe4ef;border-radius:14px;background:#f8fafc;}
.stats{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;}
.stat{border:1px solid #dbe4ef;border-radius:12px;padding:10px;background:#fbfdff;}
.stat-k{font-size:11px;font-weight:700;color:#64748b;margin-bottom:4px;}
.stat-v{font-size:18px;font-weight:800;}
.section{margin-top:10px;padding-top:10px;border-top:1px solid #e5e7eb;}
.label{font-size:11px;font-weight:700;color:#64748b;margin-bottom:4px;}
.value{font-size:16px;font-weight:700;word-break:break-word;}
.coords{margin-top:18px;}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden;}
thead th{background:#eef4ff;color:#2563eb;font-size:12px;text-align:left;padding:10px;border-bottom:1px solid #dbe4ef;}
.footer{display:flex;justify-content:space-between;gap:12px;margin-top:12px;font-size:12px;color:#64748b;}
.swatch{display:inline-block;width:12px;height:12px;border-radius:3px;background:${colorHex};margin-right:6px;vertical-align:middle;}
@media print{html,body{background:#fff;} .page{width:auto;max-width:none;margin:0;border:none;border-radius:0;padding:12px;}}
</style>
</head>

<body>

<div class="page">

  <div class="title">Printable Land Plot Sheet</div>
  <div class="sub">Generated from your saved polygon data for download and printing.</div>

  <div class="badge">LAND PLOT</div>

  <div class="grid">

    <div>
      <div class="card">
        <div class="card-title">Plot map</div>
        <div class="card-sub">Static satellite/hybrid map with polygon overlay.</div>
        <img id="plotStaticMap" class="mapimg" src="${staticMapUrl}" alt="Plot map">
      </div>

      <div class="card coords">
        <div class="card-title">Coordinates</div>
        <div class="card-sub">Corner points stored in polygon_json.</div>

        <table>
          <thead>
            <tr>
              <th>Point</th>
              <th>Latitude</th>
              <th>Longitude</th>
            </tr>
          </thead>
          <tbody>
            ${rows}
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-title">Plot summary</div>

      <div class="stats">
        <div class="stat">
          <div class="stat-k">Plot Name</div>
          <div class="stat-v">${escapeXml(plotName)}</div>
        </div>

        <div class="stat">
          <div class="stat-k">Plot Color</div>
          <div class="stat-v"><span class="swatch"></span>${escapeXml(colorHex)}</div>
        </div>

        <div class="stat">
          <div class="stat-k">Area</div>
          <div class="stat-v">${areaHa.toFixed(2)} ha</div>
        </div>

        <div class="stat">
          <div class="stat-k">Vertices</div>
          <div class="stat-v">${ring.length} corners</div>
        </div>
      </div>

      <div class="section">
        <div class="label">Farmer</div>
        <div class="value">${escapeXml(farmerName)}</div>
      </div>

      <div class="section">
        <div class="label">FFRS</div>
        <div class="value">${escapeXml(farmerFfrs)}</div>
      </div>

      <div class="section">
        <div class="label">Location</div>
        <div class="value">${escapeXml(farmerLocation)}</div>
      </div>

      <div class="section">
        <div class="label">Centroid Lat</div>
        <div class="value">${Number(centroid.lat).toFixed(6)}</div>
      </div>

      <div class="section">
        <div class="label">Centroid Lng</div>
        <div class="value">${Number(centroid.lng).toFixed(6)}</div>
      </div>

      <div class="section">
        <div class="label">Created</div>
        <div class="value">${escapeXml(createdAt)}</div>
      </div>
    </div>

  </div>

  <div class="footer">
    <span>Generated from the selected plot.</span>
    <span>Google Maps Static API</span>
  </div>

</div>

</body>
</html>`;
    }

    function printPlotSheet(farmer, plot) {
      var html = buildPrintablePlotHtml(farmer, plot);
      
      var iframe = document.createElement('iframe');
      iframe.style.position = 'absolute';
      iframe.style.width = '0';
      iframe.style.height = '0';
      iframe.style.border = 'none';
      document.body.appendChild(iframe);

      var doc = iframe.contentWindow.document;
      doc.open();
      doc.write(html);
      doc.close();

      var img = doc.getElementById("plotStaticMap");
      
      var doPrint = function () {
        setTimeout(function () {
          iframe.contentWindow.focus();
          iframe.contentWindow.print();
          
          setTimeout(function() {
            if (iframe.parentNode) iframe.parentNode.removeChild(iframe);
          }, 10000);
        }, 500); 
      };

      if (img && !img.complete) {
        img.onload = doPrint;
        img.onerror = doPrint; 
      } else {
        doPrint();
      }
    }

    async function handleDownloadPlot(plotId) {
      if (!selectedFarmerId) {
        toast('Select a farmer first.', 'warn');
        return;
      }

      var farmer = farmersById.get(String(selectedFarmerId)) || dataById.get(String(selectedFarmerId));
      var plot = plotId ? getPlotById(plotId) : null;

      if (!plot) {
        var plots = findSelectedFarmerPlots();
        if (!plots.length) {
          toast('This farmer has no saved plots yet.', 'warn');
          return;
        }
        if (plots.length > 1) {
          toast('Use the Download button on a specific plot row.', 'warn');
          return;
        }
        plot = plots[0];
      }

      try {
        setStatus('Generating image…', 'Preparing printable plot sheet');
        await downloadPlotSheetPng(farmer, plot);
        setStatus('Ready', 'Plot image downloaded.');
        toast('Plot image downloaded.', 'ok');
      } catch (err) {
        console.error(err);
        setStatus('Export failed.', '');
        toast(err && err.message ? err.message : 'Could not export plot image.', 'bad');
      }
    }

    function handlePrintPlot(plotId) {
      if (!selectedFarmerId) {
        toast('Select a farmer first.', 'warn');
        return;
      }

      var farmer = farmersById.get(String(selectedFarmerId)) || dataById.get(String(selectedFarmerId));
      var plot = plotId ? getPlotById(plotId) : null;

      if (!plot) {
        var plots = findSelectedFarmerPlots();
        if (!plots.length) {
          toast('This farmer has no saved plots yet.', 'warn');
          return;
        }
        if (plots.length > 1) {
          toast('Use the Print button on a specific plot row.', 'warn');
          return;
        }
        plot = plots[0];
      }

      try {
        printPlotSheet(farmer, plot);
        toast('Print sheet opened.', 'ok');
      } catch (err) {
        console.error(err);
        toast('Could not open printable sheet.', 'bad');
      }
    }

    window.__downloadCurrentPlotSheet = function () {
      handleDownloadPlot(null);
    };

    window.__printCurrentPlotSheet = function () {
      handlePrintPlot(null);
    };

    function updatePlotList(plots) {
      var list = document.getElementById('plotList');
      if (!list) return;

      if (!plots || !plots.length) {
        list.innerHTML = '<div class="map-empty">No plots yet. Select a farmer, then click <b>Plot Land</b>.</div>';
        syncSuggestedPlotName([], false);
        return;
      }

      syncSuggestedPlotName(plots, false);

      var html = '';
      for (var i = 0; i < plots.length; i++) {
        var pl = plots[i];
        var name = pl.name || ('Plot #' + pl.id);
        var created = pl.created_at ? String(pl.created_at).split('T')[0] : '';
        var ring = normalizePolygonRing(pl.polygon_json || pl.polygon || pl.polygonJson);
        var ha = (pl.area_ha != null ? pl.area_ha : (pl.areaHa != null ? pl.areaHa : null));
        if (ha == null) ha = estimateAreaHa(ring);

        var sub = (created ? created : '—') + (' • ' + Number(ha || 0).toFixed(2) + ' ha');
        var c = pl.color ? String(pl.color) : "#22c55e";

        html +=
          '<div class="map-plot-item">' +
            '<div>' +
              '<div class="map-plot-name"><span class="plot-swatch" style="background:' + escapeHtml(c) + ';"></span>' + escapeHtml(name) + '</div>' +
              '<div class="map-plot-sub">' + escapeHtml(sub) + '</div>' +
            '</div>' +
            '<div class="map-plot-actions">' +
              '<button type="button" class="btn btn-soft btn-sm" data-action="focusPlot" data-plot-id="' + escapeHtml(pl.id) + '">Focus</button>' +
              '<button type="button" class="btn btn-soft btn-sm" data-action="downloadPlot" data-plot-id="' + escapeHtml(pl.id) + '">Download</button>' +
              '<button type="button" class="btn btn-soft btn-sm" data-action="printPlot" data-plot-id="' + escapeHtml(pl.id) + '">Print</button>' +
              '<button type="button" class="btn btn-soft btn-sm" data-action="deletePlot" data-plot-id="' + escapeHtml(pl.id) + '">Delete</button>' +
            '</div>' +
          '</div>';
      }
      list.innerHTML = html;

      var btnsFocus = list.querySelectorAll('button[data-action="focusPlot"]');
      for (var j = 0; j < btnsFocus.length; j++) {
        btnsFocus[j].addEventListener('click', function () {
          var pid = this.getAttribute('data-plot-id');
          if (!pid) return;
          focusPlotById(pid);
        });
      }

      var btnsDownload = list.querySelectorAll('button[data-action="downloadPlot"]');
      for (var d = 0; d < btnsDownload.length; d++) {
        btnsDownload[d].addEventListener('click', function () {
          var pid = this.getAttribute('data-plot-id');
          if (!pid) return;
          handleDownloadPlot(pid);
        });
      }

      var btnsPrint = list.querySelectorAll('button[data-action="printPlot"]');
      for (var p = 0; p < btnsPrint.length; p++) {
        btnsPrint[p].addEventListener('click', function () {
          var pid = this.getAttribute('data-plot-id');
          if (!pid) return;
          handlePrintPlot(pid);
        });
      }

      var btnsDelete = list.querySelectorAll('button[data-action="deletePlot"]');
      for (var k = 0; k < btnsDelete.length; k++) {
        btnsDelete[k].addEventListener('click', function () {
          var pid = this.getAttribute('data-plot-id');
          if (!pid || !selectedFarmerId) return;
          if (!confirm('Delete this plot?')) return;

          setStatus('Deleting plot…', '');
          fetch("/farm-plots/" + encodeURIComponent(String(pid)), {
            method: "DELETE",
            headers: { "Accept": "application/json", "X-CSRF-TOKEN": csrfToken() }
          }).then(function (r) {
            if (!r.ok) throw new Error("Delete failed (" + r.status + ")");
            toast("Plot deleted.", "ok");

            plotsCacheByFarmerId.delete(String(selectedFarmerId));
            return loadPlotsForSelectedFarmer(String(selectedFarmerId), { force: true, autoZoom: false });
          }).catch(function (err) {
            console.error(err);
            toast(err && err.message ? err.message : "Delete failed", "bad");
            setStatus('Delete failed.', '');
          });
        });
      }
    }

    function loadPlotsForSelectedFarmer(farmerId, opts) {
      opts = opts || {};
      farmerId = String(farmerId);

      return fetchPlotsForFarmer(farmerId, opts).then(function(plots){
        renderPlotsForFarmer(farmerId, plots);
        if (typeof window.__applyPlotVisibility === "function") window.__applyPlotVisibility();

        if (opts.autoZoom && plots && plots.length) {
          zoomToPlots(plots);
        }

        if (String(selectedFarmerId || "") === farmerId) {
          updatePlotCount(plots.length || 0);
          updatePlotTotalArea(sumPlotAreas(plots));
          updatePlotList(plots);
        }

        return plots;
      });
    }

    function showPlotButtons(on) {
      var elClear = document.getElementById('plotClearBtn');
      var elSave = document.getElementById('plotSaveBtn');
      var elCancel = document.getElementById('plotCancelBtn');

      if (elClear) elClear.style.display = on ? "" : "none";
      if (elSave) elSave.style.display = on ? "" : "none";
      if (elCancel) elCancel.style.display = on ? "" : "none";
      if (plotModeBadge) plotModeBadge.style.display = on ? "" : "none";

      var draftInfo = document.getElementById('plotDraftInfo');
      if (draftInfo) draftInfo.style.display = on ? "" : "none";

      syncWorkflowUi();
    }

    // This zoom out uses all points (even hidden) to structure viewport correctly 
    function fitToMarkers(onlyVisible) {
      var minLat = Infinity, maxLat = -Infinity, minLng = Infinity, maxLng = -Infinity;
      var count = 0;

      markersById.forEach(function (marker) {
        if (onlyVisible && marker.style.display === "none") return;
        if (!marker.position) return;
        var ll = toLatLng(marker.position);
        minLat = Math.min(minLat, ll.lat);
        maxLat = Math.max(maxLat, ll.lat);
        minLng = Math.min(minLng, ll.lng);
        maxLng = Math.max(maxLng, ll.lng);
        count++;
      });

      if (count <= 0) return;

      var centerLat = (minLat + maxLat) / 2;
      var centerLng = (minLng + maxLng) / 2;
      var diag = haversineMeters({ lat: minLat, lng: minLng }, { lat: maxLat, lng: maxLng });
      var range = clamp(diag * 2.0, 2500, 1200000);
      flyTo(centerLat, centerLng, range, 1100);
    }

    window.__fit3dToVisibleMarkers = function () {
      fitToMarkers(false); // Calculates bounds based on all geocoded positions even though pins are hidden
    };

    window.__applyPlotVisibility = function () {
      var t = document.getElementById('togglePlots');
      var on = !t || t.checked;

      for (var i = 0; i < savedPlotOverlays.length; i++) {
        var it = savedPlotOverlays[i];
        try {
          if (!it.poly) continue;

          if (on) {
            if (!it.poly.isConnected) map3d.append(it.poly);
            if (it.line && !it.line.isConnected) map3d.append(it.line);
          } else {
            if (it.poly.isConnected) map3d.removeChild(it.poly);
            if (it.line && it.line.isConnected) map3d.removeChild(it.line);
          }
        } catch(e) {}
      }

      refreshSavedGuideMarkers();
    };

    window.__reset3dMap = function () {
      popover.open = false;
      selectedFarmerId = null;
      plotMode = false;
      setPlotModeUi(false);
      showPlotButtons(false);
      clearDraftPlot();

      if (hintEl && !selectedFarmerId) hintEl.style.display = "";
      flyTo(DEFAULT_CENTER.lat, DEFAULT_CENTER.lng, DEFAULT_RANGE, 900);

      setStatus("Ready", "Reset camera.");
      refreshAllMarkerPins(); // hides all unselected pins
      refreshSavedGuideMarkers();
      toast('Map reset.', 'ok');
    };

    window.__focusSelectedFarmer = function () {
      if (!selectedFarmerId) {
        toast('Select a farmer first.', 'warn');
        return;
      }
      var marker = markersById.get(String(selectedFarmerId));
      if (!marker || !marker.position) return;
      var ll = toLatLng(marker.position);
      flyTo(ll.lat, ll.lng, 9000, 700);
    };

    window.__clearFarmerSelection = function () {
      popover.open = false;
      selectedFarmerId = null;
      refreshSavedGuideMarkers();
      refreshAllMarkerPins(); // Automatically hides previously selected pin!

      plotMode = false;
      setPlotModeUi(false);
      showPlotButtons(false);
      clearDraftPlot();

      if (hintEl) hintEl.style.display = "";
      syncSelectedPanel(null);

      updatePlotCount(0);
      updatePlotTotalArea(0);
      updatePlotList([]);
      setStatus("Ready", "Select a farmer.");
      syncWorkflowUi();
      if (plotNameInputEl) plotNameInputEl.value = '';
    };

    var plotMode = false;
    var plotVertices = [];
    var draftDots = [];
    var draftPoly = null;

    function normalizeHex(h) {
      h = String(h || '').trim();
      if (!h) return '#3b82f6';
      if (h[0] !== '#') h = '#' + h;
      if (/^#([0-9a-fA-F]{3})$/.test(h) || /^#([0-9a-fA-F]{6})$/.test(h) || /^#([0-9a-fA-F]{8})$/.test(h)) return h;
      return '#3b82f6';
    }

    function getPlotColor() {
      var colorInput = document.getElementById('plotColorInput');
      var colorHexInput = document.getElementById('plotColorHex');
      var h = colorHexInput ? colorHexInput.value : (colorInput ? colorInput.value : '#3b82f6');
      return normalizeHex(h);
    }

    function setPlotColor(h) {
      h = normalizeHex(h);
      var colorInput = document.getElementById('plotColorInput');
      var colorHexInput = document.getElementById('plotColorHex');
      if (colorInput) colorInput.value = h.length === 9 ? h.slice(0,7) : h;
      if (colorHexInput) colorHexInput.value = h;
      syncColorPresetState();
    }

    function randomColor() {
      var letters = '0123456789ABCDEF';
      var c = '#';
      for (var i = 0; i < 6; i++) c += letters[Math.floor(Math.random() * 16)];
      return c;
    }

    function syncColorPresetState() {
      var current = normalizeHex(getPlotColor()).slice(0, 7).toLowerCase();
      var chips = document.querySelectorAll('#plotColorPresets .map-color-chip');
      for (var i = 0; i < chips.length; i++) {
        var chipColor = normalizeHex(chips[i].getAttribute('data-color') || '').slice(0, 7).toLowerCase();
        if (chipColor === current) chips[i].classList.add('is-active');
        else chips[i].classList.remove('is-active');
      }
    }

    (function bindColorControls(){
      var colorInput = document.getElementById('plotColorInput');
      var colorHexInput = document.getElementById('plotColorHex');
      var colorRandomBtn = document.getElementById('plotColorRandomBtn');
      var presetBtns = document.querySelectorAll('#plotColorPresets .map-color-chip');

      if (colorInput) {
        colorInput.addEventListener('input', function(){
          setPlotColor(colorInput.value);
          if (plotMode && plotVertices.length) refreshDraftDots();
        });
      }

      if (colorHexInput) {
        colorHexInput.addEventListener('input', function(){
          setPlotColor(colorHexInput.value);
          if (plotMode && plotVertices.length) refreshDraftDots();
        });
      }

      if (colorRandomBtn) {
        colorRandomBtn.addEventListener('click', function(){
          setPlotColor(randomColor());
          if (plotMode && plotVertices.length) refreshDraftDots();
        });
      }

      for (var i = 0; i < presetBtns.length; i++) {
        presetBtns[i].addEventListener('click', function () {
          var c = this.getAttribute('data-color') || '#3b82f6';
          setPlotColor(c);
          if (plotMode && plotVertices.length) refreshDraftDots();
        });
      }

      syncColorPresetState();
    })();

    function updateDraftStats() {
      var areaEl = document.getElementById('plotDraftArea');
      var ha = estimateAreaHa(plotVertices);

      if (areaEl) areaEl.textContent = (ha || 0).toFixed(2);

      setWorkflowCorners(plotVertices.length || 0);
      setWorkflowArea(ha || 0);

      if (plotMode) {
        if (plotVertices.length === 0) {
          setStatus('Plot mode', 'Draft ready. Click New rectangle to start.');
        } else if (selectedVertexIndex >= 0) {
          setStatus('Adjusting plot', 'Corner ' + (selectedVertexIndex + 1) + ' selected.');
        } else {
          setStatus('Plot mode', 'Draft: ~' + (ha || 0).toFixed(2) + ' ha');
        }
      }

      syncWorkflowUi();
    }

    function clearDraftDots() {
      for (var i = 0; i < draftDots.length; i++) {
        try { if (draftDots[i] && draftDots[i].isConnected) map3d.removeChild(draftDots[i]); } catch(e) {}
      }
      draftDots = [];

      detachGuideMarkers(draftGuideMarkers);
      draftGuideMarkers = [];

      if (draftPoly && draftPoly.isConnected) {
        try { map3d.removeChild(draftPoly); } catch(e) {}
      }
      draftPoly = null;
    }

    function refreshDraftDots() {
      clearDraftDots();

      var strokeHex = getPlotColor();
      var fillRgba = hexToRgba(strokeHex, 0.20);

      if (plotVertices.length >= 3 && Polygon3DElement) {
        draftPoly = new Polygon3DElement({
          path: plotVertices,
          strokeColor: hexAlpha(strokeHex, "CC"),
          strokeWidth: 9,
          fillColor: fillRgba,
          altitudeMode: AltitudeMode.CLAMP_TO_GROUND,
          drawsOccludedSegments: true
        });
        map3d.append(draftPoly);
      }

      for (var i = 0; i < plotVertices.length; i++) {
        (function (idx) {
          var v = plotVertices[idx];
          var dot = new Marker3DInteractiveElement({
            altitudeMode: AltitudeMode.CLAMP_TO_GROUND,
            position: { lat: v.lat, lng: v.lng },
            title: "Corner " + (idx + 1)
          });

          applyMarkerPin(dot, { first_name: 'C', last_name: String(idx + 1) }, idx === selectedVertexIndex);

          dot.drawsWhenOccluded = true;
          dot.sizePreserved = true;

          dot.addEventListener("gmp-click", function (ev) {
            if (ev && ev.stopPropagation) ev.stopPropagation();
            if (!plotMode) return;
            selectedVertexIndex = idx;
            toast("Selected corner " + (idx + 1) + ". Click the map or use arrow keys to move it.", "ok");
            refreshDraftDots();
          });

          map3d.append(dot);
          draftDots.push(dot);
        })(i);
      }

      draftGuideMarkers = buildGuideMarkersFromRing(plotVertices, {
        cornerOffsetMeters: 8,
        cornerScale: 0.54,
        midScale: 0.44,
        onCornerClick: function (idx) {
          if (!plotMode) return;
          selectedVertexIndex = idx;
          toast("Selected corner " + (idx + 1) + ".", "ok");
          refreshDraftDots();
        }
      });

      attachGuideMarkers(draftGuideMarkers);

      updateDraftStats();
    }

    function clearDraftPlot() {
      plotVertices = [];
      selectedVertexIndex = -1;
      clearDraftDots();
      updateDraftStats();
    }

    function moveSelectedVertex(lat, lng) {
      if (selectedVertexIndex < 0 || selectedVertexIndex >= plotVertices.length) return;
      plotVertices[selectedVertexIndex] = { lat: lat, lng: lng };
      toast("Moved corner.", "ok");
      selectedVertexIndex = -1;
      refreshDraftDots();
    }

    function nudgeSelectedVertex(latStep, lngStep) {
      if (selectedVertexIndex < 0 || selectedVertexIndex >= plotVertices.length) return;
      plotVertices[selectedVertexIndex] = {
        lat: plotVertices[selectedVertexIndex].lat + latStep,
        lng: plotVertices[selectedVertexIndex].lng + lngStep
      };
      refreshDraftDots();
    }

    function buildDefaultDraftAroundSelected() {
      var mk = markersById.get(String(selectedFarmerId));
      if (!mk || !mk.position) {
        toast("Marker not ready yet. Try again in a moment.", "warn");
        return false;
      }

      var ll = toLatLng(mk.position);
      var offsetLat = 0.00015;
      var offsetLng = 0.00015;

      plotVertices = [
        { lat: ll.lat + offsetLat, lng: ll.lng - offsetLng },
        { lat: ll.lat + offsetLat, lng: ll.lng + offsetLng },
        { lat: ll.lat - offsetLat, lng: ll.lng + offsetLng },
        { lat: ll.lat - offsetLat, lng: ll.lng - offsetLng }
      ];

      selectedVertexIndex = -1;
      refreshDraftDots();
      flyTo(ll.lat, ll.lng, 1800, 750);
      return true;
    }

    function focusDraft() {
      if (!plotVertices || plotVertices.length < 3) {
        toast('No draft to center.', 'warn');
        return;
      }
      zoomToRing(plotVertices, 4.2, 600, 15000);
    }

    function enterPlotMode() {
      if (!selectedFarmerId) {
        toast("Select a farmer first, then plot.", "warn");
        return;
      }

      plotMode = true;
      setPlotModeUi(true);
      showPlotButtons(true);

      clearDraftPlot();
      if (hintEl) hintEl.style.display = "none";

      var plots = findSelectedFarmerPlots();
      syncSuggestedPlotName(plots, true);

      if (buildDefaultDraftAroundSelected()) {
        toast("Draft rectangle created. Adjust corners, then save.", "ok");
      }
    }

    var btnPlotMode = document.getElementById('plotModeBtn');
    var btnClear = document.getElementById('plotClearBtn');
    var btnSave = document.getElementById('plotSaveBtn');
    var btnCancel = document.getElementById('plotCancelBtn');
    var btnNewRect = document.getElementById('plotNewRectBtn');
    var btnCenterDraft = document.getElementById('plotCenterDraftBtn');

    if (btnPlotMode) {
      btnPlotMode.addEventListener('click', function () {
        enterPlotMode();
      });
    }

    if (btnNewRect) {
      btnNewRect.addEventListener('click', function () {
        if (!plotMode) {
          enterPlotMode();
          return;
        }
        if (buildDefaultDraftAroundSelected()) {
          toast("Draft rectangle reset.", "ok");
        }
      });
    }

    if (btnCenterDraft) {
      btnCenterDraft.addEventListener('click', function () {
        focusDraft();
      });
    }

    if (btnCancel) {
      btnCancel.addEventListener('click', function () {
        plotMode = false;
        setPlotModeUi(false);
        showPlotButtons(false);
        clearDraftPlot();
        setStatus('Ready', selectedFarmerId ? 'Selected farmer loaded.' : 'Select a farmer.');
        toast("Plot cancelled.", "warn");
      });
    }

    if (btnClear) {
      btnClear.addEventListener('click', function () {
        clearDraftPlot();
        toast("Draft cleared. Use New rectangle to start again.", "warn");
      });
    }

    if (btnSave) {
      btnSave.addEventListener('click', function () {
        if (!selectedFarmerId) return;
        if (plotVertices.length < 3) {
          toast("Draft not ready.", "warn");
          return;
        }

        var plotName = (plotNameInputEl && plotNameInputEl.value) ? String(plotNameInputEl.value).trim() : '';
        if (!plotName) plotName = "Plot";

        var plotColor = getPlotColor();

        setStatus("Saving plot…", "");
        toast("Saving plot…", "ok");

        fetch("/farmers/" + encodeURIComponent(String(selectedFarmerId)) + "/plots", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "Accept": "application/json",
            "X-CSRF-TOKEN": csrfToken()
          },
          body: JSON.stringify({
            name: plotName,
            color: plotColor,
            polygon: plotVertices
          })
        }).then(function (r) {
          if (!r.ok) throw new Error("Save failed (" + r.status + ")");
          return r.json();
        }).then(function () {
          plotMode = false;
          setPlotModeUi(false);
          showPlotButtons(false);
          clearDraftPlot();

          setStatus("Saved ✅", "Plot added.");
          toast("Saved plot ✅", "ok");

          plotsCacheByFarmerId.delete(String(selectedFarmerId));
          return loadPlotsForSelectedFarmer(String(selectedFarmerId), { force: true, autoZoom: true });
        }).then(function (plots) {
          syncSuggestedPlotName(plots || [], false);
          if (plotNameInputEl) plotNameInputEl.value = '';
        }).catch(function (err) {
          console.error(err);
          toast(err && err.message ? err.message : "Save failed", "bad");
          setStatus("Save failed.", "");
        });
      });
    }

    document.addEventListener('keydown', function (e) {
      if (!plotMode) return;

      var active = document.activeElement;
      var typing = active && /^(input|textarea)$/i.test(active.tagName);

      if (e.key === 'Escape') {
        e.preventDefault();
        if (btnCancel) btnCancel.click();
        return;
      }

      if (e.key === 'Enter') {
        if (typing && active && active.id === 'plotNameInput') return;
        e.preventDefault();
        if (btnSave) btnSave.click();
        return;
      }

      if (typing) return;

      if (e.key === 'Backspace') {
        e.preventDefault();
        if (btnClear) btnClear.click();
        return;
      }

      if (selectedVertexIndex < 0) return;

      var step = e.shiftKey ? 0.00008 : 0.00002;
      if (e.key === 'ArrowUp') {
        e.preventDefault();
        nudgeSelectedVertex(step, 0);
      } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        nudgeSelectedVertex(-step, 0);
      } else if (e.key === 'ArrowLeft') {
        e.preventDefault();
        nudgeSelectedVertex(0, -step);
      } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        nudgeSelectedVertex(0, step);
      }
    });

    map3d.addEventListener("gmp-click", function (ev) {
      if (plotMode) {
        var pos = ev.position;
        if (!pos) return;
        var ll = toLatLng(pos);

        if (selectedVertexIndex >= 0) {
          moveSelectedVertex(ll.lat, ll.lng);
        } else {
          toast("Click a corner dot first, then click the map to move it.", "warn");
        }
        return;
      }

      popover.open = false;
    });

    function createFarmerMarker(f, lat, lng) {
      dataById.set(String(f.id), f);

      var marker = new Marker3DInteractiveElement({
        altitudeMode: AltitudeMode.CLAMP_TO_GROUND,
        position: { lat: lat, lng: lng },
        title: formatName(f),
        sizePreserved: true,
        drawsWhenOccluded: true
      });

      applyMarkerPin(marker, f, false);

      // HIDDEN BY DEFAULT: only shows up when clicked in the table.
      marker.style.display = "none";

      marker.addEventListener("gmp-click", function (event) {
        if (event && event.stopPropagation) event.stopPropagation();
        window.__openFarmer3d(String(f.id));
      });

      map3d.append(marker);
      markersById.set(String(f.id), marker);

      queuePlotFetch(String(f.id));

      if (typeof window.__applyMarkerVisibility === "function") window.__applyMarkerVisibility();

      return marker;
    }

    function ensureMarkerForFarmer(id) {
      id = String(id);
      if (markersById.has(id)) return Promise.resolve(markersById.get(id));
      if (geocodePromisesById.has(id)) return geocodePromisesById.get(id);

      var f = farmersById.get(id);
      if (!f) return Promise.reject(new Error("Farmer not found in map dataset."));

      var loc = (f.location || "").trim();
      if (!loc || loc === "—") return Promise.reject(new Error("No farm location to geocode."));

      var p = new Promise(function (resolve, reject) {
        setStatus("Geocoding selected farmer…", "Please wait");
        toast("Finding location for " + formatName(f) + "…", "warn");

        geocoder.geocode({ address: loc + ", Philippines" }, function (results, status) {
          if (status === "OK" && results && results[0]) {
            var ll = results[0].geometry.location;
            var marker = createFarmerMarker(f, ll.lat(), ll.lng());
            setStatus("Ready", "Selected farmer located.");
            resolve(marker);
          } else {
            reject(new Error("Could not geocode this farmer (" + status + ")"));
          }
        });
      });

      geocodePromisesById.set(id, p);
      p.finally(function () { geocodePromisesById.delete(id); });

      return p;
    }

    window.__openFarmer3d = function (id) {
      id = String(id);

      ensureMarkerForFarmer(id).then(function (marker) {
        var f = dataById.get(id) || farmersById.get(id);
        if (!marker || !f) return;

        selectedFarmerId = id;
        refreshSavedGuideMarkers();
        selectedVertexIndex = -1;
        
        // This makes the newly selected pin visible and keeps the rest hidden
        refreshAllMarkerPins(); 

        var ll = toLatLng(marker.position);
        flyTo(ll.lat, ll.lng, 9000, 900);

        popover.positionAnchor = marker;
        popover.open = true;

        var html = document.createElement("div");
        html.style.minWidth = "260px";
        html.style.fontFamily = "ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial";
        html.innerHTML =
          '<div style="font-weight:900; font-size:14px; margin-bottom:6px;">' +
            escapeHtml(formatName(f)) +
          '</div>' +
          '<div style="font-size:12px; line-height:1.35; color:#0b1220;">' +
            '<div><strong>FFRS:</strong> ' + escapeHtml(f.ffrs || '—') + '</div>' +
            '<div><strong>DOB:</strong> ' + escapeHtml(f.date_of_birth || '—') + '</div>' +
            '<div><strong>Gender:</strong> ' + escapeHtml(f.gender || '—') + '</div>' +
            '<div style="margin-top:6px;"><strong>Location:</strong> ' + escapeHtml((f.location || '').trim()) + '</div>' +
            '<div style="margin-top:6px;"><strong>Records:</strong> ' + Number(f.records_count || 0) + '</div>' +
            '<div><strong>Total Kgs:</strong> ' + (Number(f.total_kgs || 0)).toFixed(2) + '</div>' +
            '<div><strong>Last Received:</strong> ' + escapeHtml(f.last_received || '—') + '</div>' +
          '</div>';

        popover.replaceChildren(html);
        highlightRow(f.id);
        syncSelectedPanel(f);
        syncWorkflowUi();

        if (hintEl) hintEl.style.display = "none";
        setStatus("Selected: " + (f.last_name || "Farmer"), "Ready");
        toast("Selected " + formatName(f) + ".", "ok");

        loadPlotsForSelectedFarmer(selectedFarmerId, { autoZoom: true }).then(function (plots) {
          syncSuggestedPlotName(plots || [], false);
        });
      }).catch(function (err) {
        console.error(err);
        toast(err && err.message ? err.message : "Could not open farmer.", "bad");
      });
    };

    var geocoded = 0;
    var skipped = 0;

    function updateCounters(done, total, extra) {
      extra = extra || "";
      setStatus("Markers: " + geocoded + " • Skipped: " + skipped + " • Total: " + total, extra);
      if (total > 0) setProgress((done / total) * 100);
      updateGeocodedPill(geocoded, total);
    }

    function geocodeNext(i) {
      if (i >= data.length) {
        updateCounters(data.length, data.length, "Done");
        if (geocoded > 0) setTimeout(function () { fitToMarkers(false); }, 250);
        if (geocoded === 0) setStatus("No locations could be geocoded.", "Check address completeness.");
        toast("Geocoding complete. Plots will render progressively.", "ok");
        return;
      }

      var f = data[i];
      var loc = (f.location || "").trim();

      updateCounters(i, data.length, "Geocoding " + (i + 1) + " / " + data.length);

      if (!loc || loc === "—") {
        skipped++;
        updateCounters(i + 1, data.length, "Skipped missing address");
        return setTimeout(function () { geocodeNext(i + 1); }, 40);
      }

      geocoder.geocode({ address: loc + ", Philippines" }, function (results, status) {
        if (status === "OK" && results && results[0]) {
          var ll = results[0].geometry.location;
          createFarmerMarker(f, ll.lat(), ll.lng());
          geocoded++;
          updateCounters(i + 1, data.length, "Located " + geocoded + " / " + data.length);
          setTimeout(function () { geocodeNext(i + 1); }, 110);
        } else {
          if (status === "OVER_QUERY_LIMIT") {
            updateCounters(i, data.length, "Rate limit… retrying");
            setTimeout(function () { geocodeNext(i); }, 800);
            return;
          }
          skipped++;
          updateCounters(i + 1, data.length, "Could not resolve address");
          setTimeout(function () { geocodeNext(i + 1); }, 60);
        }
      });
    }

    setPlotModeUi(false);
    showPlotButtons(false);
    syncSelectedPanel(null);
    updatePlotCount(0);
    updatePlotTotalArea(0);
    syncWorkflowUi();
    syncSuggestedPlotName([], false);

    setStatus("Loading 3D map…", "Starting geocoding…");
    setProgress(0);
    updateGeocodedPill(0, data.length);

    geocodeNext(0);
  }
</script>
@endpush