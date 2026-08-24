{{-- resources/views/backups/preview.blade.php --}}
@extends('layouts.app')

@section('title', 'Preview Backup File')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/handsontable@14.3.0/dist/handsontable.full.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/eclipse.min.css">

<style>
  .pv-shell{
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow:hidden;
    background:#fff;
    box-shadow: 0 14px 40px rgba(2,6,23,.08);
  }
  .pv-top{
    padding: 12px 14px;
    border-bottom: 1px solid var(--border);
    background:#f8fafc;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap: 12px;
    flex-wrap: wrap;
  }
  .pv-title{ display:flex; flex-direction:column; gap:4px; min-width: 260px; }
  .pv-title h1{ margin:0; font-size:16px; font-weight:950; color:#0b1220; }
  .pv-title .sub{ font-size:12px; font-weight:800; color: var(--muted); }

  .pv-actions{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:flex-end; }
  .pv-actions .btn-sm{ padding: 8px 10px; border-radius: 12px; }

  .pv-body{
    display:grid;
    grid-template-columns: 1fr 340px;
    min-height: 620px;
  }
  @media (max-width: 980px){
    .pv-body{ grid-template-columns: 1fr; }
  }

  .pv-view{
    border-right: 1px solid var(--border);
    background:#fff;
    position: relative;
    padding: 14px;
  }
  @media (max-width: 980px){
    .pv-view{ border-right:0; border-bottom: 1px solid var(--border); }
  }

  .viewer-card{
    border: 1px solid rgba(2,6,23,.10);
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 14px 36px rgba(2,6,23,.08);
    overflow:hidden;
    min-height: 560px;
    position: relative;
  }

  .viewer-toolbar{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap: 10px;
    padding: 10px 12px;
    border-bottom: 1px solid rgba(2,6,23,.10);
    background: #fff;
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
    max-width: 520px;
    overflow:hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .viewer-area{
    height: calc(560px - 46px);
    min-height: 520px;
    background: #fff;
    position: relative;
  }

  .pdf-frame{ width:100%; height:100%; border:0; background:#fff; }

  .img-wrap{
    width:100%;
    height:100%;
    overflow:auto;
    background:#0b1220;
    display:flex;
    align-items:center;
    justify-content:center;
  }
  .img-wrap img{ max-width: 100%; height:auto; display:block; background:#fff; }

  .text-wrap{ width:100%; height:100%; }
  .CodeMirror{ height: 100% !important; font-size: 12px; }

  .xl-wrap{ width:100%; height:100%; overflow:hidden; }
  .xl-topbar{
    border-bottom: 1px solid rgba(2,6,23,.10);
    padding: 10px 12px;
    display:flex;
    gap: 10px;
    align-items:center;
    justify-content:space-between;
    flex-wrap: wrap;
    background:#fff;
  }
  .xl-tabs{ display:flex; gap: 8px; flex-wrap: wrap; }
  .xl-tab{
    border: 1px solid rgba(2,6,23,.10);
    background:#fff;
    padding: 7px 10px;
    border-radius: 999px;
    font-weight: 950;
    font-size: 12px;
    cursor:pointer;
  }
  .xl-tab.is-on{
    border-color: rgba(59,130,246,.55);
    background: rgba(59,130,246,.08);
  }
  #hotHost{ height: calc(100% - 56px); }

  .pv-info{ padding: 14px; background:#fff; }
  .info-card{
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow:hidden;
    background:#fff;
    box-shadow: 0 14px 40px rgba(2,6,23,.08);
    position: sticky;
    top: 14px;
  }
  .info-head{
    padding: 12px;
    background:#f8fafc;
    border-bottom: 1px solid var(--border);
    font-weight: 950;
    color:#0b1220;
  }
  .info-body{
    padding: 12px;
    display:flex;
    flex-direction:column;
    gap: 10px;
  }
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
  .info-actions{
    display:flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content:flex-end;
    margin-top: 8px;
  }
  .copy-btn{
    border: 1px solid rgba(2,6,23,.10);
    background: #f8fafc;
    color:#0b1220;
    padding: 8px 10px;
    border-radius: 12px;
    font-weight: 950;
    font-size: 12px;
    cursor: pointer;
  }
  .copy-btn:hover{ background:#f1f5f9; }
  .copy-btn:disabled{ opacity:.55; cursor:not-allowed; }

  .pv-loading{
    position:absolute;
    inset:0;
    display:none;
    align-items:center;
    justify-content:center;
    background: rgba(255,255,255,.78);
    backdrop-filter: blur(6px);
    z-index: 20;
  }
  .pv-loading .box{
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

  .pv-msg{ padding: 14px; font-weight: 900; color: var(--muted); }
</style>
@endpush

@section('content')
@include('partials.operations-ui-styles')
@php
  $name = $file->original_name ?? 'file';
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  $mime = $file->mime ?? '';
  $bytes = (int)($file->size ?? 0);
  $mb = $bytes ? ($bytes / (1024*1024)) : 0;
  $folder = $file->folder ?: '—';

  $mode = request('mode','view'); // view | edit
  $canEditText = in_array($ext, ['txt','log','sql','csv','json','xml','md']);
  $canEditExcel = in_array($ext, ['xlsx']); // keep simple
  $isPdf = ($ext === 'pdf') || str_contains($mime, 'pdf');
  $isImage = str_starts_with($mime, 'image/') || in_array($ext, ['png','jpg','jpeg','gif','webp']);
  $isAudio = str_starts_with($mime, 'audio/') || in_array($ext, ['mp3','wav','ogg','m4a','aac','flac']);
  $isVideo = str_starts_with($mime, 'video/') || in_array($ext, ['mp4','webm','mov','m4v']);
  $isWord  = in_array($ext, ['docx']);
  $isText = $canEditText || in_array($ext, ['txt','log','sql','csv','json','xml','md']);
  $isExcel = $canEditExcel;

  $streamUrl = route('backups.stream', $file->id);
  $downloadUrl = route('backups.download', $file->id);
  $saveUrl = route('backups.save', $file->id);
  $recordVersion = \App\Support\ConcurrentWrite::version($file);
@endphp

<div class="module-page backup-preview-page">
  <header class="module-header">
    <div>
      <div class="module-eyebrow">Backup repository · {{ $mode === 'edit' ? 'Editor' : 'Preview' }}</div>
      <h1>{{ $name }}</h1>
      <p>{{ strtoupper($ext ?: 'FILE') }} · {{ number_format($mb, 2) }} MB · {{ $folder }}{{ optional($file->municipality)->name ? ' · '.optional($file->municipality)->name : '' }}</p>
    </div>

    <div class="module-actions">
      <a class="module-button" href="{{ route('backups.index') }}">Back to files</a>

      @can('update', $file)
      @if($mode !== 'edit' && ($canEditText || $canEditExcel))
        <a class="module-button" href="{{ route('backups.preview', $file->id) }}?mode=edit">Edit file</a>
      @endif

      @if($mode === 'edit')
        <button class="module-button module-button-primary" type="button" id="saveBtn">Save changes</button>
      @endif
      @endcan

      <a class="module-button" href="{{ $streamUrl }}" target="_blank" rel="noopener">Open raw file</a>
      <a class="module-button module-button-primary" href="{{ $downloadUrl }}">Download</a>
      <button class="module-button" type="button" id="printBtn" style="display:none;">Print</button>
    </div>
  </header>

  <div class="pv-shell">
  <div class="pv-body">
    {{-- Viewer --}}
    <div class="pv-view">
      <div class="viewer-card">
        <div class="viewer-toolbar">
          <div class="left">
            <span class="pill" title="{{ $name }}">{{ $name }}</span>
            <span class="pill">{{ strtoupper($ext ?: 'FILE') }}</span>
          </div>
          <div class="right">
            <span class="pill">{{ number_format($mb, 2) }} MB</span>
          </div>
        </div>

        <div class="viewer-area" id="viewerArea">
          <div class="pv-loading" id="pvLoading">
            <div class="box"><span class="dot"></span> Working…</div>
          </div>

          @if($isPdf)
            <iframe id="pdfFrame" class="pdf-frame" src="{{ $streamUrl }}#toolbar=1&navpanes=0&scrollbar=1"></iframe>

          @elseif($isImage)
            <div class="img-wrap"><img src="{{ $streamUrl }}" alt="{{ $name }}"></div>

          @elseif($isAudio)
            <div class="media-preview media-preview-audio">
              <div class="media-preview-icon">♪</div>
              <strong>{{ $name }}</strong>
              <span>Audio backup</span>
              <audio controls preload="metadata" src="{{ $streamUrl }}">Your browser cannot play this audio file.</audio>
            </div>

          @elseif($isVideo)
            <div class="media-preview media-preview-video">
              <video controls preload="metadata" src="{{ $streamUrl }}">Your browser cannot play this video file.</video>
            </div>

          @elseif($isExcel)
            <div class="xl-wrap">
              <div class="xl-topbar">
                <div class="xl-tabs" id="xlTabs"></div>
                <div class="pill" style="max-width:none;">{{ $mode === 'edit' ? 'Editable sheet' : 'Read-only' }}</div>
              </div>
              <div id="hotHost"></div>
            </div>

          @elseif($isText)
            <div class="text-wrap" id="textHost"></div>

          @elseif($isWord)
            <div class="pv-msg" id="docxMsg">
              DOCX can be previewed, but editing requires OnlyOffice/Collabora integration.
            </div>
            <div id="docxHost" style="padding:14px;"></div>

          @else
            <div class="pv-msg">
              Preview not available for <strong>{{ strtoupper($ext ?: 'this file') }}</strong>.
              <div style="margin-top:10px;">
                <a class="btn btn-soft btn-sm" href="{{ $downloadUrl }}">Download instead</a>
              </div>
            </div>
          @endif
        </div>
      </div>
    </div>

    {{-- Info Pane --}}
    <aside class="pv-info">
      <div class="info-card">
        <div class="info-head">File info</div>
        <div class="info-body">
          <div class="kv"><div class="k">Name</div><div class="v">{{ $name }}</div></div>
          <div class="kv"><div class="k">Folder</div><div class="v">{{ $folder }}</div></div>
          <div class="kv"><div class="k">Type</div><div class="v">{{ strtoupper($ext ?: 'file') }}</div></div>
          <div class="kv"><div class="k">MIME</div><div class="v">{{ $mime ?: '—' }}</div></div>
          <div class="kv"><div class="k">Size</div><div class="v">{{ number_format($mb, 2) }} MB</div></div>
          <div class="kv"><div class="k">Uploaded</div><div class="v">{{ \App\Support\LocalTime::fromUtc($file->created_at)?->format('Y-m-d H:i') ?? '—' }} PHT</div></div>
          <div class="kv"><div class="k">Uploader</div><div class="v">{{ optional($file->uploader)->name ?? '—' }}</div></div>
          <div class="kv"><div class="k">Municipality</div><div class="v">{{ optional($file->municipality)->name ?? 'Provincial / unassigned' }}</div></div>
          <div class="kv"><div class="k">SHA-256</div><div class="v mono" title="{{ $file->sha256 }}">{{ $file->sha256 ? substr($file->sha256, 0, 20).'…' : 'Unavailable' }}</div></div>
          <div class="kv"><div class="k">Notes</div><div class="v">{{ $file->notes ?: 'No notes' }}</div></div>
          <div class="kv"><div class="k">Path</div><div class="v mono" id="pathVal">{{ $file->path ?: '—' }}</div></div>

          <div class="info-actions">
            <button class="copy-btn" type="button" id="copyPathBtn" @disabled(empty($file->path))>Copy Path</button>
          </div>

          <div class="help">
            Edit supported: TXT/LOG/SQL/CSV/JSON/XML/MD and XLSX.
          </div>
        </div>
      </div>
    </aside>
  </div>
</div>
</div>
@endsection

@push('styles')
<style>
  .backup-preview-page .module-header h1{max-width:760px;overflow:hidden;font-size:25px;text-overflow:ellipsis;white-space:nowrap}
  .backup-preview-page .pv-shell{border-radius:11px;box-shadow:0 2px 8px rgba(20,40,27,.03)}
  .backup-preview-page .viewer-card,.backup-preview-page .info-card{border-radius:10px;box-shadow:0 2px 8px rgba(20,40,27,.04)}
  .backup-preview-page .viewer-toolbar,.backup-preview-page .info-head{background:#f8faf8}
  .backup-preview-page .media-preview{width:100%;height:100%;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:9px;padding:24px;background:linear-gradient(145deg,#f8faf8,#eef6f0)}.backup-preview-page .media-preview-icon{width:58px;height:58px;display:grid;place-items:center;border-radius:16px;color:#fff;background:var(--module-green);font-size:25px;font-weight:800}.backup-preview-page .media-preview strong{max-width:80%;overflow:hidden;color:var(--module-ink);font-size:15px;text-overflow:ellipsis;white-space:nowrap}.backup-preview-page .media-preview span{color:var(--module-muted);font-size:10px}.backup-preview-page .media-preview audio{width:min(560px,90%);margin-top:8px}.backup-preview-page .media-preview-video{background:#101613}.backup-preview-page .media-preview video{max-width:100%;max-height:100%}
  @media(max-width:620px){.backup-preview-page .module-header h1{max-width:100%;white-space:normal;overflow-wrap:anywhere}.backup-preview-page .pv-view,.backup-preview-page .pv-info{padding:10px}}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/handsontable@14.3.0/dist/handsontable.full.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/sql/sql.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/markdown/markdown.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/docx-preview@0.1.15/dist/docx-preview.min.js"></script>

<script>
(function(){
  const streamUrl = @json($streamUrl);
  const saveUrl   = @json($saveUrl);
  const recordVersion = @json($recordVersion);
  const mode      = @json($mode);
  const ext       = @json($ext);
  const mime      = @json($mime);

  const loading = document.getElementById('pvLoading');
  const saveBtn = document.getElementById('saveBtn');
  const printBtn = document.getElementById('printBtn');

  const isPdf = (ext === 'pdf') || (mime && mime.includes('pdf'));
  const canEditText  = ['txt','log','sql','csv','json','xml','md'].includes(ext);
  const canEditExcel = ['xlsx'].includes(ext);
  const isText = canEditText;
  const isExcel = canEditExcel;
  const isWord = ['docx'].includes(ext);

  function setLoading(on){
    if(!loading) return;
    loading.style.display = on ? 'flex' : 'none';
  }

  async function copyText(text){
    if(!text || text === '—') return;
    try{ await navigator.clipboard.writeText(text); }
    catch(e){
      const ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
    }
  }
  const copyPathBtn = document.getElementById('copyPathBtn');
  const pathVal = document.getElementById('pathVal');
  if(copyPathBtn) copyPathBtn.addEventListener('click', () => copyText(pathVal?.textContent || ''));

  // Print (PDF)
  if(isPdf && printBtn){
    printBtn.style.display = '';
    printBtn.addEventListener('click', function(){
      const f = document.getElementById('pdfFrame');
      if(!f) return;
      try { f.contentWindow.focus(); f.contentWindow.print(); }
      catch(e){ window.open(streamUrl, '_blank', 'noopener'); }
    });
  }

  // ---------- TEXT EDITOR ----------
  let cm = null;

  function guessMode(ext){
    if(ext === 'sql') return 'text/x-sql';
    if(ext === 'json') return {name:'javascript', json:true};
    if(ext === 'xml') return 'application/xml';
    if(ext === 'md') return 'text/x-markdown';
    return null;
  }

  async function initText(){
    const host = document.getElementById('textHost');
    if(!host) return;

    setLoading(true);
    try{
      const res = await fetch(streamUrl, { credentials: 'same-origin' });
      if(!res.ok) throw new Error('HTTP ' + res.status);
      const txt = await res.text();

      cm = CodeMirror(host, {
        value: txt || '',
        lineNumbers: true,
        theme: 'eclipse',
        mode: guessMode(ext),
        readOnly: (mode !== 'edit'),
      });
    }catch(e){
      host.innerHTML = '<div class="pv-msg">Failed to load text.</div>';
    }finally{
      setLoading(false);
    }
  }

  async function saveText(){
    if(!cm) return;
    setLoading(true);
    try{
      const fd = new FormData();
      fd.append('kind', 'text');
      fd.append('content', cm.getValue());
      fd.append('_record_version', recordVersion);

      const res = await fetch(saveUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: fd
      });

      if(!res.ok){
        const error = await res.json().catch(() => ({}));
        throw new Error(error?.errors?._record_version?.[0] || error?.message || ('HTTP ' + res.status));
      }
      location.href = location.pathname + '?mode=view';
    }catch(e){
      alert('Save failed: ' + (e.message || e));
    }finally{
      setLoading(false);
    }
  }

  // ---------- EXCEL EDITOR ----------
  let wb = null;
  let hot = null;
  let activeSheet = null;

  function renderTabs(){
    const tabs = document.getElementById('xlTabs');
    if(!tabs || !wb) return;
    tabs.innerHTML = '';
    wb.SheetNames.forEach(name => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'xl-tab' + (name === activeSheet ? ' is-on' : '');
      b.textContent = name;
      b.addEventListener('click', () => {
        activeSheet = name;
        renderTabs();
        loadSheetToGrid();
      });
      tabs.appendChild(b);
    });
  }

  function sheetTo2D(ws){
    const data = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '' });
    if(!data.length) return [[]];
    // normalize to rectangle
    const maxCols = Math.max(...data.map(r => r.length));
    return data.map(r => {
      const rr = r.slice(0);
      while(rr.length < maxCols) rr.push('');
      return rr;
    });
  }

  function gridToSheet(data){
    return XLSX.utils.aoa_to_sheet(data);
  }

  function loadSheetToGrid(){
    const host = document.getElementById('hotHost');
    if(!host || !wb || !activeSheet) return;

    const ws = wb.Sheets[activeSheet];
    const data = sheetTo2D(ws);

    if(hot){
      hot.loadData(data);
      hot.render();
      return;
    }

    hot = new Handsontable(host, {
      data,
      rowHeaders: true,
      colHeaders: true,
      contextMenu: true,
      dropdownMenu: true,
      filters: true,
      licenseKey: 'non-commercial-and-evaluation',
      readOnly: (mode !== 'edit'),
      manualColumnResize: true,
      manualRowResize: true,
    });
  }

  async function initExcel(){
    setLoading(true);
    try{
      const res = await fetch(streamUrl, { credentials: 'same-origin' });
      if(!res.ok) throw new Error('HTTP ' + res.status);
      const buf = await res.arrayBuffer();
      wb = XLSX.read(buf, { type:'array' });
      activeSheet = wb.SheetNames[0];
      renderTabs();
      loadSheetToGrid();
    }catch(e){
      const host = document.getElementById('hotHost');
      if(host) host.innerHTML = '<div class="pv-msg">Failed to load Excel.</div>';
    }finally{
      setLoading(false);
    }
  }

  async function saveExcel(){
    if(!wb || !hot) return;
    setLoading(true);
    try{
      // write current grid back into workbook active sheet
      const data = hot.getData();
      wb.Sheets[activeSheet] = gridToSheet(data);

      const out = XLSX.write(wb, { bookType:'xlsx', type:'array' });
      const blob = new Blob([out], { type:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });

      const fd = new FormData();
      fd.append('kind', 'xlsx');
      fd.append('file', blob, 'edited.xlsx');
      fd.append('_record_version', recordVersion);

      const res = await fetch(saveUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: fd
      });

      if(!res.ok){
        const error = await res.json().catch(() => ({}));
        throw new Error(error?.errors?._record_version?.[0] || error?.message || ('HTTP ' + res.status));
      }
      location.href = location.pathname + '?mode=view';
    }catch(e){
      alert('Save failed: ' + (e.message || e));
    }finally{
      setLoading(false);
    }
  }

  // ---------- DOCX PREVIEW (read-only) ----------
  async function initDocx(){
    const host = document.getElementById('docxHost');
    if(!host || typeof window.docx === 'undefined') return;
    try{
      const res = await fetch(streamUrl, { credentials: 'same-origin' });
      if(!res.ok) return;
      const buf = await res.arrayBuffer();
      await window.docx.renderAsync(buf, host);
    }catch(e){}
  }

  // Hook Save button
  if(saveBtn){
    saveBtn.addEventListener('click', function(){
      if(isText) return saveText();
      if(isExcel) return saveExcel();
      alert('Editing not supported for this file type.');
    });
  }

  // Boot
  if(isText) initText();
  if(isExcel) initExcel();
  if(isWord) initDocx();
})();
</script>
@endpush
