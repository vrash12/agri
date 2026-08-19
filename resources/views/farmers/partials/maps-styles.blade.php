@push('styles')
<style>
  .farmers-map-wrap{
    border: 1px solid var(--border);
    border-radius: 18px;
    overflow: hidden;
    background: #fff;
    margin-bottom: 14px;
    box-shadow: 0 10px 32px rgba(2,6,23,.05);
  }

  .farmers-table-toolbar{
  display:flex;
  justify-content:flex-end;
  align-items:center;
  gap:10px;
  margin: 0 0 12px;
  flex-wrap:wrap;
}

.farmers-search-wrap{
  display:flex;
  gap:10px;
  align-items:center;
  flex-wrap:wrap;
}

.farmers-search-input{
  min-width:320px;
}

@media (max-width: 700px){
  .farmers-table-toolbar{
    justify-content:stretch;
  }

  .farmers-search-wrap{
    width:100%;
  }

  .farmers-search-input{
    min-width:0;
    width:100%;
  }
}

  .farmers-map-head{
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap: 14px;
    flex-wrap: wrap;
    background:
      radial-gradient(circle at top left, rgba(59,130,246,.08), transparent 36%),
      linear-gradient(180deg, #fbfdff 0%, #f8fafc 100%);
  }

  .farmers-map-head-left{ min-width: 280px; flex: 1 1 auto; }
  .farmers-map-head-right{ flex: 0 0 auto; }
  .map-title-row{ display:flex; align-items:center; gap: 10px; flex-wrap: wrap; }

  .map-badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .2px;
    border: 1px solid var(--border);
    background: rgba(2,6,23,.03);
    color: #0b1220;
    user-select: none;
  }
  .map-badge-solid{ background:#0b1220; border-color:#0b1220; color:#fff; }
  .map-badge-blue{ background: rgba(59,130,246,.10); border-color: rgba(59,130,246,.30); color:#1d4ed8; }

  .map-subtitle{ color: var(--muted); }

  .map-status-row{
    margin-top: 10px;
    display:flex;
    align-items:center;
    gap: 10px;
    flex-wrap: wrap;
  }

  .map-status-small{ font-size: 12px; color: var(--muted); font-weight: 800; }

  .map-progress{
    width: 180px;
    height: 10px;
    border-radius: 999px;
    background: rgba(2,6,23,.06);
    overflow: hidden;
    border: 1px solid rgba(2,6,23,.08);
  }

  .map-progress-bar{
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, rgba(34,197,94,.85), rgba(59,130,246,.85));
    width: 0%;
    transition: width .2s ease;
  }

  .map-controls{
    display:flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items:flex-start;
    justify-content:flex-end;
  }

  .map-control-group{
    display:flex;
    align-items:center;
    gap: 8px;
    padding: 10px;
    border-radius: 14px;
    border: 1px solid var(--border);
    background: rgba(255,255,255,.86);
    backdrop-filter: blur(6px);
    box-shadow: 0 6px 20px rgba(2,6,23,.04);
  }

  .map-control-label{
    font-size: 12px;
    font-weight: 900;
    color: #0b1220;
    margin-right: 6px;
    white-space: nowrap;
  }

  .map-toggle{
    display:inline-flex;
    align-items:center;
    gap: 8px;
    padding: 6px 10px;
    border-radius: 999px;
    border: 1px solid var(--border);
    background: rgba(2,6,23,.02);
    font-size: 12px;
    font-weight: 900;
    color: #0b1220;
    user-select: none;
    cursor: pointer;
  }

  .map-toggle input{ accent-color: var(--green); }

  .farmers-map-main{
    display:grid;
    grid-template-columns: 1.8fr 0.95fr;
    gap: 0;
    min-height: 460px;
  }

  .farmers-map-stage{
    position: relative;
    border-right: 1px solid var(--border);
    background: #eef4fb;
  }

  .farmers-map{
    width: 100%;
    height: 460px;
    background: rgba(2,6,23,.03);
    position: relative;
  }

  .farmers-map gmp-map-3d,
  .farmers-map .gmp-map-3d{
    width:100%;
    height:100%;
    display:block;
  }

  .map-panel{
    background: #fff;
    padding: 12px;
    min-height: 460px;
  }

  .map-panel-card{
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    background: #fff;
    height: 100%;
    box-shadow: 0 8px 24px rgba(2,6,23,.04);
  }

  .map-panel-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap: 10px;
    padding: 12px;
    background: #f8fafc;
    border-bottom: 1px solid var(--border);
  }

  .map-panel-title{ font-size: 13px; font-weight: 900; color: #0b1220; }
  .map-panel-body{ padding: 12px; }

  .map-kv{
    display:grid;
    grid-template-columns: 90px 1fr;
    gap: 10px;
    padding: 6px 0;
    border-bottom: 1px dashed rgba(2,6,23,.08);
  }

  .map-k{ font-size: 12px; font-weight: 900; color: var(--muted); }
  .map-v{ font-size: 12px; font-weight: 900; color: #0b1220; word-break: break-word; }

  .map-metrics{
    display:grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-top: 12px;
  }

  .map-metric{
    border: 1px solid var(--border);
    border-radius: 14px;
    background: linear-gradient(180deg, rgba(2,6,23,.02), rgba(2,6,23,.04));
    padding: 10px;
  }

  .map-metric-label{ font-size: 11px; font-weight: 900; color: var(--muted); margin-bottom: 4px; }
  .map-metric-value{ font-size: 12px; font-weight: 900; color: #0b1220; }

  .map-panel-actions{ display:flex; gap: 10px; margin-top: 12px; flex-wrap: wrap; }
  .map-divider{ height: 1px; background: rgba(2,6,23,.08); margin: 12px 0; }

  .map-plot-meta{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 10px;
  }

  .map-plot-draft{ font-size: 12px; font-weight: 800; color: #0b1220; line-height: 1.45; }

  .map-input-row{ margin-bottom: 10px; }
  .map-input-label{ display:block; font-size: 12px; font-weight: 900; color: var(--muted); margin-bottom: 6px; }

  .map-input{
    width: 100%;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 10px 12px;
    font-weight: 800;
    outline: none;
    transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
  }

  .map-input:focus{
    border-color: rgba(59,130,246,.45);
    box-shadow: 0 0 0 3px rgba(59,130,246,.10);
  }

  .map-color-row{ display:flex; gap: 10px; align-items:center; }

  .map-color{
    width: 46px;
    height: 40px;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 4px;
    background:#fff;
  }

  .map-color-hex{
    flex: 1;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 10px 12px;
    font-weight: 900;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
  }

  .map-color-presets{
    display:flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 10px;
  }

  .map-color-chip{
    width: 26px;
    height: 26px;
    border-radius: 999px;
    border: 2px solid rgba(255,255,255,.85);
    background: var(--chip);
    box-shadow: 0 0 0 1px rgba(2,6,23,.08), 0 6px 12px rgba(2,6,23,.08);
    cursor: pointer;
    transition: transform .14s ease, box-shadow .14s ease;
  }

  .map-color-chip:hover{ transform: translateY(-1px); }
  .map-color-chip.is-active{ box-shadow: 0 0 0 2px rgba(2,6,23,.85), 0 8px 14px rgba(2,6,23,.08); }

  .map-plot-list{ display:flex; flex-direction:column; gap: 8px; }

  .map-plot-item{
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 10px;
    background: linear-gradient(180deg, #fff, #fbfdff);
    display:flex;
    justify-content:space-between;
    gap: 10px;
    align-items:flex-start;
    transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
  }

  .map-plot-item:hover{
    border-color: rgba(59,130,246,.22);
    box-shadow: 0 10px 18px rgba(2,6,23,.05);
    transform: translateY(-1px);
  }

  .plot-swatch{
    width: 12px;
    height: 12px;
    border-radius: 999px;
    border: 1px solid rgba(2,6,23,.15);
    display:inline-block;
    margin-right: 8px;
    vertical-align: middle;
  }

  .map-plot-name{ font-size: 12px; font-weight: 900; color: #0b1220; }
  .map-plot-sub{ font-size: 11px; font-weight: 800; color: var(--muted); margin-top: 4px; }
  .map-plot-actions{ display:flex; gap: 8px; align-items:center; flex-wrap: wrap; }

  .map-empty{
    padding: 12px;
    border: 1px dashed rgba(2,6,23,.15);
    border-radius: 14px;
    background: rgba(2,6,23,.02);
    color: var(--muted);
    font-weight: 800;
    font-size: 12px;
  }

  .map-help{
    margin-top: 10px;
    border: 1px solid rgba(2,6,23,.10);
    border-radius: 14px;
    padding: 10px 12px;
    background: rgba(2,6,23,.02);
  }

  .map-help summary{ cursor: pointer; font-weight: 900; font-size: 12px; color: #0b1220; }
  .map-help-body{ margin-top: 8px; }
  .map-help-list{ margin: 0; padding-left: 18px; color: var(--muted); font-weight: 800; font-size: 12px; }

  .farmers-map-foot{
    padding: 10px 14px;
    border-top: 1px solid var(--border);
    display:flex;
    gap: 10px;
    align-items:center;
    color: var(--muted);
    background: #fff;
  }

  .map-hint{
    position:absolute;
    left: 14px;
    bottom: 14px;
    max-width: 230px;
    background: rgba(255,255,255,.92);
    border: 1px solid rgba(2,6,23,.10);
    border-radius: 16px;
    padding: 12px 4px;
    box-shadow: 0 10px 24px rgba(2,6,23,.10);
    backdrop-filter: blur(6px);
    z-index: 30;
  }

  .map-hint-title{ font-weight: 900; color: #0b1220; font-size: 13px; margin-bottom: 4px; }
  .map-hint-text{ font-weight: 800; color: var(--muted); font-size: 12px; line-height: 1.35; }

  .map-selection-chip{
    position:absolute;
    left: 14px;
    top: 14px;
    display:inline-flex;
    align-items:center;
    gap: 8px;
    max-width: calc(100% - 180px);
    padding: 8px 12px;
    border-radius: 999px;
    border: 1px solid rgba(2,6,23,.10);
    background: rgba(255,255,255,.92);
    box-shadow: 0 10px 24px rgba(2,6,23,.08);
    backdrop-filter: blur(8px);
    z-index: 45;
    font-size: 12px;
    font-weight: 900;
    color: #0b1220;
  }

  .map-selection-dot{
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: #3b82f6;
    box-shadow: 0 0 0 4px rgba(59,130,246,.18);
    flex: 0 0 auto;
  }

  .map-workflow-card{
    position:absolute;
    right: 14px;
    top: 14px;
    width: min(330px, calc(100% - 28px));
    background: rgba(255,255,255,.94);
    border: 1px solid rgba(2,6,23,.10);
    border-radius: 18px;
    padding: 12px;
    box-shadow: 0 16px 34px rgba(2,6,23,.10);
    backdrop-filter: blur(10px);
    z-index: 50;
  }

  .map-workflow-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap: 10px;
  }

  .map-workflow-title{
    font-size: 13px;
    font-weight: 900;
    color: #0b1220;
  }

  .map-workflow-text{
    margin-top: 8px;
    font-size: 12px;
    line-height: 1.45;
    color: var(--muted);
    font-weight: 800;
  }

  .map-workflow-grid{
    display:grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-top: 10px;
  }

  .map-workflow-metric{
    border: 1px solid rgba(2,6,23,.08);
    border-radius: 12px;
    padding: 9px;
    background: rgba(248,250,252,.88);
    min-width: 0;
  }

  .map-workflow-label{
    font-size: 10px;
    font-weight: 900;
    color: var(--muted);
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: .04em;
  }

  .map-workflow-value{
    font-size: 12px;
    font-weight: 900;
    color: #0b1220;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .map-workflow-actions{
    margin-top: 10px;
    display:flex;
    gap: 8px;
    flex-wrap: wrap;
  }

  .map-legend-card{
    position:absolute;
    right: 14px;
    bottom: 14px;
    display:flex;
    flex-direction:column;
    gap: 6px;
    padding: 10px 12px;
    border-radius: 14px;
    border: 1px solid rgba(2,6,23,.10);
    background: rgba(255,255,255,.90);
    backdrop-filter: blur(8px);
    box-shadow: 0 10px 24px rgba(2,6,23,.08);
    z-index: 30;
    font-size: 12px;
    font-weight: 800;
    color: #0b1220;
  }

  .map-legend-row{ display:flex; align-items:center; gap: 8px; }
  .map-legend-swatch{
    width: 18px;
    height: 10px;
    border-radius: 999px;
    display:inline-block;
    flex: 0 0 auto;
  }
  .map-legend-swatch.is-draft{
    background: linear-gradient(90deg, rgba(59,130,246,.95), rgba(59,130,246,.35));
    border: 1px solid rgba(59,130,246,.55);
  }
  .map-legend-swatch.is-saved{
    background: linear-gradient(90deg, rgba(34,197,94,.95), rgba(34,197,94,.35));
    border: 1px solid rgba(34,197,94,.55);
  }

.map-hint{
  position:absolute;
  left:16px;
  top:16px;
  bottom:auto; /* VERY IMPORTANT: stop stretching downward */
  z-index:20;
  width:320px;
  max-width:320px;
  padding:14px 16px;
  border-radius:16px;
  background:rgba(15,23,42,.90);
  color:#fff;
  border:1px solid rgba(255,255,255,.12);
  box-shadow:0 14px 32px rgba(2,6,23,.28);
  backdrop-filter:blur(8px);
}
.map-hint-title{
  font-weight:900;
  font-size:14px;
  margin-bottom:4px;
  color:#ffffff;
}

.map-hint-text{
  font-size:12px;
  line-height:1.45;
  color:rgba(255,255,255,.88);
}

#mapStatus,
#mapGeocodedPill{
  background:rgba(15,23,42,.92) !important;
  color:#ffffff !important;
  border:1px solid rgba(255,255,255,.10) !important;
  box-shadow:0 8px 20px rgba(2,6,23,.18);
}

#mapStatusSmall{
  color:#e2e8f0 !important;
  font-weight:700;
  text-shadow:0 1px 2px rgba(0,0,0,.35);
}

.map-toast{
  position:absolute;
  left:16px;
  bottom:16px;
  z-index:30;
  max-width:360px;
  padding:12px 14px;
  border-radius:14px;
  font-size:13px;
  font-weight:800;
  line-height:1.4;
  color:#fff;
  background:rgba(15,23,42,.94);
  border:1px solid rgba(255,255,255,.10);
  box-shadow:0 12px 30px rgba(2,6,23,.28);
  backdrop-filter:blur(8px);
  opacity:0;
  transform:translateY(8px);
  pointer-events:none;
  transition:opacity .18s ease, transform .18s ease;
}

.map-toast.is-show{
  opacity:1;
  transform:translateY(0);
}

.map-toast.is-warn{
  background:rgba(15,23,42,.96);
  color:#ffffff;
  border-color:rgba(96,165,250,.45);
}

.map-toast.is-ok{
  background:rgba(3,105,161,.95);
  color:#ffffff;
  border-color:rgba(125,211,252,.38);
}

.map-toast.is-bad{
  background:rgba(127,29,29,.95);
  color:#ffffff;
  border-color:rgba(248,113,113,.40);
}
  @media (max-width: 1080px){
    .farmers-map-main{ grid-template-columns: 1fr; }
    .farmers-map-stage{ border-right: 0; border-bottom: 1px solid var(--border); }
    .map-panel{ min-height: auto; }
    .farmers-map{ height: 400px; }
  }

  @media (max-width: 700px){
    .farmers-map{ height: 340px; }
    .map-progress{ width: 140px; }
    .map-workflow-card{
      left: 14px;
      right: 14px;
      width: auto;
      top: auto;
      bottom: 14px;
    }
    .map-hint{ max-width: calc(100% - 28px); }
    .map-legend-card{ display:none; }
    .map-selection-chip{ max-width: calc(100% - 28px); }
  }

  #farmersMapModule #farmersMap,
  #farmersMapModule .farmers-map-stage{ cursor: grab; }

  #farmersMapModule #farmersMap:active,
  #farmersMapModule .farmers-map-stage:active{ cursor: grabbing; }

  #farmersMapModule.is-plot-mode #farmersMap,
  #farmersMapModule.is-plot-mode #farmersMap *,
  #farmersMapModule.is-plot-mode .farmers-map-stage,
  #farmersMapModule.is-plot-mode .farmers-map-stage *{
    cursor: none !important;
  }

  #farmersMapModule .plot-cursor{
    position:absolute;
    left:-9999px;
    top:-9999px;
    width: 14px;
    height: 14px;
    transform: translate(-50%, -50%);
    pointer-events:none;
    z-index: 70;
    display:none;
    background: rgba(59,130,246,0.95);
    border-radius: 50%;
    box-shadow: 0 0 0 4px rgba(59,130,246,0.25);
  }

  #farmersMapModule.is-plot-mode .plot-cursor{ display:block; }

  #farmersMapModule .map-crosshair{
    position:absolute;
    inset:0;
    pointer-events:none;
    opacity:0;
    transition: opacity .15s ease;
    z-index: 40;
  }

  #farmersMapModule.is-plot-mode .map-crosshair{ opacity:1; }

  #farmersMapModule .map-crosshair-dot{
    position:absolute;
    left:50%;
    top:50%;
    transform: translate(-50%, -50%);
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid rgba(59,130,246,.85);
    background: rgba(255,255,255,.95);
    box-shadow: 0 6px 14px rgba(2,6,23,.15);
  }
</style>
@endpush