@once
<style>
  .user-management-page,.user-editor-page { display:flex;flex-direction:column;gap:24px;color:var(--ui-text);font-family:var(--ui-font);font-size:14px;line-height:1.5;min-width:0; }
  .user-editor-page { max-width:960px;margin:0 auto; }
  .user-management-hero,.user-editor-hero { display:flex;align-items:flex-start;justify-content:space-between;gap:24px;padding:24px;border:1px solid var(--ui-border);border-radius:12px;background:var(--ui-surface);color:var(--ui-text);box-shadow:none; }
  .user-management-hero h1,.user-editor-hero h1 { margin:8px 0;font-size:28px;font-weight:700;line-height:1.2; }
  .user-management-hero p,.user-editor-hero p { max-width:720px;margin:0;color:var(--ui-text-muted);font-size:14px;line-height:1.5; }
  .user-management-eyebrow,.user-editor-eyebrow { padding:0;border:0;border-radius:0;background:none;color:var(--ui-primary);font-size:12px;font-weight:500;letter-spacing:0;text-transform:none; }
  .user-management-eyebrow span { display:none; }
  .user-create-btn,.user-apply-btn,.user-save-button { color:white!important;background:var(--ui-primary)!important;border-color:var(--ui-primary)!important;box-shadow:none; }
  .user-create-btn:hover,.user-apply-btn:hover,.user-save-button:hover { background:var(--ui-primary-hover)!important; }
  .user-filter-card,.user-table-card,.user-form-card { border:1px solid var(--ui-border);border-radius:12px;background:var(--ui-surface);box-shadow:none;min-width:0; }
  .user-filter-card,.user-form-card { padding:24px; }
  .user-filter-grid { display:grid;grid-template-columns:minmax(180px,1.5fr) repeat(2,minmax(160px,1fr));gap:16px;align-items:end; }
  .user-advanced-filters { grid-column:1/-1; }
  .user-advanced-filters>.user-field-grid { padding:16px; }
  .user-filter-actions { grid-column:1/-1;justify-content:flex-end; }
  .user-filter-heading h2,.user-table-heading h2,.user-form-card h2 { margin:0;color:var(--ui-text);font-size:18px;font-weight:500; }
  .user-filter-heading p,.user-table-heading p,.user-form-card p { margin:4px 0 0;color:var(--ui-text-muted);font-size:14px;line-height:1.5; }
  .user-filter-field label,.user-field label { display:block;margin:0 0 8px;color:var(--ui-text);font-size:14px;font-weight:500;letter-spacing:0;text-transform:none; }
  .user-field label>span { color:var(--ui-danger); }
  .user-field small { display:block;margin-top:6px;color:var(--ui-text-muted);font-size:12px;line-height:1.5; }
  .user-form-errors ul { margin:8px 0 0;padding-left:20px; }
  .user-form-grid { display:grid;gap:24px; }
  .user-form-card-head { margin-bottom:20px; }
  .user-field-grid { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px; }
  .user-field { min-width:0; }
  .user-field-wide { grid-column:1/-1; }
  .user-password-section>summary { cursor:pointer;min-height:44px; }
  .user-password-section>summary strong { font-size:18px;font-weight:500; }
  .user-password-section>summary span { display:block;margin-top:4px;font-size:14px;color:var(--ui-text-muted); }
  .user-password-section[open]>.user-field-grid { margin-top:20px; }
  .locked-value { min-height:44px;padding:12px;border:1px solid var(--ui-border);border-radius:8px;background:var(--ui-surface-subtle); }
  .locked-value strong { display:block;font-size:14px;font-weight:500; }
  .locked-value.is-success { background:var(--ui-primary-soft); }
  .active-toggle { display:flex!important;align-items:center;gap:12px;min-height:44px;padding:12px;border:1px solid var(--ui-border);border-radius:8px;background:var(--ui-surface-subtle);cursor:pointer; }
  .active-toggle input { width:20px;height:20px;accent-color:var(--ui-primary); }
  .active-toggle .active-toggle-ui { display:none; }
  .active-toggle strong { display:block;color:var(--ui-text);font-size:14px;font-weight:500; }
  .password-field-wrap { position:relative; }
  .password-field-wrap .module-input { padding-right:72px;min-height:48px; }
  .password-peek { position:absolute;right:3px;top:50%;transform:translateY(-50%);min-height:44px;min-width:60px;border:0;border-radius:6px;background:var(--ui-surface-subtle);color:var(--ui-primary);font:500 14px var(--ui-font);cursor:pointer; }
  .user-form-actions { display:flex;justify-content:flex-end;gap:12px;flex-wrap:wrap;padding:20px 0; }
  .user-table-scroll { position:relative;overflow-x:auto; }
  .user-table { font-size:14px; }
  .user-table th { font-size:13px;font-weight:500;letter-spacing:0;text-transform:none; }
  .user-list-name,.user-office-name,.user-date-main { color:var(--ui-text);font-size:14px;font-weight:500; }
  .user-list-email,.user-office-sub,.user-date-sub { font-size:12px; }
  .user-row-btn { min-height:38px;font-size:12px!important; }
  .user-stat-grid { padding:0 20px 20px;gap:16px; }
  .user-stat-card { border-radius:8px;box-shadow:none; }
  .user-stat-card:after { display:none; }
  .user-stat-card strong { font-weight:500; }
  #municipalityField.is-hidden,
  #provinceField.is-hidden { display:none; }
  .user-editor-page :is(input,button,select,a,summary):focus-visible,.user-management-page :is(input,button,select,a,summary):focus-visible { outline:3px solid var(--ui-focus);outline-offset:3px; }
  @media(max-width:1050px) { .user-filter-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }.user-filter-search { grid-column:1/-1; } }
  @media(max-width:700px) { .user-management-hero,.user-editor-hero { flex-direction:column;padding:20px; }.user-field-grid,.user-filter-grid { grid-template-columns:1fr; }.user-field-wide { grid-column:auto; }.user-form-card,.user-filter-card { padding:16px; }.user-form-actions { flex-direction:column; }.user-form-actions>.module-button { width:100%; } }
</style>
@endonce
