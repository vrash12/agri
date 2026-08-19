@extends('layouts.app')

@section('title', 'Audit Event #'.$auditLog->id)

@push('styles')
  @include('partials.operations-ui-styles')
  <style>
    .audit-detail-hero{background:linear-gradient(112deg,#fff 0%,#f8fbf8 60%,#edf5f8 100%)}
    .audit-detail-grid{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(280px,.8fr);gap:13px;align-items:start}.audit-stack{display:flex;flex-direction:column;gap:13px}
    .audit-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0;padding:4px 16px 16px}.audit-summary div{min-width:0;padding:13px 12px;border-right:1px solid #e8ede9;border-bottom:1px solid #e8ede9}.audit-summary div:nth-child(3n){border-right:0}.audit-summary dt{color:var(--module-muted);font-size:8px;font-weight:900;letter-spacing:.05em;text-transform:uppercase}.audit-summary dd{margin:5px 0 0;overflow-wrap:anywhere;color:var(--module-ink);font-size:10px;font-weight:750;line-height:1.45}.audit-summary dd.module-mono{font-size:9px}
    .audit-change-table{width:100%;border-collapse:collapse}.audit-change-table th{padding:10px 13px;color:var(--module-muted);background:#f8faf8;border-bottom:1px solid var(--module-border);font-size:8px;font-weight:900;letter-spacing:.05em;text-align:left;text-transform:uppercase}.audit-change-table td{width:40%;padding:12px 13px;border-bottom:1px solid #edf1ee;color:#45534a;font-size:10px;line-height:1.5;vertical-align:top;overflow-wrap:anywhere}.audit-change-table td:first-child{width:20%;color:var(--module-ink);font-weight:850}.audit-change-table tr:last-child td{border-bottom:0}.audit-value{display:block;max-height:170px;overflow:auto;white-space:pre-wrap}.audit-value-old{color:#8d4242}.audit-value-new{color:#17643a}.audit-empty-value{color:#8a958e;font-style:italic}
    .audit-context{padding:4px 16px 16px}.audit-context-row{padding:11px 0;border-bottom:1px solid #edf1ee}.audit-context-row:last-child{border-bottom:0}.audit-context-row span{display:block;color:var(--module-muted);font-size:8px;font-weight:900;letter-spacing:.045em;text-transform:uppercase}.audit-context-row strong,.audit-context-row code{display:block;margin-top:5px;overflow-wrap:anywhere;color:var(--module-ink);font-size:9px;line-height:1.5}.audit-context-row code{padding:9px;border-radius:7px;background:#f6f8f6;white-space:pre-wrap}
    .audit-lock-note{display:flex;gap:9px;padding:13px;border:1px solid #dce5df;border-radius:9px;color:#57645c;background:#f8faf8;font-size:9px;line-height:1.5}.audit-lock-note svg{width:18px;height:18px;flex:0 0 auto;color:var(--module-green);fill:none;stroke:currentColor;stroke-width:1.8}
    .module-badge-red{color:var(--module-red);background:var(--module-red-soft)}.module-badge-neutral{color:#526057;background:#edf1ee}
    @media(max-width:1050px){.audit-detail-grid{grid-template-columns:1fr}}
    @media(max-width:650px){.audit-summary{grid-template-columns:1fr}.audit-summary div,.audit-summary div:nth-child(3n){border-right:0}.audit-change-table thead{display:none}.audit-change-table,.audit-change-table tbody,.audit-change-table tr,.audit-change-table td{display:block;width:100%}.audit-change-table tr{padding:11px 13px;border-bottom:1px solid #e6ece8}.audit-change-table td{padding:4px 0;border:0}.audit-change-table td:first-child{width:100%;margin-bottom:5px}.audit-change-table td:nth-child(2):before{content:'Before';display:block;color:var(--module-muted);font-size:8px;font-weight:900;text-transform:uppercase}.audit-change-table td:nth-child(3):before{content:'After';display:block;margin-top:6px;color:var(--module-muted);font-size:8px;font-weight:900;text-transform:uppercase}}
  </style>
@endpush

@php
  $roleLabels = [
      'super_admin' => 'Super Admin',
      'provincial_staff' => 'Provincial Staff',
      'municipal_head' => 'Head Agriculturist',
      'municipal_staff' => 'Municipal Staff',
  ];
  $oldValues = collect($auditLog->old_values ?? []);
  $newValues = collect($auditLog->new_values ?? []);
  $changeKeys = $oldValues->keys()->merge($newValues->keys())->unique()->sort()->values();
  $formatValue = function ($value) {
      if ($value === null || $value === '') return 'Not set';
      if (is_bool($value)) return $value ? 'Yes' : 'No';
      if (is_array($value)) return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      return (string) $value;
  };
  $metadata = collect($auditLog->metadata ?? []);
@endphp

@section('content')
<div class="module-page">
  <header class="module-header audit-detail-hero">
    <div>
      <div class="module-eyebrow">Audit event #{{ $auditLog->id }}</div>
      <h1>{{ $auditLog->event_label }} · {{ $auditLog->module }}</h1>
      <p>{{ $auditLog->description }}</p>
    </div>
    <div class="module-actions">
      <span class="module-badge module-badge-{{ $auditLog->event_tone === 'neutral' ? 'neutral' : $auditLog->event_tone }}">{{ $auditLog->event_label }}</span>
      <a class="module-button" href="{{ route('audit-logs.index') }}">Back to audit trail</a>
    </div>
  </header>

  <div class="audit-detail-grid">
    <div class="audit-stack">
      <section class="module-panel">
        <div class="module-panel-head"><div><h2>Event summary</h2><p>The identity, scope, and source captured at the time of the activity.</p></div></div>
        <dl class="audit-summary">
          <div><dt>Date and time</dt><dd>{{ $auditLog->created_at?->format('F d, Y · h:i:s A') }}<br>{{ $auditLog->created_at?->diffForHumans() }}</dd></div>
          <div><dt>Actor</dt><dd>{{ $auditLog->actor_name ?: 'System / unknown' }}<br>{{ $auditLog->actor_email ?: 'No account email' }}</dd></div>
          <div><dt>Role at the time</dt><dd>{{ $roleLabels[$auditLog->actor_role] ?? ($auditLog->actor_role ? Str::headline($auditLog->actor_role) : 'Unattributed event') }}</dd></div>
          <div><dt>Municipality</dt><dd>{{ $auditLog->municipality?->name ?? 'Province-wide / unassigned' }}</dd></div>
          <div><dt>Related record</dt><dd>{{ $auditLog->auditable_type ? class_basename($auditLog->auditable_type).' #'.$auditLog->auditable_id : 'No model record' }}</dd></div>
          <div><dt>Source address</dt><dd class="module-mono">{{ $auditLog->ip_address ?: 'Not captured' }}</dd></div>
        </dl>
      </section>

      <section class="module-panel">
        <div class="module-panel-head"><div><h2>Before and after</h2><p>Only fields involved in this event are displayed. Protected values are excluded at capture time.</p></div><span class="module-panel-tag">{{ $changeKeys->count() }} {{ Str::plural('field', $changeKeys->count()) }}</span></div>
        @if($changeKeys->isNotEmpty())
          <div class="module-table-scroll">
            <table class="audit-change-table">
              <thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead>
              <tbody>
                @foreach($changeKeys as $key)
                  <tr>
                    <td>{{ Str::headline($key) }}</td>
                    <td><span class="audit-value audit-value-old {{ !$oldValues->has($key) ? 'audit-empty-value' : '' }}">{{ $oldValues->has($key) ? $formatValue($oldValues->get($key)) : 'Not applicable' }}</span></td>
                    <td><span class="audit-value audit-value-new {{ !$newValues->has($key) ? 'audit-empty-value' : '' }}">{{ $newValues->has($key) ? $formatValue($newValues->get($key)) : 'Not applicable' }}</span></td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @else
          <div class="module-empty"><span class="module-empty-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"></circle><path d="M12 8v4M12 16h.01"></path></svg></span><strong>No changed field values</strong><span>This activity records access or context rather than a model field change.</span></div>
        @endif
      </section>
    </div>

    <aside class="audit-stack">
      <section class="module-panel">
        <div class="module-panel-head"><div><h2>Request context</h2><p>Technical evidence for troubleshooting and security review.</p></div></div>
        <div class="audit-context">
          <div class="audit-context-row"><span>Request</span><strong>{{ $auditLog->request_method ?: 'Background event' }} {{ $auditLog->request_url ?: '' }}</strong></div>
          <div class="audit-context-row"><span>IP address</span><strong class="module-mono">{{ $auditLog->ip_address ?: 'Not captured' }}</strong></div>
          <div class="audit-context-row"><span>Browser / client</span><strong>{{ $auditLog->user_agent ?: 'Not captured' }}</strong></div>
          @if($metadata->isNotEmpty())<div class="audit-context-row"><span>Additional context</span><code>{{ json_encode($metadata->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</code></div>@endif
        </div>
      </section>

      <div class="audit-lock-note">
        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"></path></svg>
        <span><strong>Read-only evidence.</strong> Audit events cannot be edited or deleted from the application. Account snapshots remain visible even when the related user or operational record no longer exists.</span>
      </div>
    </aside>
  </div>
</div>
@endsection
