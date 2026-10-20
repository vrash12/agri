{{--
  Settings → LGU profiles & websites. Builds on the shared operations form system
  (module-form-shell / section / aside) and only adds what these three screens need:
  status badges, the issuer preview, image tiles and repeatable content entries.
--}}
@push('styles')
@once
<style>
  .lgu-set .module-header-actions{display:flex;gap:8px;flex-wrap:wrap}
  .lgu-set .module-header .module-scope-note{gap:8px}
  .lgu-set fieldset.lgu-set-fieldset{display:flex;flex-direction:column;gap:24px;min-width:0;margin:0;padding:0;border:0}
  .lgu-set .module-form-body{padding:20px 24px 24px}
  .lgu-set .module-form-section-head .lgu-icon{flex:0 0 auto;width:26px;height:26px;padding:4px;border-radius:7px;color:var(--ui-primary);background:var(--ui-primary-soft)}
  .lgu-set .module-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px 20px}
  .lgu-set .module-form-field{display:flex;flex-direction:column;gap:6px;min-width:0;grid-column:span 1}
  .lgu-set .module-form-field>label{margin:0}
  .lgu-set .module-form-field-full{grid-column:1/-1}
  .lgu-set .module-form-field>label,.lgu-set legend.lgu-set-legend{color:var(--ui-text);font-size:14px;font-weight:500}
  .lgu-set .module-form-field :is(input:not([type=checkbox]):not([type=radio]):not([type=file]),select,textarea){width:100%;min-height:44px;padding:10px 12px;border:1px solid var(--ui-control-border);border-radius:var(--ui-radius-control);color:var(--ui-text);background:var(--ui-surface);font:400 16px/1.4 var(--ui-font)}
  .lgu-set .module-form-field textarea{min-height:96px;resize:vertical;line-height:1.5}
  .lgu-set .module-form-field :is(input,select,textarea):focus-visible{outline:3px solid var(--ui-focus);outline-offset:1px}
  .lgu-set .module-form-field :is(input,select,textarea)[aria-invalid=true]{border-color:var(--ui-danger);box-shadow:inset 0 0 0 1px var(--ui-danger)}
  html:has(.lgu-set-sticky-actions){scroll-padding-bottom:110px}
  .lgu-set .module-form-field :is(input,textarea)[readonly]{background:var(--ui-surface-subtle);color:var(--ui-text-muted)}
  .lgu-set fieldset:disabled :is(input,select,textarea){background:var(--ui-surface-subtle);color:var(--ui-text);cursor:not-allowed}
  .lgu-set .module-hint{color:var(--ui-text-muted);font-size:13px;line-height:1.45}
  .lgu-set .lgu-set-note{display:flex;gap:10px;margin:16px 0 0;padding:12px 14px;border-radius:var(--ui-radius-control);color:var(--ui-text);background:var(--ui-surface-subtle);font-size:13px;line-height:1.5}
  .lgu-set .lgu-set-note .lgu-icon{flex:0 0 auto;color:var(--ui-primary)}
  .lgu-set .lgu-set-counter{align-self:flex-end;color:var(--ui-text-muted);font-size:12px;font-variant-numeric:tabular-nums}
  .lgu-set .module-alert{font-size:14px;font-weight:500}
  .lgu-set .module-alert a{color:inherit}
  .lgu-set .lgu-set-alert-info{color:var(--ui-info);border-color:#c8dbe7;background:var(--ui-info-soft)}
  .lgu-set .lgu-set-alert-warning{color:var(--ui-warning);border-color:#ecd8b0;background:var(--ui-warning-soft)}

  .lgu-badge{display:inline-flex;align-items:center;gap:6px;min-height:24px;padding:2px 10px;border-radius:999px;color:var(--ui-text-muted);background:#edf1ee;font-size:12px;font-weight:700;white-space:nowrap}
  .lgu-badge::before{content:'';width:7px;height:7px;border-radius:50%;background:currentColor}
  .lgu-badge-green{color:var(--ui-primary);background:var(--ui-primary-soft)}
  .lgu-badge-amber{color:var(--ui-warning);background:var(--ui-warning-soft)}
  .lgu-badge-blue{color:var(--ui-info);background:var(--ui-info-soft)}

  /* Image tiles and file pickers */
  .lgu-set-media{display:grid;grid-template-columns:auto minmax(0,1fr);gap:20px;align-items:start}
  .lgu-set-tile{display:grid;place-items:center;width:132px;height:132px;padding:12px;border:1px solid var(--ui-border);border-radius:var(--ui-radius-panel);background:repeating-conic-gradient(#f3f6f4 0 25%,#fff 0 50%) 0 0/16px 16px;color:var(--ui-text-muted);text-align:center;font-size:12px;overflow:hidden}
  .lgu-set-tile img{width:100%;height:100%;object-fit:contain}
  .lgu-set-tile-cover{width:240px;height:135px;padding:0;background:var(--ui-surface-subtle)}
  .lgu-set-tile-cover img{object-fit:cover}
  .lgu-set-tile .lgu-icon{width:28px;height:28px;margin:0 auto 6px;color:var(--ui-control-border)}
  .lgu-set-picker{display:flex;flex-direction:column;gap:12px;min-width:0}
  .lgu-set .module-dropzone{position:relative;flex-wrap:wrap}
  .lgu-set .module-dropzone:focus-within{outline:3px solid var(--ui-focus);outline-offset:2px}
  .lgu-set .module-dropzone input[type=file]{flex:1 1 220px;min-width:0;font-size:14px}
  .lgu-set-file-status{margin:0;color:var(--ui-text-muted);font-size:13px}
  .lgu-set-file-status[data-state=warning]{color:var(--ui-warning)}
  .lgu-set label.lgu-set-check{display:flex;margin:0;align-items:flex-start;gap:10px;min-height:44px;padding:10px 12px;border:1px solid var(--ui-border);border-radius:var(--ui-radius-control);background:var(--ui-surface);font-size:14px;cursor:pointer}
  .lgu-set-check input{width:18px;height:18px;margin:2px 0 0;accent-color:var(--ui-primary)}
  .lgu-set-check-hint{display:block;margin-top:2px}
  .lgu-set-tile.is-removing{opacity:.4}

  /* Website address and publication choice */
  .lgu-set-address{display:flex;align-items:stretch;border:1px solid var(--ui-control-border);border-radius:var(--ui-radius-control);background:var(--ui-surface);overflow:hidden}
  .lgu-set-address:focus-within{outline:3px solid var(--ui-focus);outline-offset:1px}
  .lgu-set-address>span{display:flex;align-items:center;padding:0 10px;color:var(--ui-text-muted);background:var(--ui-surface-subtle);font-size:14px;white-space:nowrap}
  .lgu-set .lgu-set-address input{flex:1;min-width:0;border:0!important;border-radius:0!important;outline:0!important}
  .lgu-set-url{display:block;margin-top:2px;padding:8px 10px;border-radius:var(--ui-radius-control);color:var(--ui-text);background:var(--ui-surface-subtle);font:500 13px/1.4 ui-monospace,SFMono-Regular,Consolas,monospace;overflow-wrap:anywhere}
  .lgu-set-choices{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:6px}
  .lgu-set label.lgu-set-choice{position:relative;display:flex;margin:0;color:var(--ui-text);gap:12px;padding:14px;border:1px solid var(--ui-border);border-radius:var(--ui-radius-panel);background:var(--ui-surface);cursor:pointer}
  .lgu-set-choice input{width:18px;height:18px;margin:2px 0 0;flex:0 0 auto;accent-color:var(--ui-primary)}
  .lgu-set-choice strong{display:block;font-size:14px}
  .lgu-set-choice span{display:block;margin-top:2px;color:var(--ui-text-muted);font-size:13px;line-height:1.45}
  .lgu-set-choice:has(input:checked){border-color:var(--ui-primary);background:var(--ui-primary-soft);box-shadow:inset 0 0 0 1px var(--ui-primary)}
  .lgu-set-choice:has(input:focus-visible){outline:3px solid var(--ui-focus);outline-offset:2px}
  .lgu-set fieldset.lgu-set-group{margin:0;padding:0;border:0;min-width:0}

  /* Repeatable services and announcements */
  .lgu-set-entries{display:flex;flex-direction:column;gap:10px}
  .lgu-set-entry{border:1px solid var(--ui-border);border-radius:var(--ui-radius-panel);background:var(--ui-surface)}
  .lgu-set-entry[open]{border-color:#b9cbbf;box-shadow:0 1px 0 rgba(23,62,45,.04)}
  .lgu-set-entry>summary{display:flex;align-items:center;gap:12px;min-height:52px;padding:8px 14px;list-style:none;cursor:pointer;border-radius:inherit}
  .lgu-set-entry>summary::-webkit-details-marker{display:none}
  .lgu-set-entry>summary::after{content:'+';margin-left:auto;color:var(--ui-text-muted);font-size:20px;line-height:1}
  .lgu-set-entry[open]>summary::after{content:'−'}
  .lgu-set-entry>summary:focus-visible{outline:3px solid var(--ui-focus);outline-offset:2px}
  .lgu-set-entry>summary:hover{background:var(--ui-surface-subtle)}
  .lgu-set-entry-number{display:grid;place-items:center;flex:0 0 auto;width:28px;height:28px;border-radius:7px;color:var(--ui-primary);background:var(--ui-primary-soft);font-size:12px;font-weight:700}
  .lgu-set-entry-title{min-width:0;font-weight:500;overflow-wrap:anywhere}
  .lgu-set-entry-title[data-empty]{color:var(--ui-text-muted);font-weight:400;font-style:italic}
  .lgu-set-entry .module-form-grid{padding:4px 14px 16px}
  .lgu-set-entries-meta{display:flex;justify-content:space-between;gap:12px;margin-bottom:12px;color:var(--ui-text-muted);font-size:13px}

  /* Aside: issuer preview, checklist, links */
  .lgu-set .module-aside-card{padding:16px;border-radius:var(--ui-radius-panel)}
  .lgu-set .module-aside-card h3{display:flex;align-items:center;gap:8px;margin:0 0 12px;font-size:13px;letter-spacing:.02em}
  .lgu-set .module-aside-card h3 .lgu-icon{width:18px;height:18px;color:var(--ui-primary)}
  .lgu-set .module-aside-card p,.lgu-set .module-aside-card li{font-size:13px}
  .lgu-issuer{overflow:hidden;border:1px solid #cfdcd3;border-radius:10px;background:linear-gradient(180deg,#f3f9f5 0,#fff 70%)}
  .lgu-issuer-band{height:6px;background:var(--ui-brand-stripe)}
  .lgu-issuer-head{display:flex;align-items:center;gap:10px;padding:12px}
  .lgu-issuer-logo{display:grid;place-items:center;flex:0 0 auto;width:46px;height:46px;border-radius:50%;background:#fff;border:1px solid var(--ui-border);overflow:hidden}
  .lgu-issuer-logo img{width:100%;height:100%;object-fit:contain}
  .lgu-issuer-logo .lgu-icon{color:var(--ui-control-border)}
  .lgu-issuer-office{display:block;color:var(--ui-brand-dark);font-size:12px;font-weight:700;line-height:1.25;text-transform:uppercase;overflow-wrap:anywhere}
  .lgu-issuer-place{display:block;margin-top:2px;color:var(--ui-text-muted);font-size:12px}
  .lgu-issuer-sign{margin:4px 12px 12px;padding-top:22px;text-align:center}
  .lgu-issuer-sign strong{display:block;padding-top:4px;border-top:1px solid var(--ui-text);font-size:12px;text-transform:uppercase;overflow-wrap:anywhere}
  .lgu-issuer-sign span{display:block;color:var(--ui-text-muted);font-size:12px}
  .lgu-issuer-sign[hidden]{display:none}
  .lgu-issuer-nosign{margin:0 12px 12px;color:var(--ui-text-muted);font-size:12px;font-style:italic}
  .lgu-set-list{display:flex;flex-direction:column;gap:8px;margin:0;padding:0!important;list-style:none}
  .lgu-set-list li{display:flex;align-items:flex-start;gap:8px;margin:0!important}
  .lgu-set-list .lgu-icon{flex:0 0 auto;width:18px;height:18px;margin-top:1px}
  .lgu-set-list [data-done=true] .lgu-icon{color:var(--ui-primary)}
  .lgu-set-list [data-done=false] .lgu-icon{color:var(--ui-control-border)}
  .lgu-set-list small{display:block;color:var(--ui-text-muted);font-size:12px}
  .lgu-set-links{display:flex;flex-direction:column;gap:8px}
  .lgu-set-links .module-button{justify-content:flex-start;width:100%}
  .lgu-set-sticky-actions{position:sticky;bottom:0;z-index:2;border:1px solid var(--ui-border);border-radius:var(--ui-radius-panel);box-shadow:0 -6px 18px rgba(23,62,45,.06)}

  /* Directory */
  .lgu-set-search{display:flex;align-items:flex-end;gap:12px;padding:16px 20px;flex-wrap:wrap}
  .lgu-set-search .module-form-field{flex:1 1 280px}
  .lgu-set-search .module-actions{display:flex;gap:8px}
  .lgu-set-table td{vertical-align:middle}
  .lgu-set-lgu{display:flex;flex-direction:column}
  .lgu-set-lgu strong{font-size:14px}
  .lgu-set-lgu span{color:var(--ui-text-muted);font-size:13px}
  .lgu-set-row-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
  .lgu-set-count{margin:0;color:var(--ui-text-muted);font-size:13px}
  .lgu-sr-only{position:absolute!important;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0}

  @media(max-width:1180px){.lgu-set .module-form-aside{grid-template-columns:repeat(2,minmax(0,1fr))}}
  @media(max-width:700px){
    .lgu-set .module-form-grid,.lgu-set-choices,.lgu-set .module-form-aside{grid-template-columns:1fr}
    .lgu-set .module-form-field{grid-column:1/-1}
    .lgu-set-media{grid-template-columns:1fr}
    .lgu-set-tile-cover{width:100%;height:auto;aspect-ratio:16/9}
    .lgu-set-address{flex-direction:column}
    .lgu-set-address>span{padding:6px 10px}
    .lgu-set .module-form-body{padding:16px}
    .lgu-set-row-actions{justify-content:flex-start}
    .lgu-set-sticky-actions{position:static}
  }
  @media(prefers-reduced-motion:reduce){.lgu-set *{transition:none!important}}
</style>
@endonce
@endpush
