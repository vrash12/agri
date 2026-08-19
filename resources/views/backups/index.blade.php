@extends('layouts.app')

@section('title', 'Backup Folder')

@section('content')
@include('partials.operations-ui-styles')
@php
  $formatBytes = function ($bytes) {
    $bytes = max(0, (int) $bytes);
    if ($bytes === 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
    return number_format($bytes / (1024 ** $power), $power > 1 ? 2 : 0).' '.$units[$power];
  };
  $integrityCoverage = $filteredFileCount > 0 ? round(($hashedFileCount / $filteredFileCount) * 100, 1) : 0;
  $latestLabel = $latestUploadAt ? \Illuminate\Support\Carbon::parse($latestUploadAt)->format('M d, Y · h:i A') : 'No uploads yet';
  $activeFilterCount = collect([
    request('search'), request('municipality_id'), request('folder'), request('uploaded_by'),
    request('date_preset'), request('date_from'), request('date_to'), request('size_preset'),
    request('min_mb'), request('max_mb'), count((array) request('exts', [])) ? 'types' : null,
  ])->filter(fn ($value) => filled($value))->count();
  $fileIcon = '<svg viewBox="0 0 24 24"><path d="M7 3h7l4 4v14H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M14 3v5h5"/></svg>';
@endphp

<div class="module-page backup-page">
  @if (session('success'))<div class="module-alert">{{ session('success') }}</div>@endif
  @if (session('error'))<div class="module-alert module-alert-error">{{ session('error') }}</div>@endif
  @if ($errors->any())
    <div class="module-alert module-alert-error"><strong>The upload could not be completed.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif

  <header class="module-header">
    <div>
      <div class="module-eyebrow">Secure document repository</div>
      <h1>Backup folder</h1>
      <p>Store exports, database snapshots, reports, and supporting files with municipality ownership, integrity hashes, and controlled download access.</p>
    </div>
    <div class="module-actions">
      <a class="module-button" href="#backupFiles">
        <svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        Browse files
      </a>
      @can('create', \App\Models\BackupFile::class)
      <button class="module-button module-button-primary" type="button" id="openBackupUpload">
        <svg viewBox="0 0 24 24"><path d="M12 3v12m0-12-4 4m4-4 4 4M5 15v4h14v-4"/></svg>
        Upload files
      </button>
      @else
        <span class="module-badge module-badge-green">Read-only oversight</span>
      @endcan
    </div>
  </header>

  <section class="module-kpis" aria-label="Backup repository summary">
    <article class="module-kpi"><div class="module-kpi-top"><span class="module-kpi-label">Matching files</span><span class="module-kpi-icon"><svg viewBox="0 0 24 24"><path d="M7 3h7l4 4v14H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M14 3v5h5"/></svg></span></div><strong>{{ number_format($filteredFileCount) }}</strong><small>Across {{ number_format($filteredFolderCount) }} folder{{ $filteredFolderCount === 1 ? '' : 's' }}</small></article>
    <article class="module-kpi"><div class="module-kpi-top"><span class="module-kpi-label">Storage represented</span><span class="module-kpi-icon module-kpi-icon-blue"><svg viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v7c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12v7c0 1.7 3.6 3 8 3s8-1.3 8-3v-7"/></svg></span></div><strong>{{ $formatBytes($filteredBytes) }}</strong><small>Combined size of the current result set</small></article>
    <article class="module-kpi"><div class="module-kpi-top"><span class="module-kpi-label">Integrity coverage</span><span class="module-kpi-icon {{ $integrityCoverage < 100 && $filteredFileCount ? 'module-kpi-icon-amber' : '' }}"><svg viewBox="0 0 24 24"><path d="m12 3 8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4Z"/><path d="m9 12 2 2 4-5"/></svg></span></div><strong>{{ number_format($integrityCoverage, 1) }}<small>%</small></strong><small>{{ number_format($hashedFileCount) }} file{{ $hashedFileCount === 1 ? '' : 's' }} with SHA-256</small></article>
    <article class="module-kpi"><div class="module-kpi-top"><span class="module-kpi-label">Latest upload</span><span class="module-kpi-icon module-kpi-icon-amber"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg></span></div><strong class="backup-kpi-date">{{ $latestUploadAt ? \Illuminate\Support\Carbon::parse($latestUploadAt)->format('M d, Y') : '—' }}</strong><small>{{ $latestLabel }}</small></article>
  </section>

  @can('create', \App\Models\BackupFile::class)
  <details class="module-more backup-upload-panel" id="backupUpload" @if($errors->any()) open @endif>
    <summary>Upload backup files <span>Maximum 50 MB per file</span></summary>
    <div class="module-more-content">
      <form class="backup-upload-form" method="POST" action="{{ route('backups.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="module-dropzone backup-dropzone">
          <span class="module-dropzone-icon"><svg viewBox="0 0 24 24"><path d="M12 3v12m0-12-4 4m4-4 4 4M5 15v4h14v-4"/></svg></span>
          <div><strong>Choose one or more files</strong><small id="backupFileSummary">SQL dumps, archives, spreadsheets, PDFs, images, and text files</small><input type="file" id="backupFilesInput" name="files[]" multiple required></div>
        </div>
        <div class="backup-upload-fields">
          @if ($canChooseMunicipality ?? false)
            <div class="module-form-field"><label for="upload_municipality_id">Municipality <span class="module-required">*</span></label><select class="module-input" id="upload_municipality_id" name="municipality_id" required><option value="">Select municipality</option>@foreach(($municipalities ?? []) as $municipality)<option value="{{ $municipality->id }}" @selected((string) old('municipality_id') === (string) $municipality->id)>{{ $municipality->name }}</option>@endforeach</select><div class="module-hint">Controls who can access these files.</div></div>
          @endif
          <div class="module-form-field"><label for="backup_folder">Folder</label><input class="module-input" id="backup_folder" name="folder" value="{{ old('folder') }}" placeholder="e.g. audits/2026"><div class="module-hint">Blank uses {{ now()->format('Y/m') }} automatically.</div></div>
          <div class="module-form-field backup-notes-field"><label for="backup_notes">Notes</label><textarea class="module-input" id="backup_notes" name="notes" rows="3" placeholder="Purpose, source, or restoration context">{{ old('notes') }}</textarea></div>
        </div>
        <div class="backup-upload-actions"><span>SHA-256 is calculated automatically after upload.</span><button class="module-button module-button-primary" type="submit">Upload selected files</button></div>
      </form>
    </div>
  </details>
  @endcan

  <section class="module-panel">
    <div class="module-panel-head"><div><h2>Search the repository</h2><p>Use common filters here; open advanced filters for precise file, date, and size matching.</p></div><span class="module-panel-tag">{{ $activeFilterCount ? $activeFilterCount.' active' : 'All accessible files' }}</span></div>
    <form class="module-filter" id="backupFilters" method="GET" action="{{ route('backups.index') }}">
      <div class="module-filter-grid">
        <div class="module-field module-field-search"><label for="backup_search">Search files</label><div class="module-search-wrap"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input class="module-input" type="search" id="backup_search" name="search" value="{{ request('search') }}" placeholder="Filename, folder, note, or hash"></div></div>
        @if ($canChooseMunicipality ?? false)
          <div class="module-field"><label for="backup_municipality">Municipality</label><select class="module-input" id="backup_municipality" name="municipality_id"><option value="">All municipalities</option>@foreach(($municipalities ?? []) as $municipality)<option value="{{ $municipality->id }}" @selected((string) ($selectedMunicipalityId ?? '') === (string) $municipality->id)>{{ $municipality->name }}</option>@endforeach</select></div>
        @endif
        <div class="module-field"><label for="backup_folder_filter">Folder</label><select class="module-input" id="backup_folder_filter" name="folder"><option value="">All folders</option>@foreach($folders as $folder)<option value="{{ $folder }}" @selected(request('folder') === $folder)>{{ $folder }}</option>@endforeach</select></div>
        <div class="module-field"><label for="backup_sort">Sort by</label><select class="module-input" id="backup_sort" name="sort"><option value="newest" @selected(request('sort','newest')==='newest')>Newest first</option><option value="oldest" @selected(request('sort')==='oldest')>Oldest first</option><option value="name_asc" @selected(request('sort')==='name_asc')>Filename A–Z</option><option value="name_desc" @selected(request('sort')==='name_desc')>Filename Z–A</option><option value="size_desc" @selected(request('sort')==='size_desc')>Largest first</option><option value="size_asc" @selected(request('sort')==='size_asc')>Smallest first</option></select></div>
        <div class="module-field"><label for="backup_per_page">Rows per page</label><select class="module-input" id="backup_per_page" name="per_page">@foreach([10,20,50,100] as $amount)<option value="{{ $amount }}" @selected((int) request('per_page',20) === $amount)>{{ $amount }} rows</option>@endforeach</select></div>
      </div>

      <details class="backup-advanced" @if($activeFilterCount > collect([request('search'),request('municipality_id'),request('folder')])->filter()->count()) open @endif>
        <summary>Advanced filters</summary>
        <div class="backup-advanced-grid">
          <div class="module-field"><label for="search_field">Search field</label><select class="module-input" id="search_field" name="search_field"><option value="all" @selected(request('search_field','all')==='all')>All searchable fields</option><option value="name" @selected(request('search_field')==='name')>Filename only</option><option value="folder" @selected(request('search_field')==='folder')>Folder only</option><option value="notes" @selected(request('search_field')==='notes')>Notes only</option><option value="sha256" @selected(request('search_field')==='sha256')>SHA-256 only</option></select></div>
          <div class="module-field"><label for="search_mode">Match mode</label><select class="module-input" id="search_mode" name="search_mode"><option value="contains" @selected(request('search_mode','contains')==='contains')>Contains</option><option value="starts" @selected(request('search_mode')==='starts')>Starts with</option><option value="ends" @selected(request('search_mode')==='ends')>Ends with</option><option value="exact" @selected(request('search_mode')==='exact')>Exact match</option></select></div>
          <div class="module-field"><label for="backup_uploader">Uploaded by</label><select class="module-input" id="backup_uploader" name="uploaded_by"><option value="">Any uploader</option>@foreach($uploaders as $uploader)<option value="{{ $uploader->id }}" @selected((string) request('uploaded_by') === (string) $uploader->id)>{{ $uploader->name }}</option>@endforeach</select></div>
          <div class="module-field"><label for="date_preset">Upload date</label><select class="module-input" id="date_preset" name="date_preset"><option value="">Any date</option><option value="today" @selected(request('date_preset')==='today')>Today</option><option value="7d" @selected(request('date_preset')==='7d')>Last 7 days</option><option value="30d" @selected(request('date_preset')==='30d')>Last 30 days</option></select></div>
          <div class="module-field"><label for="date_from">Date from</label><input class="module-input" type="date" id="date_from" name="date_from" value="{{ request('date_from') }}"></div>
          <div class="module-field"><label for="date_to">Date to</label><input class="module-input" type="date" id="date_to" name="date_to" value="{{ request('date_to') }}"></div>
          <div class="module-field"><label for="size_preset">File size</label><select class="module-input" id="size_preset" name="size_preset"><option value="">Any size</option><option value="small" @selected(request('size_preset')==='small')>Small · under 1 MB</option><option value="medium" @selected(request('size_preset')==='medium')>Medium · 1–10 MB</option><option value="large" @selected(request('size_preset')==='large')>Large · over 10 MB</option></select></div>
          <div class="module-field"><label for="min_mb">Minimum MB</label><input class="module-input" type="number" min="0" step="0.01" id="min_mb" name="min_mb" value="{{ request('min_mb') }}" placeholder="0"></div>
          <div class="module-field"><label for="max_mb">Maximum MB</label><input class="module-input" type="number" min="0" step="0.01" id="max_mb" name="max_mb" value="{{ request('max_mb') }}" placeholder="50"></div>
          <div class="backup-type-field"><label>File types</label><div class="backup-type-options">@foreach($extPresets as $ext)<label><input type="checkbox" name="exts[]" value="{{ $ext }}" @checked(in_array($ext, (array) request('exts', []), true))><span>.{{ $ext }}</span></label>@endforeach</div></div>
        </div>
      </details>
      <div class="module-filter-actions"><span>{{ number_format($files->total()) }} matching file{{ $files->total() === 1 ? '' : 's' }}</span><div class="module-filter-buttons">@if($activeFilterCount)<a class="module-button" href="{{ route('backups.index') }}">Clear filters</a>@endif<button class="module-button module-button-primary" type="submit">Apply filters</button></div></div>
    </form>
  </section>

  <section class="module-panel" id="backupFiles">
    <div class="module-table-tools"><div><strong>Repository files</strong><span>Open a file for preview or editing; download preserves its original filename.</span></div><span>{{ $formatBytes($filteredBytes) }} in results</span></div>
    <div class="module-table-scroll">
      <table class="module-table backup-table">
        <thead><tr><th>File</th><th>Folder and scope</th><th>File details</th><th>Uploaded</th><th>Integrity</th><th>Notes</th><th style="text-align:right">Actions</th></tr></thead>
        <tbody>
          @forelse($files as $file)
            @php
              $extension = strtolower(pathinfo($file->original_name ?? '', PATHINFO_EXTENSION)) ?: 'file';
              $previewable = true;
            @endphp
            <tr>
              <td><div class="backup-file-cell"><span class="backup-file-icon">{!! $fileIcon !!}<b>{{ strtoupper(substr($extension,0,5)) }}</b></span><span><strong title="{{ $file->original_name }}">{{ $file->original_name }}</strong><small class="module-mono">ID {{ $file->id }} · {{ $extension }}</small></span></div></td>
              <td><strong>{{ $file->folder ?: 'Root' }}</strong><small>{{ optional($file->municipality)->name ?: 'Provincial / unassigned' }}</small></td>
              <td><strong>{{ $formatBytes($file->size) }}</strong><small>{{ $file->mime ?: 'Unknown MIME type' }}</small></td>
              <td><strong>{{ optional($file->created_at)->format('M d, Y') ?: '—' }}</strong><small>{{ optional($file->uploader)->name ?: 'Unknown uploader' }} · {{ optional($file->created_at)->format('h:i A') ?: '—' }}</small></td>
              <td>@if($file->sha256)<span class="module-badge module-badge-green">SHA-256 ready</span><small class="module-mono" title="{{ $file->sha256 }}">{{ substr($file->sha256,0,12) }}…</small>@else<span class="module-badge module-badge-amber">Hash unavailable</span><small>Integrity fingerprint missing</small>@endif</td>
              <td><span class="backup-notes" title="{{ $file->notes }}">{{ $file->notes ?: 'No notes' }}</span><small class="module-mono" title="{{ $file->path }}">{{ $file->path }}</small></td>
              <td><div class="module-row-actions"><a class="module-button module-button-small" href="{{ route('backups.preview', $file) }}">Open</a><a class="module-button module-button-small" href="{{ route('backups.download', $file) }}">Download</a>@can('delete',$file)<details class="module-action-menu"><summary aria-label="More actions">⋯</summary><div class="module-action-menu-list"><a href="{{ route('backups.preview', [$file, 'mode' => 'edit']) }}">Open editor</a><button type="button" class="js-copy-hash" data-hash="{{ $file->sha256 }}" @disabled(!$file->sha256)>Copy SHA-256</button><form method="POST" action="{{ route('backups.destroy', $file) }}" onsubmit="return confirm('Delete this file from backup storage?');">@csrf @method('DELETE')<button class="danger" type="submit">Delete file</button></form></div></details>@endcan</div></td>
            </tr>
          @empty
            <tr><td colspan="7"><div class="module-empty"><span class="module-empty-icon"><svg viewBox="0 0 24 24"><path d="M3 7h7l2 2h9v11H3V7Z"/></svg></span><strong>No backup files match</strong><span>{{ $activeFilterCount ? 'Clear or adjust the repository filters.' : 'No backup files have been uploaded yet.' }}</span>@can('create', \App\Models\BackupFile::class)<button class="module-button module-button-primary" type="button" id="emptyBackupUpload">Upload files</button>@endcan</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @include('partials.pagination', ['paginator' => $files, 'label' => 'file'])
  </section>
</div>
@endsection

@push('styles')
<style>
  .backup-page{scroll-behavior:smooth}.backup-kpi-date{font-size:18px!important;line-height:1.15!important}.backup-upload-panel{scroll-margin-top:16px}.backup-upload-panel>summary span,.backup-advanced>summary span{margin-left:auto;color:var(--module-muted);font-size:9px;font-weight:650}.backup-upload-panel>summary:after{margin-left:8px}
  .backup-upload-form{padding-top:12px}.backup-dropzone>div{display:grid;gap:3px;width:100%}.backup-dropzone strong{color:var(--module-ink);font-size:11px}.backup-dropzone small{color:var(--module-muted);font-size:9px}.backup-dropzone input{margin-top:6px}.backup-upload-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px;margin-top:12px}.backup-notes-field{grid-column:1/-1}.backup-upload-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:12px;padding-top:11px;border-top:1px solid #edf1ee}.backup-upload-actions span{color:var(--module-muted);font-size:9px}
  .backup-advanced{margin-top:12px;border:1px solid var(--module-border);border-radius:8px;background:#fbfcfb}.backup-advanced>summary{padding:10px 12px;color:var(--module-ink);font-size:10px;font-weight:850;cursor:pointer}.backup-advanced-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;padding:0 12px 12px}.backup-advanced-grid .module-field{grid-column:span 1}.backup-type-field{grid-column:1/-1}.backup-type-field>label{display:block;margin-bottom:6px;color:#5d6a62;font-size:9px;font-weight:850;text-transform:uppercase}.backup-type-options{display:flex;gap:6px;flex-wrap:wrap}.backup-type-options label{cursor:pointer}.backup-type-options input{position:absolute;opacity:0;pointer-events:none}.backup-type-options span{display:block;padding:5px 8px;border:1px solid var(--module-border);border-radius:999px;color:var(--module-muted);background:#fff;font-size:8px;font-weight:850}.backup-type-options input:checked+span{color:var(--module-green);border-color:#8fb09c;background:var(--module-green-soft)}
  .backup-table{min-width:1160px}.backup-file-cell{display:flex;align-items:center;gap:10px;min-width:210px}.backup-file-cell>span:last-child{min-width:0}.backup-file-cell strong{display:block;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.backup-file-icon{position:relative;width:37px;height:40px;display:grid;place-items:center;flex:0 0 auto;color:#416b50}.backup-file-icon svg{width:32px;height:36px;fill:#edf5ef;stroke:currentColor;stroke-width:1.4}.backup-file-icon b{position:absolute;bottom:7px;font-size:6px;letter-spacing:.03em}.backup-notes{display:block;max-width:190px;overflow:hidden;color:var(--module-ink);font-weight:750;text-overflow:ellipsis;white-space:nowrap}.module-action-menu-list button:disabled{opacity:.45;cursor:not-allowed}
  @media(max-width:980px){.backup-advanced-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
  @media(max-width:650px){.backup-upload-fields,.backup-advanced-grid{grid-template-columns:1fr}.backup-upload-actions{align-items:stretch;flex-direction:column}.backup-upload-actions .module-button{width:100%}}
</style>
@endpush

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const panel = document.getElementById('backupUpload');
    function openUpload() { if (!panel) return; panel.open = true; panel.scrollIntoView({behavior:'smooth', block:'start'}); setTimeout(() => document.getElementById('backupFilesInput')?.focus(), 350); }
    document.getElementById('openBackupUpload')?.addEventListener('click', openUpload);
    document.getElementById('emptyBackupUpload')?.addEventListener('click', openUpload);

    const input = document.getElementById('backupFilesInput');
    const summary = document.getElementById('backupFileSummary');
    input?.addEventListener('change', function () {
      const files = Array.from(input.files || []);
      if (!files.length) { summary.textContent = 'SQL dumps, archives, spreadsheets, PDFs, images, and text files'; return; }
      const bytes = files.reduce((total, file) => total + file.size, 0);
      const mb = (bytes / (1024 * 1024)).toFixed(2);
      summary.textContent = files.length + ' file' + (files.length === 1 ? '' : 's') + ' selected · ' + mb + ' MB total';
    });

    document.querySelectorAll('.js-copy-hash').forEach(button => button.addEventListener('click', async function () {
      if (!button.dataset.hash) return;
      try { await navigator.clipboard.writeText(button.dataset.hash); button.textContent = 'Copied'; setTimeout(() => button.textContent = 'Copy SHA-256', 1400); }
      catch (error) { window.prompt('Copy SHA-256', button.dataset.hash); }
    }));
  });
</script>
@endpush
