@extends('layouts.app')

@section('content')
<main class="ops-page" aria-labelledby="visitor-dashboard-title">
  <section class="ops-page-header">
    <div>
      <p class="ops-eyebrow">AgriLGU system overview</p>
      <h1 id="visitor-dashboard-title">Welcome, visitor</h1>
      <p class="ops-page-subtitle">This read-only overview shows system coverage without farmer records or personal information.</p>
    </div>
    <a class="ops-button ops-button-primary" href="{{ route('municipality-boundaries.index') }}">Open boundary map</a>
  </section>

  <section class="ops-kpi-grid" aria-label="System coverage">
    <article class="ops-kpi-card"><span class="ops-kpi-label">Active provinces</span><strong class="ops-kpi-value">{{ number_format($provinceCount) }}</strong><span class="ops-kpi-note">Configured administrative scope</span></article>
    <article class="ops-kpi-card"><span class="ops-kpi-label">Active municipalities</span><strong class="ops-kpi-value">{{ number_format($municipalityCount) }}</strong><span class="ops-kpi-note">Available in the boundary viewer</span></article>
    <article class="ops-kpi-card"><span class="ops-kpi-label">Boundary references</span><strong class="ops-kpi-value">{{ number_format($boundaryCount) }}</strong><span class="ops-kpi-note">Read-only planning geometry</span></article>
  </section>

  <section class="ops-card" aria-labelledby="visitor-access-title">
    <div class="ops-card-header"><div><p class="ops-eyebrow">Visitor access</p><h2 id="visitor-access-title">What you can review</h2></div></div>
    <div class="ops-empty"><strong>Administrative boundaries only</strong><span>Farmer profiles, parcels, assistance, harvests, reports, exports, accounts, and audit details are unavailable to this account.</span></div>
  </section>
</main>
@endsection
