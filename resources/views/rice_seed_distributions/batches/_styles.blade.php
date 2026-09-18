{{--
  Layout-only additions for the rice seed distribution sheet. Every colour comes
  from the shared design tokens in partials.design-tokens; this file introduces
  no second theme and stays scoped to the .rice-sheet-* / .rice-print-* classes.
--}}
@once
<style>
  /* The shared styles do not define .sr-only outside the dashboard, so scope it here. */
  .rice-sheet-page .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap;border:0}
  .rice-sheet-facts{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:0;margin:0}
  .rice-sheet-facts>div{min-width:0;padding:14px 16px;border-top:1px solid var(--ui-border);border-right:1px solid var(--ui-border)}
  .rice-sheet-facts dt{color:var(--ui-text-muted);font-size:12px;font-weight:500}
  .rice-sheet-facts dd{margin:4px 0 0;color:var(--ui-text);font-size:14px;font-weight:500;line-height:1.5;overflow-wrap:anywhere}
  .rice-sheet-facts dd.rice-sheet-missing{color:var(--ui-text-muted);font-weight:400}

  .rice-sheet-notice{display:block;padding:12px 16px;border:1px solid var(--ui-border);border-radius:var(--ui-radius-panel);background:var(--ui-surface);color:var(--ui-text);font-size:14px;line-height:1.5}
  .rice-sheet-notice-warning{border-color:var(--ui-warning);background:var(--ui-warning-soft);color:var(--ui-warning)}
  .rice-sheet-notice-info{border-color:var(--ui-info);background:var(--ui-info-soft);color:var(--ui-info)}
  .rice-sheet-notice strong{display:block;font-size:14px;font-weight:700}
  .rice-sheet-notice a{color:inherit}

  .rice-sheet-register .module-table{min-width:880px}
  .rice-sheet-register .module-table td small{white-space:normal}
  .rice-sheet-register .module-person-copy strong,.rice-sheet-register .module-person-copy small{white-space:normal;overflow-wrap:anywhere}
  .rice-sheet-register .module-row-actions{flex-wrap:wrap;white-space:normal}
  .rice-sheet-measure{display:block;font-variant-numeric:tabular-nums}

  .rice-sheet-section-harvest{border-left:4px solid var(--ui-info)}
  .rice-sheet-section-receipt{border-left:4px solid var(--ui-primary)}
  .rice-sheet-section-harvest>summary{gap:8px;padding:20px 24px;font-size:18px}
  .rice-sheet-section-harvest>summary .module-hint{margin:0;font-weight:400}
  .rice-sheet-selected{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;grid-column:1/-1;padding:12px 16px;border:1px solid var(--ui-border);border-radius:var(--ui-radius-panel);background:var(--ui-surface-subtle)}
  .rice-sheet-selected div{min-width:0}
  .rice-sheet-selected dt{color:var(--ui-text-muted);font-size:12px;font-weight:500}
  .rice-sheet-selected dd{margin:4px 0 0;color:var(--ui-text);font-size:14px;font-weight:500;overflow-wrap:anywhere}

  .rice-sheet-total{display:flex;flex-direction:column;min-width:0}
  .rice-sheet-total-label{display:block;margin:0 0 8px;color:#45534a;font-size:14px;font-weight:500}
  .rice-sheet-total-value{display:flex;align-items:center;min-height:44px;padding:0 10px;border:1px dashed var(--ui-control-border);border-radius:var(--ui-radius-control);background:var(--ui-surface-subtle);color:var(--ui-text);font-size:16px;font-variant-numeric:tabular-nums;overflow-wrap:anywhere}

  .rice-sheet-danger{padding:16px 24px;border-top:1px solid var(--ui-border)}
  .rice-sheet-danger h2{margin:0;color:var(--ui-text);font-size:18px;font-weight:700}
  .rice-sheet-danger p{margin:4px 0 12px;color:var(--ui-text-muted);font-size:14px;line-height:1.5}

  .module-form-actions [data-sheet-form-status]{margin:0 auto 0 0;max-width:46ch;text-align:left}
  .rice-sheet-submit.is-saving{opacity:.72;pointer-events:none}
  .rice-sheet-submit .rice-sheet-submit-busy{display:none}
  .rice-sheet-submit.is-saving .rice-sheet-submit-idle{display:none}
  .rice-sheet-submit.is-saving .rice-sheet-submit-busy{display:inline}

  .rice-print-scroll{overflow:auto;padding:16px;background:var(--ui-surface-subtle)}
  .rice-print-sheet{min-width:1180px;padding:20px;background:var(--ui-surface);color:var(--ui-text)}
  .rice-print-head{display:flex;align-items:flex-start;justify-content:space-between;gap:24px;margin-bottom:12px}
  .rice-print-head h2{margin:0 0 4px;font-size:18px;font-weight:700}
  .rice-print-head p{margin:0;color:var(--ui-text-muted);font-size:13px;line-height:1.5}
  .rice-print-head dl{display:grid;grid-template-columns:auto auto;gap:2px 12px;margin:0;font-size:13px}
  .rice-print-head dt{color:var(--ui-text-muted);font-weight:500}
  .rice-print-head dd{margin:0;font-weight:700;text-align:right}
  .rice-print-table{width:100%;border-collapse:collapse;font-size:12px;line-height:1.35}
  .rice-print-table caption{padding-bottom:8px;color:var(--ui-text-muted);font-size:13px;text-align:left}
  .rice-print-table th,.rice-print-table td{padding:6px 5px;border:1px solid #b9c6bd;vertical-align:top;overflow-wrap:anywhere}
  .rice-print-table thead th{background:var(--ui-surface-subtle);color:var(--ui-text);font-size:11px;font-weight:700;text-align:center}
  .rice-print-table thead .rice-print-band th{background:var(--ui-primary-soft);color:var(--ui-primary);font-size:12px}
  .rice-print-table thead .rice-print-band th.rice-print-band-harvest{background:var(--ui-info-soft);color:var(--ui-info)}
  .rice-print-table thead .rice-print-band th.rice-print-band-receipt{background:var(--ui-accent-soft);color:var(--ui-on-accent)}
  .rice-print-table tbody td{height:30px}
  .rice-print-table .rice-print-numeric{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
  .rice-print-table .rice-print-sign{min-width:104px}
  .rice-print-table .rice-print-total td{background:var(--ui-surface-subtle);font-weight:700}
  .rice-print-signatories{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:24px;margin-top:24px;font-size:12px}
  .rice-print-signatories div{padding-top:28px;border-top:1px solid var(--ui-text)}
  .rice-print-signatories strong{display:block;font-weight:700}
  .rice-print-signatories span{display:block;color:var(--ui-text-muted)}
  .rice-print-footnote{margin-top:16px;color:var(--ui-text-muted);font-size:12px;line-height:1.5}

  @media(max-width:820px){
    .rice-sheet-register .module-table{display:block;min-width:0}
    .rice-sheet-register .module-table thead{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap}
    .rice-sheet-register .module-table tbody{display:block}
    .rice-sheet-register .rice-sheet-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:16px;border-top:1px solid var(--ui-border)}
    .rice-sheet-register .rice-sheet-row:first-child{border-top:0}
    .rice-sheet-register .rice-sheet-row td{display:block;min-width:0;padding:0;border:0;text-align:left;overflow-wrap:anywhere}
    .rice-sheet-register .rice-sheet-row td[data-label]::before{content:attr(data-label);display:block;margin-bottom:4px;color:var(--ui-text-muted);font-size:12px;font-weight:500}
    .rice-sheet-register .rice-sheet-row-farmer,.rice-sheet-register .rice-sheet-row-actions{grid-column:1/-1}
    .rice-sheet-register .module-row-actions{justify-content:flex-start}
    .rice-sheet-register .module-button-small{min-height:44px}
  }
  @media(max-width:560px){
    .rice-sheet-register .rice-sheet-row{grid-template-columns:1fr}
    .rice-sheet-facts>div{border-right:0}
  }

  @media print{
    @page{size:A4 landscape;margin:8mm}
    .skip-link,.overlay,.sidebar,.mobilebar,.idle-session-warning,.flash-success,.flash-error,.rice-print-screen-only{display:none!important}
    .app-shell,.main,.container{display:block!important;width:100%!important;max-width:none!important;margin:0!important;padding:0!important}
    .module-page{gap:0!important}
    .rice-print-scroll{overflow:visible!important;padding:0!important;background:#fff!important}
    .rice-print-sheet{min-width:0!important;padding:0!important;border:0!important;box-shadow:none!important}
    .rice-print-panel{border:0!important;border-radius:0!important;box-shadow:none!important}
    .rice-print-table{font-size:7.5pt}
    .rice-print-table thead{display:table-header-group}
    .rice-print-table tr{break-inside:avoid;page-break-inside:avoid}
    .rice-print-table th,.rice-print-table td{padding:2px 3px;border-color:#000;-webkit-print-color-adjust:exact;print-color-adjust:exact}
    .rice-print-table tbody td{height:10mm}
    .rice-print-table .rice-print-sign{width:28mm;min-width:28mm}
    .rice-print-signatories{margin-top:10mm;break-inside:avoid;page-break-inside:avoid}
  }
</style>
@endonce
