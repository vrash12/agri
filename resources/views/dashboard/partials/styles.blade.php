@include('partials.design-tokens')
<style>
  .ops-dashboard {
    --ops-ink: var(--ui-text);
    --ops-muted: var(--ui-text-muted);
    --ops-border: var(--ui-border);
    --ops-surface: var(--ui-surface);
    --ops-subtle: var(--ui-surface-subtle);
    --ops-green: var(--ui-primary);
    --ops-green-soft: var(--ui-primary-soft);
    --ops-blue: var(--ui-info);
    --ops-blue-soft: var(--ui-info-soft);
    --ops-amber: var(--ui-warning);
    --ops-amber-soft: var(--ui-warning-soft);
    --ops-red: var(--ui-danger);
    --ops-red-soft: var(--ui-danger-soft);
    display: flex;
    flex-direction: column;
    gap: 22px;
    color: var(--ops-ink);
    font-variant-numeric: tabular-nums;
  }
  body:has(.ops-dashboard) { background: var(--ui-page-background); }
  body:has(.ops-dashboard) .container { max-width: 1760px; padding: 24px 26px 40px; }
  .ops-dashboard .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
  .ops-dashboard :is(a, button, input, select, summary):focus-visible { outline: 3px solid var(--ui-focus); outline-offset: 4px; }
  .ops-header { display: flex; justify-content: space-between; align-items: center; gap: 24px; padding: 6px 0; }
  .ops-context { color: var(--ops-green); font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: none; }
  .ops-header h1 { margin: 9px 0 8px; font-size: clamp(26px, 2.7vw, 36px); letter-spacing: -.04em; line-height: 1.15; }
  .ops-header p { margin: 0; color: var(--ops-muted); font-size: 13px; line-height: 1.6; }
  .ops-date { flex-shrink: 0; text-align: right; }
  .ops-date strong, .ops-date span { display: block; font-size: 12px; }
  .ops-date span { margin-top: 6px; color: var(--ops-muted); font-size: 12px; }
  .ops-overview { display: grid; grid-template-columns: minmax(0, 1fr); gap: 28px; padding: 30px; border-radius: 12px; background: var(--ui-brand-dark); color: #fff; }
  .ops-overview-copy { min-width: 0; display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap; }
  .ops-scope { display: inline-flex; align-items: center; gap: 7px; color: #cee3d2; font-size: 12px; font-weight: 500; }
  .ops-scope svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 1.7; }
  .ops-overview h2 { margin: 13px 0 9px; font-size: clamp(22px, 2.1vw, 29px); font-weight: 500; letter-spacing: -.025em; line-height: 1.2; }
  .ops-overview p { max-width: 570px; margin: 0; color: #c6dacd; font-size: 13px; line-height: 1.65; }
  .ops-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 23px; }
  .ops-button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 44px; padding: 10px 13px; border: 1px solid var(--ops-border); border-radius: 8px; font-size: 12px; font-weight: 700; text-decoration: none; transition: background .15s, border-color .15s; }
  .ops-button svg, .ops-icon svg { width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
  .ops-button-secondary { background: #fff; color: var(--ops-green); }
  .ops-button-secondary:hover { background: #edf4ee; border-color: #94b49e; }
  .ops-button-primary { color: #fff; background: var(--ops-green); border-color: var(--ops-green); }
  .ops-button-primary:hover { background: #18492f; }
  .ops-overview .ops-button-secondary { color: #fff; background: transparent; border-color: #638270; }
  .ops-overview .ops-button-secondary:hover { background: #2b523e; border-color: #9db9a7; }
  .ops-overview .ops-button-primary { color: var(--ui-on-accent); background: var(--ui-accent); border-color: var(--ui-accent); }
  .ops-overview .ops-button-primary:hover { background: var(--ui-accent-hover); border-color: var(--ui-accent-hover); }
  .ops-overview :is(a, button):focus-visible { outline-color: var(--ui-focus-inverse); }
  .ops-overview .ops-actions { margin-top: 0; }
  .ops-field-summary { position: relative; display: flex; flex-direction: column; justify-content: flex-end; min-width: 0; min-height: 180px; padding: 24px; border: 1px solid #567462; border-radius: 12px; background: #254b36; color: #d8e6dc; text-decoration: none; }
  .ops-field-summary:hover { border-color: #c7d8b2; }
  .ops-field-art { position: absolute; right: 4px; top: 4px; width: 145px; height: 100px; stroke: #99b38b; stroke-width: 1.1; opacity: .65; }
  .ops-field-summary > span { position: relative; font-size: 12px; }
  .ops-field-summary strong { position: relative; margin: 8px 0 12px; font-size: 32px; font-weight: 500; letter-spacing: -.035em; overflow-wrap: anywhere; }
  .ops-field-summary small { font-size: 15px; color: #c4d8ba; }
  .ops-field-summary > span:last-child { display: flex; justify-content: space-between; }
  .ops-section-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-top: 3px; }
  .ops-section-heading h2 { margin: 4px 0 0; font-size: 18px; letter-spacing: -.02em; }
  .ops-section-heading > span { color: var(--ops-muted); font-size: 12px; }
  .ops-kpi-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-top: -7px; }
  .ops-kpi { display: flex; flex-direction: column; min-width: 0; padding: 21px; border: 1px solid var(--ops-border); border-radius: 12px; background: #fff; }
  .ops-kpi-top, .ops-kpi-value-row, .ops-kpi-foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
  .ops-kpi-label { font-size: 12px; font-weight: 500; color: var(--ops-muted); }
  .ops-icon { display: grid; place-items: center; flex: 0 0 auto; width: 35px; height: 35px; border-radius: 10px; }
  .ops-icon-green { color: var(--ops-green); background: var(--ops-green-soft); }
  .ops-icon-blue { color: var(--ops-blue); background: var(--ops-blue-soft); }
  .ops-icon-amber { color: var(--ops-amber); background: var(--ops-amber-soft); }
  .ops-icon-red { color: var(--ops-red); background: var(--ops-red-soft); }
  .ops-kpi-value { display: block; margin: 14px 0 19px; font-size: clamp(27px, 2.5vw, 35px); font-weight: 500; letter-spacing: -.045em; line-height: 1.12; overflow-wrap: anywhere; }
  .ops-kpi-value small { color: var(--ops-muted); font-size: 14px; letter-spacing: 0; }
  .ops-kpi-value-row { flex-wrap: wrap; }
  .ops-kpi-value-row .ops-kpi-value { margin-bottom: 12px; }
  .ops-kpi-value-row > span { color: var(--ops-muted); font-size: 12px; }
  .ops-kpi-foot { margin-top: auto; padding-top: 14px; border-top: 1px solid #edf1ee; font-size: 12px; color: var(--ops-muted); line-height: 1.5; flex-wrap: wrap; }
  .ops-kpi-foot a, .ops-text-link, .ops-row-link, .ops-cell-link { color: var(--ops-green); font-weight: 700; text-decoration: none; }
  .ops-kpi-foot a:hover, .ops-text-link:hover, .ops-row-link:hover, .ops-cell-link:hover { text-decoration: underline; }
  .ops-progress, .ops-mini-progress, .ops-rank-bar { height: 6px; margin: 0 0 14px; overflow: hidden; border-radius: 99px; background: #eaf0eb; }
  .ops-progress span, .ops-mini-progress span, .ops-rank-bar span { display: block; height: 100%; border-radius: inherit; background: #639579; }
  .ops-month-strip { display: grid; grid-template-columns: 1.15fr repeat(4, 1fr); padding: 6px; border: 1px solid var(--ops-border); border-radius: 12px; background: #edf2eb; }
  .ops-month-label, .ops-month-stat { min-width: 0; padding: 12px 16px; }
  .ops-month-stat { border-left: 1px solid #d6e1d6; }
  .ops-month-label span, .ops-month-stat span { display: block; margin-bottom: 7px; font-size: 12px; color: var(--ops-muted); }
  .ops-month-label strong, .ops-month-stat strong { font-size: 15px; font-weight: 700; overflow-wrap: anywhere; }
  .ops-layout { display: grid; grid-template-columns: minmax(0, 1.65fr) minmax(310px, 1fr); align-items: start; gap: 20px; }
  .ops-main-column, .ops-side-column { display: flex; flex-direction: column; gap: 20px; min-width: 0; }
  .ops-panel { min-width: 0; overflow: hidden; border: 1px solid var(--ops-border); border-radius: 12px; background: #fff; }
  .ops-panel-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; padding: 21px; border-bottom: 1px solid #edf1ee; }
  .ops-panel-kicker { display: block; margin-bottom: 5px; color: var(--ops-green); font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: none; }
  .ops-panel-header h2 { margin: 0; font-size: 17px; line-height: 1.3; letter-spacing: -.02em; }
  .ops-panel-header p { margin: 7px 0 0; color: var(--ops-muted); font-size: 12px; line-height: 1.5; }
  .ops-period, .ops-attention-badge { flex-shrink: 0; padding: 6px 9px; border: 1px solid var(--ops-border); border-radius: 6px; background: var(--ops-subtle); color: var(--ops-muted); font-size: 12px; white-space: nowrap; }
  .ops-text-link { align-self: center; font-size: 12px; white-space: nowrap; }
  .ops-chart-wrap { position: relative; min-width: 0; height: 290px; padding: 20px; overflow: hidden; }
  .ops-chart-wrap canvas { max-width: 100%; max-height: 100%; }
  .ops-chart-empty { position: absolute; inset: 20px; display: grid; place-items: center; padding: 24px; border: 1px dashed #cbd8ce; border-radius: 8px; background: #fbfdfbf2; color: var(--ops-muted); font-size: 12px; text-align: center; line-height: 1.5; }
  .ops-chart-data { padding: 0 21px 16px; font-size: 12px; color: var(--ops-muted); }
  .ops-chart-data summary { padding: 6px 0; cursor: pointer; color: var(--ops-green); }
  .ops-chart-data dl { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
  .ops-chart-data dl div { display: flex; justify-content: space-between; gap: 5px; }
  .ops-chart-data dd { margin: 0; font-weight: 700; }
  .ops-table-wrap { position: relative; overflow-x: auto; }
  .ops-table { width: 100%; border-collapse: collapse; }
  .ops-table th { padding: 12px 17px; border-bottom: 1px solid var(--ops-border); background: #f7f9f6; color: var(--ops-muted); font-size: 12px; font-weight: 500; text-align: left; white-space: nowrap; }
  .ops-table td { padding: 16px 17px; border-bottom: 1px solid #edf1ee; color: var(--ops-muted); font-size: 12px; white-space: nowrap; }
  .ops-table td strong { color: var(--ops-ink); font-weight: 500; }
  .ops-table td > small { display: block; margin-top: 5px; font-size: 12px; }
  .ops-table tbody tr:last-child td { border-bottom: 0; }
  .ops-table tbody tr:hover td { background: #f8fbf7; }
  .ops-numeric { text-align: right !important; }
  .ops-mono { font-family: ui-monospace, monospace; font-size: 12px !important; }
  .ops-record-row { display: grid; grid-template-columns: 36px minmax(0, 1fr) auto; align-items: center; gap: 12px; padding: 16px 20px; border-bottom: 1px solid #edf1ee; color: inherit; text-decoration: none; }
  .ops-record-row:last-child { border-bottom: 0; }
  a.ops-record-row:hover { background: #f8fbf7; }
  .ops-record-mark { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 10px; background: var(--ops-green-soft); color: var(--ops-green); font-size: 12px; font-weight: 700; }
  .ops-record-mark-red { background: var(--ops-red-soft); color: var(--ops-red); }
  .ops-record-body { min-width: 0; }
  .ops-record-body strong { display: block; font-size: 12px; font-weight: 500; overflow-wrap: anywhere; }
  .ops-record-body small { display: block; margin-top: 5px; color: var(--ops-muted); font-size: 12px; line-height: 1.5; overflow-wrap: anywhere; }
  .ops-record-row time { color: var(--ops-muted); font-size: 12px; }
  .ops-attention-panel { border-color: #e4dac3; }
  .ops-attention-panel .ops-panel-header { background: #fcf9f1; }
  .ops-attention-panel .ops-panel-kicker { color: var(--ops-amber); }
  .ops-attention-badge { color: var(--ops-amber); background: #f7ecd1; border-color: #e9d9b7; }
  .ops-attention-row { display: grid; grid-template-columns: minmax(40px, auto) minmax(0, 1fr) 12px; gap: 12px; align-items: center; padding: 18px 20px; border-bottom: 1px solid #f0eee7; color: inherit; text-decoration: none; }
  .ops-attention-row:last-child { border-bottom: 0; }
  .ops-attention-row:hover { background: #fcfaf4; }
  .ops-attention-count { min-width: 40px; padding: 9px 6px; border-radius: 8px; background: #faf4e7; color: var(--ops-amber); font-size: 17px; font-weight: 500; text-align: center; }
  .ops-attention-row strong, .ops-attention-row small { display: block; }
  .ops-attention-row strong { font-size: 12px; font-weight: 500; line-height: 1.4; }
  .ops-attention-row small { margin-top: 4px; color: var(--ops-muted); font-size: 12px; line-height: 1.5; }
  .ops-chevron { color: #8c9c90; font-size: 21px; }
  .ops-all-clear { padding: 0 20px; color: var(--ops-green); font-size: 12px; line-height: 1.6; }
  .ops-ranking { padding: 22px; }
  .ops-rank-row + .ops-rank-row { margin-top: 21px; }
  .ops-rank-meta { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 9px; font-size: 12px; }
  .ops-rank-meta span { overflow-wrap: anywhere; min-width: 0; }
  .ops-rank-meta strong { color: var(--ops-muted); font-size: 12px; font-weight: 500; white-space: nowrap; }
  .ops-rank-bar { margin-bottom: 0; }
  .ops-status-list { margin: 0; padding: 4px 20px; }
  .ops-status-list > div { display: flex; justify-content: space-between; gap: 16px; padding: 12px 0; border-bottom: 1px solid #edf1ee; font-size: 12px; line-height: 1.5; }
  .ops-status-list > div:last-child { border-bottom: 0; }
  .ops-status-list dt { color: var(--ops-muted); }
  .ops-status-list dd { margin: 0; text-align: right; font-weight: 500; }
  .ops-panel-actions { display: flex; flex-wrap: wrap; gap: 8px; padding: 14px 20px 20px; }
  .ops-panel-actions .ops-button { flex: 1; }
  .ops-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 155px; padding: 28px; color: var(--ops-muted); text-align: center; font-size: 12px; line-height: 1.6; }
  .ops-empty strong { color: var(--ops-ink); margin-bottom: 5px; font-weight: 500; font-size: 14px; }
  .ops-empty-small { min-height: 100px; }
  .ops-province-summary { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); border-bottom: 1px solid var(--ops-border); background: #f8faf7; }
  .ops-province-summary article { min-width: 0; padding: 18px; border-right: 1px solid var(--ops-border); }
  .ops-province-summary article:last-child { border-right: 0; }
  .ops-province-summary span, .ops-province-summary small { display: block; color: var(--ops-muted); font-size: 12px; line-height: 1.5; }
  .ops-province-summary strong { display: block; margin: 8px 0; font-size: 27px; font-weight: 500; }
  .ops-province-summary strong small { display: inline; margin-left: 3px; }
  .ops-province-summary .ops-summary-alert strong, .ops-text-warning { color: var(--ops-amber) !important; }
  .ops-municipality-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 18px 20px; }
  .ops-municipality-search { position: relative; display: flex; align-items: center; min-width: 180px; flex: 1; }
  .ops-municipality-search svg { position: absolute; left: 12px; width: 16px; height: 16px; fill: none; stroke: var(--ops-muted); stroke-width: 1.8; }
  .ops-municipality-search input, .ops-toolbar-field select { height: 44px; width: 100%; border: 1px solid var(--ops-border); border-radius: 8px; background: #fff; color: var(--ops-ink); font-size: 12px; }
  .ops-municipality-search input { padding: 0 12px 0 37px; }
  .ops-toolbar-field { display: flex; align-items: center; gap: 8px; color: var(--ops-muted); font-size: 12px; }
  .ops-toolbar-field select { width: auto; padding: 0 8px; }
  .ops-visible-count { color: var(--ops-muted); font-size: 12px; }
  .ops-municipality-table-wrap { max-height: 560px; overflow: auto; scrollbar-width: thin; }
  .ops-municipality-table { min-width: 1240px; }
  .ops-municipality-table th { position: sticky; top: 0; z-index: 1; }
  .ops-municipality-identity { display: flex; align-items: center; gap: 10px; }
  .ops-municipality-identity > span { display: grid; place-items: center; width: 34px; height: 34px; border-radius: 9px; background: var(--ops-green-soft); color: var(--ops-green); font-weight: 700; }
  .ops-municipality-identity strong, .ops-municipality-identity small, .ops-cell-value, .ops-cell-note, .ops-cell-link, .ops-directory-link { display: block; }
  .ops-municipality-identity small, .ops-cell-note { margin-top: 5px; color: var(--ops-muted); font-size: 12px; }
  .ops-cell-link, .ops-directory-link { margin-top: 8px; font-size: 12px; }
  .ops-cell-value-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
  .ops-cell-value-row span { color: var(--ops-muted); font-size: 12px; }
  .ops-mapping-cell { min-width: 150px; }
  .ops-mini-progress { height: 4px; margin: 8px 0; }
  .ops-status-pill { display: inline-flex; align-items: center; padding: 5px 8px; border-radius: 6px; font-size: 12px; }
  .ops-status-operational { color: var(--ops-green); background: var(--ops-green-soft); }
  .ops-status-missing-head { color: var(--ops-red); background: var(--ops-red-soft); }
  .ops-status-needs-mapping { color: var(--ops-amber); background: var(--ops-amber-soft); }
  .ops-status-no-records { color: #546275; background: #eef1f5; }
  .ops-filter-empty { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 32px; font-size: 12px; color: var(--ops-muted); }
  .ops-dashboard [hidden] { display: none !important; }
  .ops-municipality-note { padding: 14px 20px; border-top: 1px solid var(--ops-border); background: #fafbf9; color: var(--ops-muted); font-size: 12px; line-height: 1.6; }
  @media (max-width: 1250px) {
    .ops-overview { grid-template-columns: minmax(0, 1fr); padding: 25px; gap: 20px; }
    .ops-kpi { padding: 17px; }
    .ops-layout { grid-template-columns: minmax(0, 1.35fr) minmax(290px, 1fr); gap: 16px; }
    .ops-province-summary { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .ops-province-summary article:nth-child(3) { border-right: 0; }
    .ops-province-summary article:nth-child(-n+3) { border-bottom: 1px solid var(--ops-border); }
  }
  @media (max-width: 1050px) {
    .ops-date { display: none; }
    .ops-overview { grid-template-columns: 1fr; }
    .ops-field-summary { display: none; }
    .ops-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ops-layout { display: flex; flex-direction: column; }
    .ops-main-column, .ops-side-column { display: contents; }
    .ops-layout > * > .ops-panel { width: 100%; }
    .ops-attention-panel { order: -1; }
    .ops-month-label, .ops-month-stat { padding: 10px; }
  }
  @media (max-width: 620px) {
    body:has(.ops-dashboard) .container { padding: 18px 12px 30px; }
    .ops-dashboard { gap: 18px; }
    .ops-header h1 { font-size: 28px; }
    .ops-overview { padding: 22px; border-radius: 12px; }
    .ops-actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ops-actions .ops-button { padding: 10px 8px; font-size: 12px; line-height: 1.4; }
    .ops-actions .ops-button svg { display: none; }
    .ops-section-heading > span { max-width: 90px; text-align: right; line-height: 1.5; }
    .ops-kpi-grid { gap: 10px; }
    .ops-kpi { padding: 14px; }
    .ops-kpi-top { align-items: flex-start; }
    .ops-kpi-label { font-size: 12px; line-height: 1.5; }
    .ops-icon { width: 27px; height: 27px; border-radius: 7px; }
    .ops-icon svg { width: 15px; height: 15px; }
    .ops-kpi-value { font-size: 27px; margin: 16px 0; }
    .ops-kpi-foot { font-size: 12px; align-items: flex-start; gap: 8px; }
    .ops-kpi-value-row > span { font-size: 12px; margin-bottom: 8px; }
    .ops-month-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ops-month-label { grid-column: 1 / -1; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #d6e1d6; }
    .ops-month-label span { margin: 0; }
    .ops-month-stat { border-left: 0; padding: 13px 10px; }
    .ops-panel-header { padding: 18px; flex-wrap: wrap; }
    .ops-panel-header h2 { font-size: 16px; }
    .ops-chart-wrap { height: 240px; padding: 12px; }
    .ops-chart-data dl { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ops-province-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ops-province-summary article { border-bottom: 1px solid var(--ops-border); padding: 14px; }
    .ops-province-summary article:nth-child(3) { border-right: 1px solid var(--ops-border); }
    .ops-province-summary article:nth-child(even) { border-right: 0; }
    .ops-municipality-search { flex-basis: 100%; }
    .ops-toolbar-field { flex: 1; flex-direction: column; align-items: stretch; min-width: 0; }
    .ops-toolbar-field select { width: 100%; min-width: 0; }
    .ops-visible-count { width: 100%; }
  }
  @media (prefers-reduced-motion: reduce) { .ops-dashboard * { transition: none !important; } }
  .ops-dashboard { font-family: var(--ui-font); font-size:14px; line-height:1.5; gap:24px; }
  .ops-header p,.ops-overview p,.ops-panel-header p,.ops-button,.ops-table td,.ops-record-body strong,.ops-attention-row strong { font-size:14px; }
  .ops-panel-header h2 { font-size:18px; }
  .ops-button { font-weight:500; }
  .ops-text-link,.ops-row-link,.ops-cell-link,.ops-kpi-foot a { display:inline-flex; align-items:center; min-height:38px; }
  .ops-municipality-search input,.ops-toolbar-field select { font-size:16px; border-color:var(--ui-control-border); }
  .ops-toolbar-field { font-size:14px; }
  .ops-reports { min-width:0; border:1px solid var(--ops-border); border-radius:12px; background:var(--ops-surface); }
  .ops-reports > summary { display:flex; align-items:center; gap:16px; padding:20px 24px; min-height:64px; cursor:pointer; list-style:none; }
  .ops-reports > summary::-webkit-details-marker { display:none; }
  .ops-reports > summary::after { content:'+'; margin-left:auto; color:var(--ops-green); font-size:24px; }
  .ops-reports[open] > summary::after { content:'−'; }
  .ops-reports > summary strong { display:block; font-size:18px; font-weight:500; }
  .ops-reports > summary small { display:block; color:var(--ops-muted); font-size:14px; margin-top:4px; }
  .ops-reports-content { display:flex; flex-direction:column; gap:24px; padding:0 20px 20px; min-width:0; }
  .ops-report-kpis { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; }
  .ops-chart-data h3 { font-size:14px; color:var(--ops-ink); }
  @media (max-width:1250px) { .ops-kpi-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
  @media (max-width:620px) { .ops-actions { grid-template-columns:1fr; } .ops-actions .ops-button {font-size:14px;} .ops-report-kpis {grid-template-columns:1fr;} .ops-reports-content {padding:0 12px 12px;} .ops-reports > summary {padding:16px;} }
  @media (max-width:380px) { .ops-kpi-grid {grid-template-columns:1fr;} .ops-chart-data dl {grid-template-columns:1fr;} }
</style>
