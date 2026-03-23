{{-- resources/views/backups/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Backup Folder')

@push('styles')
  {{-- Editor libs --}}
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/handsontable@14.3.0/dist/handsontable.full.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/eclipse.min.css">

  <style>
    /* ===== Explorer Shell ===== */
    .explorer-shell{
      border: 1px solid var(--border);
      border-radius: 16px;
      overflow: hidden;
      background: #fff;
      box-shadow: 0 14px 40px rgba(2,6,23,.08);
    }

    .explorer-top{
      padding: 14px;
      border-bottom: 1px solid var(--border);
      background: #f8fafc;
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap: 12px;
      flex-wrap: wrap;
    }

    .explorer-title{ display:flex; flex-direction:column; gap:4px; }
    .explorer-title h1{ margin:0; font-size:18px; font-weight:900; }
    .explorer-title .sub{ font-size:12px; color: var(--muted); font-weight:800; }

    .explorer-toolbar{
      display:flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items:end;
      justify-content:flex-end;
    }

    .toolbar-row{ display:flex; gap: 10px; flex-wrap: wrap; align-items:end; }
    .toolbar-group{ display:flex; flex-direction:column; gap: 6px; min-width: 160px; }
    .toolbar-group label{ margin:0; font-size: 12px; font-weight: 900; color: var(--muted); }

    .btn-sm{ padding: 8px 10px; border-radius: 12px; }

    .chips{
      display:flex; flex-wrap:wrap; gap:8px;
      padding: 10px 14px;
      border-bottom: 1px solid var(--border);
      background: #fff;
    }
    .chip{
      display:inline-flex; align-items:center; gap:8px;
      padding: 6px 10px;
      border-radius: 999px;
      border: 1px solid var(--border);
      background: rgba(2,6,23,.02);
      font-size: 12px;
      font-weight: 900;
      color:#0b1220;
    }
    .chip small{ color: var(--muted); font-weight:800; }

    .explorer-body{
      display:grid;
      grid-template-columns: 1fr 420px;
      min-height: 600px;
    }
    @media (max-width: 1100px){
      .explorer-body{ grid-template-columns: 1fr; }
    }

    /* ===== Upload box ===== */
    .upload-card{
      border: 1px solid var(--border);
      border-radius: 16px;
      overflow:hidden;
      background:#fff;
      margin-bottom: 12px;
      box-shadow: 0 14px 40px rgba(2,6,23,.06);
    }
    .upload-head{
      padding: 12px;
      background:#f8fafc;
      border-bottom: 1px solid var(--border);
      font-weight: 900;
      color:#0b1220;
    }
    .upload-body{ padding: 12px; }
    .grid-2{ display:grid; gap: 12px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    @media (max-width: 900px){ .grid-2{ grid-template-columns: 1fr; } }

    /* ===== Left (Icon View) ===== */
    .grid-wrap{
      padding: 14px;
      border-right: 1px solid var(--border);
      background:
        radial-gradient(900px 450px at 15% 10%, rgba(253,230,138,.35) 0%, transparent 55%),
        radial-gradient(900px 450px at 80% 20%, rgba(34,197,94,.14) 0%, transparent 55%),
        #fff;
      position: relative;
    }
    @media (max-width: 1100px){
      .grid-wrap{ border-right: 0; border-bottom: 1px solid var(--border); }
    }

    .group-head{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap: 10px;
      margin: 10px 0 8px;
      padding: 8px 10px;
      border: 1px solid rgba(2,6,23,.10);
      background: rgba(255,255,255,.70);
      border-radius: 14px;
      box-shadow: 0 10px 22px rgba(2,6,23,.05);
    }
    .group-head .left{
      display:flex; align-items:center; gap:10px;
      font-weight: 950;
      color:#0b1220;
    }
    .group-head .count{
      font-size: 12px;
      font-weight: 950;
      color: var(--muted);
    }

    .grid-wrap[data-view="small"]{ --tile: 96px;  --thumb: 58px; --icon: 34px; }
    .grid-wrap[data-view="medium"]{ --tile: 120px; --thumb: 72px; --icon: 42px; }
    .grid-wrap[data-view="large"]{ --tile: 150px; --thumb: 92px; --icon: 52px; }
    .grid-wrap{ --tile: 120px; --thumb: 72px; --icon: 42px; }

    .icon-grid{
      display:grid;
      grid-template-columns: repeat(auto-fill, minmax(var(--tile), var(--tile)));
      gap: 10px 14px;
      justify-content: start;
      align-content: start;
      padding: 4px 2px 14px;
    }

    .icon-tile{
      width: var(--tile);
      border-radius: 10px;
      border: 1px solid transparent;
      background: transparent;
      padding: 6px 6px 8px;
      cursor: default;
      user-select:none;
      outline: none;
    }
    .icon-tile:hover{
      background: rgba(2,6,23,.03);
      border-color: rgba(2,6,23,.06);
    }
    .icon-tile.is-selected{
      background: rgba(59,130,246,.12);
      border-color: rgba(59,130,246,.55);
    }
    .icon-tile.is-primary{
      box-shadow: 0 0 0 2px rgba(59,130,246,.20);
    }

    .thumb{
      width: 100%;
      height: var(--thumb);
      border-radius: 10px;
      border: 1px solid rgba(2,6,23,.10);
      background: rgba(255,255,255,.95);
      display:flex;
      align-items:center;
      justify-content:center;
      position: relative;
      overflow: hidden;
      box-shadow: 0 10px 22px rgba(2,6,23,.06);
    }
    .thumb img{
      width:100%;
      height:100%;
      object-fit: cover;
      display:block;
    }
    .thumb .ext-badge{
      position:absolute;
      right: 6px;
      bottom: 6px;
      font-size: 10px;
      font-weight: 950;
      padding: 3px 7px;
      border-radius: 999px;
      border: 1px solid rgba(2,6,23,.10);
      background: rgba(255,255,255,.92);
      color:#0b1220;
      text-transform: uppercase;
      max-width: 86px;
      overflow:hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .file-label{
      margin-top: 7px;
      text-align: center;
      font-size: 12px;
      font-weight: 900;
      color:#0b1220;
      line-height: 1.2;
      display:-webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      word-break: break-word;
      padding: 0 2px;
    }

    /* ===== Right Pane: Details + Embedded Viewer/Editor ===== */
    .right-pane{ padding: 14px; background:#fff; }
    .pane-card{
      border: 1px solid var(--border);
      border-radius: 16px;
      overflow:hidden;
      background:#fff;
      box-shadow: 0 14px 40px rgba(2,6,23,.08);
      position: sticky;
      top: 14px;
    }
    .pane-head{
      padding: 12px;
      border-bottom: 1px solid var(--border);
      background: #f8fafc;
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap: 10px;
      flex-wrap: wrap;
    }
    .pane-title{ font-size: 13px; font-weight: 950; color:#0b1220; }
    .pane-actions{ display:flex; gap:8px; flex-wrap: wrap; }

    .kv{
      display:grid;
      grid-template-columns: 92px 1fr;
      gap: 10px;
      padding: 8px 0;
      border-bottom: 1px dashed rgba(2,6,23,.10);
    }
    .k{ font-size: 12px; font-weight: 900; color: var(--muted); }
    .v{ font-size: 12px; font-weight: 900; color:#0b1220; word-break: break-word; }
    .mono{
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono","Courier New", monospace;
      font-size: 12px;
    }

    .pane-body{ padding: 12px; display:flex; flex-direction:column; gap: 10px; }

    .toolbar{
      display:flex;
      gap: 8px;
      flex-wrap: wrap;
      justify-content:flex-end;
      align-items:center;
      margin-top: 8px;
    }

    .copy-btn{
      border: 1px solid rgba(2,6,23,.10);
      background: #f8fafc;
      color:#0b1220;
      padding: 8px 10px;
      border-radius: 12px;
      font-weight: 900;
      font-size: 12px;
      cursor: pointer;
    }
    .copy-btn:hover{ background:#f1f5f9; }
    .copy-btn:disabled{ opacity:.55; cursor:not-allowed; }

    .viewer-shell{
      margin-top: 8px;
      border: 1px solid rgba(2,6,23,.10);
      border-radius: 14px;
      overflow:hidden;
      background:#fff;
      box-shadow: 0 12px 28px rgba(2,6,23,.08);
    }
    .viewer-top{
      padding: 10px 10px;
      background:#fff;
      border-bottom: 1px solid rgba(2,6,23,.10);
      display:flex;
      gap: 10px;
      align-items:center;
      justify-content:space-between;
      flex-wrap: wrap;
    }
    .pill{
      display:inline-flex; align-items:center; gap:8px;
      padding: 6px 10px;
      border-radius: 999px;
      border: 1px solid rgba(2,6,23,.10);
      background: rgba(2,6,23,.03);
      font-size: 12px;
      font-weight: 950;
      color:#0b1220;
      max-width: 100%;
      overflow:hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .viewer-actions{ display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }

    .viewer-area{
      height: 420px;
      background:#fff;
      position: relative;
    }

    .vmsg{
      padding: 12px;
      font-weight: 900;
      color: var(--muted);
    }

    .pdf-frame{ width:100%; height:100%; border:0; background:#fff; }
    .img-wrap{
      width:100%; height:100%;
      display:flex; align-items:center; justify-content:center;
      background:#0b1220;
      overflow:auto;
    }
    .img-wrap img{ max-width: 100%; height:auto; background:#fff; display:block; }

    .CodeMirror{ height: 100% !important; font-size: 12px; }
    .hot-wrap{ width:100%; height:100%; overflow:hidden; }
    #hotHost{ width:100%; height:100%; }

    .loading{
      position:absolute;
      inset:0;
      display:none;
      align-items:center;
      justify-content:center;
      background: rgba(255,255,255,.78);
      backdrop-filter: blur(6px);
      z-index: 20;
    }
    .loading .box{
      border: 1px solid rgba(2,6,23,.10);
      background:#fff;
      border-radius: 16px;
      padding: 14px 16px;
      box-shadow: 0 18px 60px rgba(2,6,23,.18);
      font-weight: 950;
      color:#0b1220;
      display:flex;
      gap: 10px;
      align-items:center;
    }
    .dot{
      width: 9px; height: 9px;
      border-radius: 999px;
      background: rgba(59,130,246,.8);
      box-shadow: 0 0 0 6px rgba(59,130,246,.12);
    }

    /* ===== Context Menu ===== */
    .ctx-menu{
      position: fixed;
      z-index: 9999;
      min-width: 230px;
      background: #ffffff;
      border: 1px solid rgba(2,6,23,.12);
      border-radius: 14px;
      box-shadow: 0 18px 50px rgba(2,6,23,.20);
      padding: 8px;
      display:none;
    }
    .ctx-item{
      width:100%;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap: 10px;
      padding: 10px 10px;
      border-radius: 12px;
      border: 0;
      background: transparent;
      font-weight: 900;
      font-size: 12px;
      cursor:pointer;
      color:#0b1220;
      text-align:left;
    }
    .ctx-item:hover{ background: rgba(2,6,23,.04); }
    .ctx-item.danger{ color:#b91c1c; }
    .ctx-item:disabled{ opacity:.55; cursor:not-allowed; }
    .ctx-sep{ height: 1px; background: rgba(2,6,23,.08); margin: 6px 0; }
    .ctx-muted{ font-size: 11px; font-weight: 800; color: var(--muted); }

    /* View buttons */
    .view-btns{ display:flex; gap: 8px; align-items:center; }
    .view-btn{
      border: 1px solid rgba(2,6,23,.10);
      background: #ffffff;
      padding: 8px 10px;
      border-radius: 12px;
      font-weight: 900;
      font-size: 12px;
      cursor: pointer;
    }
    .view-btn:hover{ background: #f8fafc; }
    .view-btn.is-on{
      border-color: rgba(59,130,246,.55);
      background: rgba(59,130,246,.08);
    }
  </style>
@endpush

@section('content')
@php
  $folders     = $folders     ?? [];
  $uploaders   = $uploaders   ?? collect();
  $extPresets  = $extPresets  ?? ['sql','zip','rar','7z','pdf','png','jpg','jpeg','xlsx','xls','csv','doc','docx','txt','log','json','xml','md'];

  $active      = $active      ?? [
    'search' => request('search',''),
    'folder' => request('folder',''),
    'exts'   => (array)request('exts',[]),
    'date_preset' => request('date_preset',''),
    'min_mb' => request('min_mb'),
    'max_mb' => request('max_mb'),
  ];

  $pageItems = $files->getCollection();

  $groupLabel = function($dt){
    if(!$dt) return 'Unknown date';
    try {
      $c = $dt instanceof \Carbon\Carbon ? $dt : \Illuminate\Support\Carbon::parse($dt);
    } catch (\Throwable $e) {
      return 'Unknown date';
    }
    if($c->isToday()) return 'Today';
    if($c->isYesterday()) return 'Yesterday';
    return $c->isCurrentYear() ? $c->format('F j') : $c->format('F j, Y');
  };

  $grouped = $pageItems->groupBy(function($f) use ($groupLabel){
    return $groupLabel(optional($f)->created_at);
  });

  $hasStream  = \Illuminate\Support\Facades\Route::has('backups.stream');
  $hasSave    = \Illuminate\Support\Facades\Route::has('backups.save');

  $svg = [
    'file' => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3h7l3 3v15a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7"/><path d="M14 3v4a1 1 0 0 0 1 1h4" stroke="currentColor" stroke-width="1.7"/></svg>',
    'archive' => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Z" stroke="currentColor" stroke-width="1.7"/><path d="M4 7l2-3h12l2 3" stroke="currentColor" stroke-width="1.7"/><path d="M10 11h4" stroke="currentColor" stroke-width="1.7"/><path d="M12 11v7" stroke="currentColor" stroke-width="1.7"/></svg>',
    'db' => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true"><ellipse cx="12" cy="5" rx="7" ry="3" stroke="currentColor" stroke-width="1.7"/><path d="M5 5v6c0 1.7 3.1 3 7 3s7-1.3 7-3V5" stroke="currentColor" stroke-width="1.7"/><path d="M5 11v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6" stroke="currentColor" stroke-width="1.7"/></svg>',
    'sheet' => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3h10a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7"/><path d="M8 8h8M8 12h8M8 16h8" stroke="currentColor" stroke-width="1.7"/></svg>',
    'pdf' => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3h7l3 3v15a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7"/><path d="M8 14c0-1.1.9-2 2-2h4" stroke="currentColor" stroke-width="1.7"/><path d="M8 18h6" stroke="currentColor" stroke-width="1.7"/></svg>',
    'image' => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="5" width="16" height="14" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M8 14l2-2 3 3 2-2 3 3" stroke="currentColor" stroke-width="1.7"/><path d="M9 9.5h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>',
    'text' => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3h10a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7"/><path d="M8 9h8M8 12h8M8 15h6" stroke="currentColor" stroke-width="1.7"/></svg>',
    'word' => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3h7l3 3v15a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7"/><path d="M8 11l1.2 5L11 11l1.8 5L14 11" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  ];
@endphp

<div class="card">
  <div class="card-header" style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
    <div>
      <h1 class="h1" style="margin:0;">Backup Folder</h1>
      <p class="p" style="margin-top:6px;">
        Windows-style icon explorer: click to select • right-click for menu • edit supported files in the right panel
      </p>
      <p class="p" style="margin-top:6px;"><strong>Results:</strong> {{ method_exists($files,'total') ? $files->total() : count($pageItems) }}</p>
    </div>
  </div>

  <div style="padding:16px;">
    @if(session('success'))
      <div class="flash-success" style="margin-bottom:12px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
      <div class="flash-success" style="margin-bottom:12px; background: var(--dangerBg); border-color: var(--dangerBorder); color: var(--dangerText);">
        {{ session('error') }}
      </div>
    @endif

    {{-- Upload --}}
    <div class="upload-card">
      <div class="upload-head">Upload Backup Files</div>
      <div class="upload-body">
        <form method="POST" action="{{ route('backups.store') }}" enctype="multipart/form-data" class="grid" style="gap:10px;">
          @csrf
          <div class="grid-2">
            <div>
              <label>Files</label>
              <input class="input" type="file" name="files[]" multiple required>
              @error('files') <div style="color:#b91c1c; font-weight:800;">{{ $message }}</div> @enderror
              @error('files.*') <div style="color:#b91c1c; font-weight:800;">{{ $message }}</div> @enderror
              <div class="help">Upload multiple files (SQL, ZIP, reports, etc.).</div>
            </div>
            <div>
              <label>Folder (optional)</label>
              <input class="input" name="folder" placeholder="e.g. 2026/03 or audits" value="{{ old('folder') }}">
              <div class="help">If blank: auto uses {{ now()->format('Y/m') }}</div>
            </div>
          </div>

          <div>
            <label>Notes (optional)</label>
            <textarea class="input" name="notes" rows="2" placeholder="e.g. pre-migration backup">{{ old('notes') }}</textarea>
          </div>

          <div style="display:flex; justify-content:flex-end; gap:10px;">
            <button class="btn" type="submit">Upload</button>
          </div>
        </form>
      </div>
    </div>

    {{-- Explorer --}}
    <div class="explorer-shell">
      <div class="explorer-top">
        <div class="explorer-title">
          <h1>Files</h1>
          <div class="sub">
            Tip: Right-click for actions • Ctrl+E = Edit • Ctrl+S = Save (when editing) • Esc clears
          </div>
        </div>

        {{-- Filter Toolbar --}}
        <form method="GET" action="{{ route('backups.index') }}" class="explorer-toolbar" id="filtersForm">
          <div class="toolbar-row">
            <div class="toolbar-group" style="min-width:260px;">
              <label>Search</label>
              <input class="input" name="search" value="{{ request('search') }}" placeholder="Search backups...">
            </div>

            <div class="toolbar-group" style="min-width:200px;">
              <label>Folder</label>
              <select class="input" name="folder">
                <option value="">All folders</option>
                @foreach($folders as $f)
                  <option value="{{ $f }}" @selected(request('folder') === $f)>{{ $f }}</option>
                @endforeach
              </select>
            </div>

            <div class="toolbar-group" style="min-width:180px;">
              <label>Sort</label>
              @php $sort = request('sort','newest'); @endphp
              <select class="input" name="sort">
                <option value="newest" @selected($sort==='newest')>Newest</option>
                <option value="oldest" @selected($sort==='oldest')>Oldest</option>
                <option value="name_asc" @selected($sort==='name_asc')>Name A–Z</option>
                <option value="name_desc" @selected($sort==='name_desc')>Name Z–A</option>
                <option value="size_desc" @selected($sort==='size_desc')>Largest</option>
                <option value="size_asc" @selected($sort==='size_asc')>Smallest</option>
              </select>
            </div>

            <div class="toolbar-group" style="min-width:120px;">
              <label>Per page</label>
              @php $pp = (int)request('per_page',20); @endphp
              <select class="input" name="per_page">
                <option value="10" @selected($pp===10)>10</option>
                <option value="20" @selected($pp===20)>20</option>
                <option value="50" @selected($pp===50)>50</option>
                <option value="100" @selected($pp===100)>100</option>
              </select>
            </div>

            <div class="toolbar-group" style="min-width:190px;">
              <label>View</label>
              <div class="view-btns">
                <button class="view-btn" type="button" data-view="small">Small</button>
                <button class="view-btn" type="button" data-view="medium">Medium</button>
                <button class="view-btn" type="button" data-view="large">Large</button>
              </div>
            </div>

            <button class="btn btn-soft btn-sm" type="submit">Apply</button>
            <a class="btn btn-soft btn-sm" href="{{ route('backups.index') }}">Clear</a>
          </div>
        </form>
      </div>

      {{-- Active chips --}}
      @php
        $chips = [];
        if(($active['search'] ?? '') !== '') $chips[] = ['k'=>'Search','v'=>$active['search']];
        if(($active['folder'] ?? '') !== '') $chips[] = ['k'=>'Folder','v'=>$active['folder']];
        if(!empty($active['exts'])) $chips[] = ['k'=>'Types','v'=>implode(', ', $active['exts'])];
        if(($active['date_preset'] ?? '') !== '') $chips[] = ['k'=>'Date','v'=>$active['date_preset']];
        if(($active['min_mb'] ?? null) !== null || ($active['max_mb'] ?? null) !== null) $chips[] = ['k'=>'Size','v'=>($active['min_mb'] ?? '—').' → '.($active['max_mb'] ?? '—').' MB'];
      @endphp
      @if(count($chips))
        <div class="chips">
          @foreach($chips as $c)
            <span class="chip"><small>{{ $c['k'] }}:</small> {{ $c['v'] }}</span>
          @endforeach
        </div>
      @endif

      <div class="explorer-body">
        {{-- LEFT: Icon grid --}}
        <div class="grid-wrap" id="gridWrap" data-view="medium">
          @forelse($grouped as $label => $groupFiles)
            <div class="group-head">
              <div class="left">
                <span style="font-size:14px;">📁</span>
                <span>{{ $label }}</span>
              </div>
              <div class="count">{{ $groupFiles->count() }}</div>
            </div>

            <div class="icon-grid" data-group="{{ e($label) }}">
              @foreach($groupFiles as $f)
                @php
                  $name = $f->original_name ?? 'file';
                  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                  $bytes = (int)($f->size ?? 0);
                  $mb = $bytes / (1024*1024);
                  $folder = $f->folder ?: '—';
                  $mime = $f->mime ?? '';
                  $isImage = str_starts_with($mime, 'image/') || in_array($ext, ['png','jpg','jpeg','gif','webp']);

                  $iconKey = 'file';
                  if(in_array($ext, ['zip','rar','7z'])) $iconKey = 'archive';
                  elseif(in_array($ext, ['sql'])) $iconKey = 'db';
                  elseif(in_array($ext, ['xlsx','xls','csv'])) $iconKey = 'sheet';
                  elseif(in_array($ext, ['pdf'])) $iconKey = 'pdf';
                  elseif(in_array($ext, ['txt','log','json','xml','md','csv','sql'])) $iconKey = 'text';
                  elseif(in_array($ext, ['doc','docx'])) $iconKey = 'word';
                  elseif($isImage) $iconKey = 'image';

                  $downloadUrl = route('backups.download', $f->id);
                  $streamUrl = $hasStream ? route('backups.stream', $f->id) : '';
                  $saveUrl = $hasSave ? route('backups.save', $f->id) : '';
                  $thumbUrl = $streamUrl ?: $downloadUrl;
                @endphp

                <div class="icon-tile"
                     tabindex="0"
                     data-id="{{ $f->id }}"
                     data-name="{{ e($name) }}"
                     data-folder="{{ e($folder) }}"
                     data-ext="{{ e($ext) }}"
                     data-bytes="{{ $bytes }}"
                     data-size="{{ number_format($mb, 2) }} MB"
                     data-mime="{{ e($mime ?: '—') }}"
                     data-created="{{ optional($f->created_at)->format('Y-m-d H:i') ?? '—' }}"
                     data-uploader="{{ e(optional($f->uploader)->name ?? '—') }}"
                     data-path="{{ e($f->path) }}"
                     data-download="{{ $downloadUrl }}"
                     data-stream="{{ $streamUrl }}"
                     data-save="{{ $saveUrl }}"
                     data-delete="{{ route('backups.destroy', $f->id) }}"
                >
                  <div class="thumb" aria-hidden="true">
                    @if($isImage && $streamUrl)
                      <img src="{{ $thumbUrl }}"
                           alt=""
                           loading="lazy"
                           onerror="this.style.display='none'; this.parentElement.querySelector('.svgico').style.display='flex';">
                    @endif

                    <div class="svgico" style="display: {{ ($isImage && $streamUrl) ? 'none' : 'flex' }}; align-items:center; justify-content:center; width:100%; height:100%; color:#0b1220;">
                      <span style="font-size: calc(var(--icon) * 1px); line-height: 0; display:flex; align-items:center; justify-content:center;">
                        {!! $svg[$iconKey] ?? $svg['file'] !!}
                      </span>
                    </div>

                    <span class="ext-badge">{{ $ext ?: 'file' }}</span>
                  </div>

                  <div class="file-label" title="{{ $name }}">{{ $name }}</div>
                </div>
              @endforeach
            </div>
          @empty
            <div style="padding: 18px; color: var(--muted); font-weight: 900;">
              No backup files match your filters.
            </div>
          @endforelse

          <div style="margin-top:12px;">
            {{ $files->links() }}
          </div>
        </div>

        {{-- RIGHT: Embedded details + editor --}}
        <aside class="right-pane">
          <div class="pane-card">
            <div class="pane-head">
              <div class="pane-title">Details & Editor</div>
              <div class="pane-actions">
                <button class="btn btn-soft btn-sm" type="button" id="clearSelectionBtn">Clear</button>
              </div>
            </div>

            <div class="pane-body">
              <div class="kv"><div class="k">Name</div><div class="v" id="dName">—</div></div>
              <div class="kv"><div class="k">Folder</div><div class="v" id="dFolder">—</div></div>
              <div class="kv"><div class="k">Type</div><div class="v" id="dExt">—</div></div>
              <div class="kv"><div class="k">Size</div><div class="v" id="dSize">—</div></div>
              <div class="kv"><div class="k">Uploaded</div><div class="v" id="dCreated">—</div></div>
              <div class="kv"><div class="k">Uploader</div><div class="v" id="dUploader">—</div></div>
              <div class="kv"><div class="k">Path</div><div class="v mono" id="dPath">—</div></div>

              <div class="toolbar">
                <button class="copy-btn" type="button" id="copyPathBtn" disabled>Copy Path</button>
                <a class="btn btn-soft btn-sm" href="#" id="openTabBtn" target="_blank" rel="noopener" style="display:none;">Open Tab</a>
                <a class="btn btn-soft btn-sm" href="#" id="downloadBtn" style="display:none;">Download</a>
                <button class="btn btn-soft btn-sm" type="button" id="editToggleBtn" disabled>Edit</button>
                <button class="btn btn-sm" type="button" id="saveBtn" disabled style="background: rgba(34,197,94,.12); border-color: rgba(34,197,94,.28);">Save</button>
                <button class="btn btn-danger btn-sm" type="button" id="deleteBtn" disabled>Delete</button>
              </div>

              <div class="viewer-shell">
                <div class="viewer-top">
                  <span class="pill" id="viewerStatus">No file selected</span>
                  <div class="viewer-actions">
                    <span class="pill" id="modePill" style="display:none;">View</span>
                  </div>
                </div>
                <div class="viewer-area" id="viewerArea">
                  <div class="loading" id="loading">
                    <div class="box"><span class="dot"></span> Working…</div>
                  </div>
                  <div class="vmsg" id="viewerMsg">Select a file to preview and edit here.</div>
                </div>
              </div>

              <div class="help" id="detailsHint">
                Supported editing: txt/log/sql/csv/json/xml/md and xlsx (requires stream + save routes).
              </div>
            </div>
          </div>
        </aside>
      </div>
    </div>
  </div>
</div>

{{-- Context Menu --}}
<div id="ctxMenu" class="ctx-menu" role="menu" aria-label="File actions">
  <button class="ctx-item" type="button" data-action="open">
    Open in pane <span class="ctx-muted">Enter</span>
  </button>
  <button class="ctx-item" type="button" data-action="edit" id="ctxEdit">
    Edit <span class="ctx-muted">Ctrl+E</span>
  </button>
  <button class="ctx-item" type="button" data-action="save" id="ctxSave">
    Save <span class="ctx-muted">Ctrl+S</span>
  </button>
  <div class="ctx-sep"></div>
  <button class="ctx-item" type="button" data-action="download">Download</button>
  <button class="ctx-item" type="button" data-action="copyPath">Copy Path</button>
  <div class="ctx-sep"></div>
  <button class="ctx-item danger" type="button" data-action="delete">Delete</button>
</div>

<form id="deleteForm" method="POST" style="display:none;">
  @csrf
  @method('DELETE')
</form>
@endsection
@push('scripts')
  {{-- libs --}}
  <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/handsontable@14.3.0/dist/handsontable.full.min.js"></script>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/sql/sql.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/markdown/markdown.min.js"></script>

  {{-- Required for DOCX Preview --}}
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/docx-preview@0.1.15/dist/docx-preview.min.js"></script>

<script>
(function () {
  // ---------- Helpers ----------
  function toast(msg){
    if (window.__mapToast) { window.__mapToast(msg, 'ok'); return; }
    console.log(msg);
  }

  function setLoading(on){
    loading.style.display = on ? 'flex' : 'none';
  }

  async function copyText(text){
    if(!text) return;
    try { await navigator.clipboard.writeText(text); toast('Copied ✅'); }
    catch(e){
      var ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
      toast('Copied ✅');
    }
  }

  function bytesToMB(bytes){
    var mb = (bytes || 0) / (1024*1024);
    return (Math.round(mb * 100) / 100).toFixed(2) + ' MB';
  }

  function extOf(name){
    name = (name || '').toLowerCase();
    var idx = name.lastIndexOf('.');
    return idx >= 0 ? name.slice(idx+1) : '';
  }

  function isEditableText(ext){
    return ['txt','log','sql','csv','json','xml','md'].includes(ext);
  }
  function isEditableExcel(ext){
    return ['xlsx'].includes(ext);
  }

  function guessMode(ext){
    if(ext === 'sql') return 'text/x-sql';
    if(ext === 'json') return {name:'javascript', json:true};
    if(ext === 'xml') return 'application/xml';
    if(ext === 'md') return 'text/x-markdown';
    return null;
  }

  function confirmLoseChanges(){
    if(!dirty) return true;
    return confirm('You have unsaved changes. Discard them?');
  }

  // ---------- View size controls ----------
  var gridWrap = document.getElementById('gridWrap');
  var viewBtns = Array.from(document.querySelectorAll('.view-btn[data-view]'));
  function setView(v){
    if(!gridWrap) return;
    gridWrap.setAttribute('data-view', v);
    try { localStorage.setItem('backup_view', v); } catch(e){}
    viewBtns.forEach(function(b){
      b.classList.toggle('is-on', b.getAttribute('data-view') === v);
    });
  }
  (function initView(){
    var v = 'medium';
    try { v = localStorage.getItem('backup_view') || 'medium'; } catch(e){}
    setView(v);
    viewBtns.forEach(function(btn){
      btn.addEventListener('click', function(){
        setView(btn.getAttribute('data-view'));
      });
    });
  })();

  // ---------- DOM refs ----------
  var items = gridWrap ? Array.from(gridWrap.querySelectorAll('.icon-tile')) : [];
  var selected = null;

  // Details
  var dName = document.getElementById('dName');
  var dFolder = document.getElementById('dFolder');
  var dExt = document.getElementById('dExt');
  var dSize = document.getElementById('dSize');
  var dCreated = document.getElementById('dCreated');
  var dUploader = document.getElementById('dUploader');
  var dPath = document.getElementById('dPath');

  // Buttons
  var clearBtn = document.getElementById('clearSelectionBtn');
  var copyPathBtn = document.getElementById('copyPathBtn');
  var openTabBtn = document.getElementById('openTabBtn');
  var downloadBtn = document.getElementById('downloadBtn');
  var editToggleBtn = document.getElementById('editToggleBtn');
  var saveBtn = document.getElementById('saveBtn');
  var deleteBtn = document.getElementById('deleteBtn');

  // Viewer
  var viewerArea = document.getElementById('viewerArea');
  var viewerMsg = document.getElementById('viewerMsg');
  var viewerStatus = document.getElementById('viewerStatus');
  var modePill = document.getElementById('modePill');
  var loading = document.getElementById('loading');

  // Context menu
  var ctx = document.getElementById('ctxMenu');
  var ctxEdit = document.getElementById('ctxEdit');
  var ctxSave = document.getElementById('ctxSave');

  // Delete form
  var deleteForm = document.getElementById('deleteForm');

  // ---------- Editor state ----------
  var dirty = false;
  var editMode = false;

  var cm = null;         // CodeMirror instance
  var hot = null;        // Handsontable instance
  var wb = null;         // SheetJS workbook (for xlsx)
  var activeSheet = null;

  function resetEditors(){
    dirty = false;
    editMode = false;
    cm = null;
    hot = null;
    wb = null;
    activeSheet = null;
    modePill.style.display = 'none';
    modePill.textContent = '';
  }

  function setDirty(on){
    dirty = !!on;
    if(dirty){
      modePill.textContent = editMode ? 'Edit • Unsaved' : 'View • Unsaved';
    } else {
      modePill.textContent = editMode ? 'Edit' : 'View';
    }
  }

  function setActionStates(enabled){
    copyPathBtn.disabled = !enabled;
    deleteBtn.disabled = !enabled;
    downloadBtn.style.display = enabled ? '' : 'none';
    openTabBtn.style.display = enabled ? '' : 'none';

    // edit/save depends on file type + routes
    editToggleBtn.disabled = true;
    saveBtn.disabled = true;
  }

  function clearSelection(){
    if(!confirmLoseChanges()) return;

    items.forEach(function(el){ el.classList.remove('is-selected'); });
    selected = null;
    resetEditors();

    dName.textContent = '—';
    dFolder.textContent = '—';
    dExt.textContent = '—';
    dSize.textContent = '—';
    dCreated.textContent = '—';
    dUploader.textContent = '—';
    dPath.textContent = '—';

    viewerStatus.textContent = 'No file selected';
    viewerArea.innerHTML = '<div class="loading" id="loading"><div class="box"><span class="dot"></span> Working…</div></div><div class="vmsg" id="viewerMsg">Select a file to preview and edit here.</div>';
    // rebind refs (since innerHTML reset)
    loading = document.getElementById('loading');
    viewerMsg = document.getElementById('viewerMsg');

    openTabBtn.setAttribute('href', '#');
    downloadBtn.setAttribute('href', '#');

    editToggleBtn.textContent = 'Edit';

    setActionStates(false);
  }

  function selectItem(el){
    if(!el) return;
    if(selected === el) return;

    if(selected && !confirmLoseChanges()) return;

    items.forEach(function(it){ it.classList.remove('is-selected'); });
    el.classList.add('is-selected');
    selected = el;

    resetEditors();

    dName.textContent = el.dataset.name || '—';
    dFolder.textContent = el.dataset.folder || '—';
    dExt.textContent = (el.dataset.ext || 'file').toUpperCase();
    dSize.textContent = el.dataset.size || '—';
    dCreated.textContent = el.dataset.created || '—';
    dUploader.textContent = el.dataset.uploader || '—';
    dPath.textContent = el.dataset.path || '—';

    copyPathBtn.disabled = !(el.dataset.path);
    deleteBtn.disabled = false;

    downloadBtn.style.display = '';
    downloadBtn.setAttribute('href', el.dataset.download || '#');

    openTabBtn.style.display = '';
    openTabBtn.setAttribute('href', el.dataset.stream || el.dataset.download || '#');

    // Load embedded preview automatically
    loadIntoPane(el, false);
  }

  // ---------- Embedded rendering ----------
  async function loadIntoPane(el, forceEdit){
    if(!el) return;

    var name = el.dataset.name || '';
    var ext = (el.dataset.ext || extOf(name) || '').toLowerCase();
    var mime = (el.dataset.mime || '').toLowerCase();
    var streamUrl = el.dataset.stream || '';
    var canStream = !!streamUrl;
    var canSave = !!(el.dataset.save || '');
    var saveUrl = el.dataset.save || '';

    viewerStatus.textContent = name || 'Selected file';
    modePill.style.display = '';
    editMode = !!forceEdit;
    modePill.textContent = editMode ? 'Edit' : 'View';
    setDirty(false);

    // Determine type
    var isPdf = ext === 'pdf' || mime.includes('pdf');
    var isImage = mime.startsWith('image/') || ['png','jpg','jpeg','gif','webp'].includes(ext);
    var isDocx = ext === 'docx';
    var isText = isEditableText(ext);
    var isXlsx = isEditableExcel(ext);

    // Edit availability
    var editable = (isText || isXlsx) && canStream && canSave;
    editToggleBtn.disabled = !editable;
    saveBtn.disabled = !editable;

    if(!canStream){
      viewerArea.innerHTML = '<div class="vmsg">Preview requires <strong>backups.stream</strong> route. Currently missing.</div>';
      return;
    }

    setLoading(true);
    try{
      // Clear area
      viewerArea.innerHTML = `
        <div class="loading" id="loading"><div class="box"><span class="dot"></span> Working…</div></div>
        <div id="innerHost" style="width:100%;height:100%;"></div>
      `;
      loading = document.getElementById('loading');
      var innerHost = document.getElementById('innerHost');

      // PDF
      if(isPdf){
        editMode = false;
        modePill.textContent = 'View';
        editToggleBtn.textContent = 'Edit';
        editToggleBtn.disabled = true;
        saveBtn.disabled = true;

        innerHost.innerHTML = `<iframe class="pdf-frame" src="${streamUrl}#toolbar=1&navpanes=0&scrollbar=1"></iframe>`;
        return;
      }

      // Image
      if(isImage){
        editMode = false;
        modePill.textContent = 'View';
        editToggleBtn.textContent = 'Edit';
        editToggleBtn.disabled = true;
        saveBtn.disabled = true;

        innerHost.innerHTML = `<div class="img-wrap"><img src="${streamUrl}" alt=""></div>`;
        return;
      }

     // DOCX preview
      if(isDocx){
        editMode = false;
        modePill.textContent = 'View';
        editToggleBtn.textContent = 'Edit';
        editToggleBtn.disabled = true;
        saveBtn.disabled = true;

        // Container takes up 100% of the space
        innerHost.innerHTML = `<div id="docxHost" style="width: 100%; height: 100%; overflow: auto; background: #e2e8f0;"></div>`;
        
        try {
          var res = await fetch(streamUrl, { credentials:'same-origin' });
          if(!res.ok) throw new Error('HTTP ' + res.status);
          
          var buf = await res.arrayBuffer();
          
          // Render the document into the container with explicit options
          await window.docx.renderAsync(buf, document.getElementById('docxHost'), null, {
            className: "docx", 
            inWrapper: true, 
            ignoreWidth: false, 
            ignoreHeight: false, 
            debug: true // This will print helpful logs to the browser console
          });
        } catch (e) {
          console.error("Error loading DOCX:", e);
          document.getElementById('docxHost').innerHTML = `<div class="vmsg" style="color:#b91c1c;">Failed to render document. Please check the browser console for details.</div>`;
        }
        return;
      }

      // TEXT (CodeMirror)
      if(isText){
        var resT = await fetch(streamUrl, { credentials:'same-origin' });
        if(!resT.ok) throw new Error('HTTP ' + resT.status);
        var text = await resT.text();

        innerHost.innerHTML = `<div id="cmHost" style="height:100%;"></div>`;
        cm = CodeMirror(document.getElementById('cmHost'), {
          value: text || '',
          lineNumbers: true,
          theme: 'eclipse',
          mode: guessMode(ext),
          readOnly: !editMode,
        });

        cm.on('change', function(){
          if(editMode) setDirty(true);
        });

        editToggleBtn.textContent = editMode ? 'View' : 'Edit';
        modePill.textContent = editMode ? 'Edit' : 'View';
        return;
      }

      // XLSX (Handsontable + SheetJS)
      if(isXlsx){
        var resX = await fetch(streamUrl, { credentials:'same-origin' });
        if(!resX.ok) throw new Error('HTTP ' + resX.status);
        var ab = await resX.arrayBuffer();

        wb = XLSX.read(ab, { type:'array' });
        activeSheet = wb.SheetNames[0];

        innerHost.innerHTML = `
          <div style="height:100%; display:flex; flex-direction:column;">
            <div style="padding:10px 10px; border-bottom:1px solid rgba(2,6,23,.10); display:flex; gap:8px; flex-wrap:wrap; align-items:center; justify-content:space-between;">
              <div id="xlTabs" style="display:flex; gap:8px; flex-wrap:wrap;"></div>
              <span class="pill">${editMode ? 'Editable sheet' : 'Read-only'}</span>
            </div>
            <div class="hot-wrap"><div id="hotHost"></div></div>
          </div>
        `;

        function sheetTo2D(ws){
          const data = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '' });
          if(!data.length) return [[]];
          const maxCols = Math.max(...data.map(r => r.length));
          return data.map(r => {
            const rr = r.slice(0);
            while(rr.length < maxCols) rr.push('');
            return rr;
          });
        }

        function loadSheet(){
          const ws = wb.Sheets[activeSheet];
          const data = sheetTo2D(ws);

          if(hot){
            hot.loadData(data);
            hot.updateSettings({ readOnly: !editMode });
            hot.render();
            return;
          }

          hot = new Handsontable(document.getElementById('hotHost'), {
            data,
            rowHeaders: true,
            colHeaders: true,
            contextMenu: editMode,
            dropdownMenu: editMode,
            filters: true,
            manualColumnResize: true,
            manualRowResize: true,
            licenseKey: 'non-commercial-and-evaluation',
            readOnly: !editMode,
          });

          hot.addHook('afterChange', function(changes, src){
            if(editMode && src !== 'loadData' && changes) setDirty(true);
          });
        }

        function renderTabs(){
          const tabs = document.getElementById('xlTabs');
          tabs.innerHTML = '';
          wb.SheetNames.forEach(function(name){
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'view-btn ' + (name === activeSheet ? 'is-on' : '');
            b.textContent = name;
            b.addEventListener('click', function(){
              if(dirty && !confirm('Switch sheet and lose unsaved changes for current sheet?')) return;
              setDirty(false);
              activeSheet = name;
              renderTabs();
              loadSheet();
            });
            tabs.appendChild(b);
          });
        }

        renderTabs();
        loadSheet();

        editToggleBtn.textContent = editMode ? 'View' : 'Edit';
        modePill.textContent = editMode ? 'Edit' : 'View';
        return;
      }

      innerHost.innerHTML = `<div class="vmsg">No preview available for this file type.</div>`;
      editToggleBtn.disabled = true;
      saveBtn.disabled = true;
      editToggleBtn.textContent = 'Edit';
      modePill.textContent = 'View';
    } finally {
      setLoading(false);
    }
  }

  // ---------- Save ----------
  async function saveCurrent(){
    if(!selected) return;

    var name = selected.dataset.name || '';
    var ext = (selected.dataset.ext || extOf(name) || '').toLowerCase();
    var saveUrl = selected.dataset.save || '';
    var streamUrl = selected.dataset.stream || '';

    if(!saveUrl){
      alert('Save route not available (backups.save).');
      return;
    }
    if(!streamUrl){
      alert('Stream route not available (backups.stream).');
      return;
    }

    if(!editMode){
      toast('Switch to Edit mode first.');
      return;
    }

    // TEXT
    if(isEditableText(ext)){
      if(!cm){ alert('Editor not ready.'); return; }

      setLoading(true);
      try{
        var fd = new FormData();
        fd.append('kind', 'text');
        fd.append('content', cm.getValue());

        var res = await fetch(saveUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
          body: fd
        });

        if(!res.ok) throw new Error('HTTP ' + res.status);
        setDirty(false);
        toast('Saved ✅');
      } catch(e){
        alert('Save failed: ' + (e.message || e));
      } finally {
        setLoading(false);
      }
      return;
    }

    // XLSX
    if(isEditableExcel(ext)){
      if(!wb || !hot){ alert('Excel editor not ready.'); return; }

      setLoading(true);
      try{
        // write grid back into current sheet (loses formatting)
        var data = hot.getData();
        wb.Sheets[activeSheet] = XLSX.utils.aoa_to_sheet(data);

        var out = XLSX.write(wb, { bookType:'xlsx', type:'array' });
        var blob = new Blob([out], { type:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });

        var fd2 = new FormData();
        fd2.append('kind', 'xlsx');
        fd2.append('file', blob, 'edited.xlsx');

        var res2 = await fetch(saveUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
          body: fd2
        });

        if(!res2.ok) throw new Error('HTTP ' + res2.status);
        setDirty(false);
        toast('Saved ✅');
      } catch(e){
        alert('Save failed: ' + (e.message || e));
      } finally {
        setLoading(false);
      }
      return;
    }

    toast('This file type is not editable here.');
  }

  function toggleEdit(){
    if(!selected) return;
    var name = selected.dataset.name || '';
    var ext = (selected.dataset.ext || extOf(name) || '').toLowerCase();

    // only text/xlsx
    if(!(isEditableText(ext) || isEditableExcel(ext))) return;

    // require routes
    if(!selected.dataset.stream || !selected.dataset.save){
      alert('Editing requires backups.stream and backups.save routes.');
      return;
    }

    // switching off edit with dirty
    if(editMode && dirty){
      if(!confirm('Discard unsaved changes and switch to View mode?')) return;
      setDirty(false);
    }

    editMode = !editMode;
    modePill.style.display = '';
    modePill.textContent = editMode ? 'Edit' : 'View';
    editToggleBtn.textContent = editMode ? 'View' : 'Edit';

    // apply readOnly to current editor
    if(cm){
      cm.setOption('readOnly', !editMode);
    }
    if(hot){
      hot.updateSettings({ readOnly: !editMode, contextMenu: editMode, dropdownMenu: editMode });
      hot.render();
    }
  }

  // ---------- Delete ----------
  function confirmDelete(){
    if(!selected) return;
    var name = selected.dataset.name || 'this file';
    if(!confirm('Delete "' + name + '"?')) return;
    var action = selected.dataset.delete;
    if(!action) return;
    deleteForm.setAttribute('action', action);
    deleteForm.submit();
  }

  // ---------- Context menu ----------
  function hideContext(){ ctx.style.display = 'none'; }
  function showContext(el, x, y){
    if(!el) return;
    selectItem(el);

    // toggle edit/save availability in menu
    var name = el.dataset.name || '';
    var ext = (el.dataset.ext || extOf(name) || '').toLowerCase();
    var editable = (isEditableText(ext) || isEditableExcel(ext)) && el.dataset.stream && el.dataset.save;

    ctxEdit.style.display = editable ? '' : 'none';
    ctxSave.style.display = editable ? '' : 'none';

    ctx.style.display = 'block';
    var w = ctx.offsetWidth;
    var h = ctx.offsetHeight;
    var vx = Math.min(x, window.innerWidth - w - 8);
    var vy = Math.min(y, window.innerHeight - h - 8);
    ctx.style.left = vx + 'px';
    ctx.style.top  = vy + 'px';
  }

  // ---------- Bind items ----------
  items.forEach(function(el){
    el.addEventListener('click', function(){
      hideContext();
      selectItem(el);
    });

    el.addEventListener('dblclick', function(){
      hideContext();
      // double-click just focuses in pane (already loaded). If you want: toggle edit on dblclick for editable:
      // toggleEdit();
    });

    el.addEventListener('contextmenu', function(e){
      e.preventDefault();
      showContext(el, e.clientX, e.clientY);
    });

    el.addEventListener('keydown', function(e){
      if(e.key === 'Enter'){
        e.preventDefault();
        hideContext();
        selectItem(el);
      }
      if(e.key === 'Escape'){
        e.preventDefault();
        hideContext();
        clearSelection();
      }
    });
  });

  // Click empty area clears selection
  if(gridWrap){
    gridWrap.addEventListener('click', function(e){
      if(!e.target.closest('.icon-tile')){
        hideContext();
        clearSelection();
      }
    });
  }

  // Context menu click actions
  ctx.addEventListener('click', function(e){
    var btn = e.target.closest('button[data-action]');
    if(!btn) return;
    var act = btn.getAttribute('data-action');
    hideContext();

    if(!selected) return;

    if(act === 'open'){
      loadIntoPane(selected, false);
    } else if(act === 'edit'){
      toggleEdit();
    } else if(act === 'save'){
      saveCurrent();
    } else if(act === 'download'){
      window.location.href = selected.dataset.download || '#';
    } else if(act === 'copyPath'){
      copyText(selected.dataset.path || '');
    } else if(act === 'delete'){
      confirmDelete();
    }
  });

  // Outside click closes menu
  document.addEventListener('click', function(e){
    if(ctx.style.display === 'block' && !ctx.contains(e.target)){
      hideContext();
    }
  });
  window.addEventListener('scroll', hideContext, true);

  // Buttons
  clearBtn.addEventListener('click', function(){ hideContext(); clearSelection(); });

  copyPathBtn.addEventListener('click', function(){
    if(selected) copyText(selected.dataset.path || '');
  });

  editToggleBtn.addEventListener('click', function(){ toggleEdit(); });
  saveBtn.addEventListener('click', function(){ saveCurrent(); });
  deleteBtn.addEventListener('click', function(){ confirmDelete(); });

  // Global shortcuts
  document.addEventListener('keydown', function(e){
    // Ctrl+E toggle edit
    if((e.ctrlKey || e.metaKey) && (e.key === 'e' || e.key === 'E')){
      if(selected){
        e.preventDefault();
        toggleEdit();
      }
    }

    // Ctrl+S save
    if((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')){
      if(selected){
        e.preventDefault();
        saveCurrent();
      }
    }

    if(e.key === 'Escape'){
      hideContext();
      // optional: don’t auto-clear if editing (you can keep it)
      // clearSelection();
    }
  });

  // Initial state
  clearSelection();
})();
</script>
@endpush