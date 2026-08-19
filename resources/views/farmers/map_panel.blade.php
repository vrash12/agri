{{-- resources/views/farmers/map_panel.blade.php --}}

@push('styles')
<style>
  /*
  |--------------------------------------------------------------------------
  | Enhanced Farmer Details Panel
  |--------------------------------------------------------------------------
  | These styles are scoped to .farmer-detail-panel so they do not interfere
  | with the other map components.
  */

  .farmer-detail-panel {
    --fd-green: #16a34a;
    --fd-green-dark: #166534;
    --fd-green-soft: #ecfdf5;
    --fd-blue: #2563eb;
    --fd-blue-soft: #eff6ff;
    --fd-amber: #d97706;
    --fd-amber-soft: #fffbeb;
    --fd-purple: #7c3aed;
    --fd-purple-soft: #f5f3ff;
    --fd-text: #0f172a;
    --fd-muted: #64748b;
    --fd-border: #e2e8f0;
    --fd-bg: #f8fafc;

    padding: 12px;
    background:
      radial-gradient(
        circle at top right,
        rgba(34, 197, 94, 0.07),
        transparent 28%
      ),
      #f8fafc;
  }

  .farmer-detail-panel .fd-card {
    min-height: 100%;
    overflow: hidden;
    border: 1px solid var(--fd-border);
    border-radius: 22px;
    background: #ffffff;
    box-shadow: 0 16px 34px rgba(15, 23, 42, 0.08);
  }

  /*
  |--------------------------------------------------------------------------
  | Farmer Profile Header
  |--------------------------------------------------------------------------
  */

  .farmer-detail-panel .fd-profile-header {
    position: sticky;
    top: 0;
    z-index: 5;
    padding: 18px;
    border-bottom: 1px solid var(--fd-border);
    background:
      radial-gradient(
        circle at top right,
        rgba(250, 204, 21, 0.16),
        transparent 38%
      ),
      radial-gradient(
        circle at bottom left,
        rgba(34, 197, 94, 0.14),
        transparent 42%
      ),
      linear-gradient(145deg, #ffffff, #f7fff9);
    backdrop-filter: blur(12px);
  }

  .farmer-detail-panel .fd-profile-top {
    display: grid;
    grid-template-columns: 54px minmax(0, 1fr) auto;
    gap: 13px;
    align-items: start;
  }

  .farmer-detail-panel .fd-avatar {
    width: 54px;
    height: 54px;
    display: grid;
    place-items: center;
    border: 1px solid rgba(34, 197, 94, 0.24);
    border-radius: 18px;
    color: var(--fd-green-dark);
    background:
      linear-gradient(
        145deg,
        rgba(34, 197, 94, 0.18),
        rgba(250, 204, 21, 0.12)
      );
    box-shadow: 0 12px 24px rgba(22, 163, 74, 0.12);
  }

  .farmer-detail-panel .fd-avatar svg {
    width: 26px;
    height: 26px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.9;
    stroke-linecap: round;
    stroke-linejoin: round;
  }

  .farmer-detail-panel .fd-profile-copy {
    min-width: 0;
  }

  .farmer-detail-panel .fd-eyebrow {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 5px;
    color: var(--fd-green-dark);
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 0.55px;
    text-transform: uppercase;
  }

  .farmer-detail-panel .fd-eyebrow-dot {
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: #22c55e;
    box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.13);
  }

  .farmer-detail-panel .fd-farmer-name {
    margin: 0;
    overflow: hidden;
    color: var(--fd-text);
    font-size: 18px;
    font-weight: 950;
    line-height: 1.25;
    text-overflow: ellipsis;
  }

  .farmer-detail-panel .fd-identity-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 7px;
    margin-top: 8px;
  }

  .farmer-detail-panel .fd-identity-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    max-width: 100%;
    padding: 5px 9px;
    border: 1px solid var(--fd-border);
    border-radius: 999px;
    color: #475569;
    background: rgba(255, 255, 255, 0.9);
    font-size: 11px;
    font-weight: 850;
  }

  .farmer-detail-panel .fd-identity-chip strong {
    overflow: hidden;
    color: var(--fd-text);
    font-family:
      ui-monospace,
      SFMono-Regular,
      Menlo,
      Monaco,
      Consolas,
      monospace;
    text-overflow: ellipsis;
  }

  .farmer-detail-panel .fd-clear-button {
    width: 38px;
    height: 38px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    padding: 0 !important;
    border-radius: 13px !important;
    color: #475569 !important;
    background: rgba(255, 255, 255, 0.88) !important;
  }

  .farmer-detail-panel .fd-clear-button svg {
    width: 17px;
    height: 17px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
  }

  .farmer-detail-panel .fd-location-card {
    display: grid;
    grid-template-columns: 34px minmax(0, 1fr);
    gap: 10px;
    align-items: center;
    margin-top: 14px;
    padding: 10px 12px;
    border: 1px solid rgba(37, 99, 235, 0.14);
    border-radius: 15px;
    background: rgba(239, 246, 255, 0.72);
  }

  .farmer-detail-panel .fd-location-icon {
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    border-radius: 12px;
    color: var(--fd-blue);
    background: #ffffff;
    box-shadow: 0 5px 13px rgba(37, 99, 235, 0.09);
  }

  .farmer-detail-panel .fd-location-icon svg {
    width: 17px;
    height: 17px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
  }

  .farmer-detail-panel .fd-location-label {
    margin-bottom: 2px;
    color: var(--fd-muted);
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 0.35px;
    text-transform: uppercase;
  }

  .farmer-detail-panel .fd-location-value {
    color: var(--fd-text);
    font-size: 12px;
    font-weight: 850;
    line-height: 1.4;
    word-break: break-word;
  }

  /*
  |--------------------------------------------------------------------------
  | Panel Body
  |--------------------------------------------------------------------------
  */

  .farmer-detail-panel .fd-body {
    padding: 16px;
  }

  .farmer-detail-panel .fd-section {
    margin-top: 20px;
  }

  .farmer-detail-panel .fd-section:first-child {
    margin-top: 0;
  }

  .farmer-detail-panel .fd-section-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 11px;
  }

  .farmer-detail-panel .fd-section-title-row {
    display: flex;
    align-items: center;
    gap: 9px;
  }

  .farmer-detail-panel .fd-section-icon {
    width: 32px;
    height: 32px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border-radius: 11px;
    color: var(--fd-green-dark);
    background: var(--fd-green-soft);
  }

  .farmer-detail-panel .fd-section-icon.blue {
    color: var(--fd-blue);
    background: var(--fd-blue-soft);
  }

  .farmer-detail-panel .fd-section-icon.purple {
    color: var(--fd-purple);
    background: var(--fd-purple-soft);
  }

  .farmer-detail-panel .fd-section-icon svg {
    width: 17px;
    height: 17px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
  }

  .farmer-detail-panel .fd-section-title {
    margin: 0;
    color: var(--fd-text);
    font-size: 14px;
    font-weight: 950;
  }

  .farmer-detail-panel .fd-section-description {
    margin: 3px 0 0;
    color: var(--fd-muted);
    font-size: 11px;
    line-height: 1.4;
  }

  /*
  |--------------------------------------------------------------------------
  | Farmer Metrics
  |--------------------------------------------------------------------------
  */

  .farmer-detail-panel .fd-metrics {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 9px;
  }

  .farmer-detail-panel .fd-metric {
    position: relative;
    overflow: hidden;
    min-width: 0;
    padding: 12px;
    border: 1px solid var(--fd-border);
    border-radius: 16px;
    background: #ffffff;
  }

  .farmer-detail-panel .fd-metric::after {
    content: "";
    position: absolute;
    top: -28px;
    right: -28px;
    width: 66px;
    height: 66px;
    border-radius: 999px;
    background: var(--metric-soft, rgba(34, 197, 94, 0.1));
  }

  .farmer-detail-panel .fd-metric.green {
    --metric-colour: var(--fd-green);
    --metric-soft: rgba(34, 197, 94, 0.12);
  }

  .farmer-detail-panel .fd-metric.blue {
    --metric-colour: var(--fd-blue);
    --metric-soft: rgba(37, 99, 235, 0.11);
  }

  .farmer-detail-panel .fd-metric.amber {
    --metric-colour: var(--fd-amber);
    --metric-soft: rgba(245, 158, 11, 0.14);
  }

  .farmer-detail-panel .fd-metric-label {
    position: relative;
    z-index: 1;
    color: var(--fd-muted);
    font-size: 9px;
    font-weight: 900;
    letter-spacing: 0.35px;
    text-transform: uppercase;
  }

  .farmer-detail-panel .fd-metric-value {
    position: relative;
    z-index: 1;
    margin-top: 7px;
    overflow: hidden;
    color: var(--metric-colour, var(--fd-text));
    font-size: 16px;
    font-weight: 950;
    line-height: 1.1;
    text-overflow: ellipsis;
  }

  .farmer-detail-panel .fd-metric-value.is-date {
    font-size: 12px;
    line-height: 1.3;
  }

  /*
  |--------------------------------------------------------------------------
  | Primary Actions
  |--------------------------------------------------------------------------
  */

  .farmer-detail-panel .fd-actions {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 9px;
    margin-top: 11px;
  }

  .farmer-detail-panel .fd-primary-action {
    min-height: 42px;
    color: #ffffff !important;
    border-color: var(--fd-green) !important;
    background:
      linear-gradient(
        135deg,
        #22c55e,
        #15803d
      ) !important;
    box-shadow: 0 10px 20px rgba(22, 163, 74, 0.2) !important;
  }

  .farmer-detail-panel .fd-focus-action {
    min-width: 44px;
    min-height: 42px;
    padding: 0 12px !important;
  }

  .farmer-detail-panel .fd-action-icon {
    width: 16px;
    height: 16px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
  }

  /*
  |--------------------------------------------------------------------------
  | Plot Summary
  |--------------------------------------------------------------------------
  */

  .farmer-detail-panel .fd-plot-badges {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 7px;
  }

  .farmer-detail-panel .fd-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 9px;
    border: 1px solid var(--fd-border);
    border-radius: 999px;
    color: #475569;
    background: #f8fafc;
    font-size: 10px;
    font-weight: 900;
    white-space: nowrap;
  }

  .farmer-detail-panel .fd-badge.green {
    color: var(--fd-green-dark);
    border-color: rgba(34, 197, 94, 0.22);
    background: var(--fd-green-soft);
  }

  .farmer-detail-panel .fd-draft-status {
    width: 100%;
    margin-top: 8px;
    padding: 8px 10px;
    border: 1px dashed rgba(37, 99, 235, 0.32);
    border-radius: 12px;
    color: #1d4ed8;
    background: rgba(239, 246, 255, 0.76);
    font-size: 11px;
    font-weight: 850;
    line-height: 1.45;
  }

  /*
  |--------------------------------------------------------------------------
  | Selected Lot Card
  |--------------------------------------------------------------------------
  */

  .farmer-detail-panel .fd-lot-card {
    overflow: hidden;
    border: 1px solid var(--fd-border);
    border-radius: 17px;
    background:
      radial-gradient(
        circle at top right,
        rgba(124, 58, 237, 0.08),
        transparent 34%
      ),
      #ffffff;
  }

  .farmer-detail-panel .fd-lot-main {
    padding: 13px 14px;
    border-bottom: 1px solid var(--fd-border);
  }

  .farmer-detail-panel .fd-lot-label {
    margin-bottom: 5px;
    color: var(--fd-muted);
    font-size: 9px;
    font-weight: 900;
    letter-spacing: 0.35px;
    text-transform: uppercase;
  }

  .farmer-detail-panel .fd-lot-name {
    overflow: hidden;
    color: var(--fd-text);
    font-size: 14px;
    font-weight: 950;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .farmer-detail-panel .fd-lot-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .farmer-detail-panel .fd-lot-stat {
    min-width: 0;
    padding: 11px 12px;
    border-right: 1px solid var(--fd-border);
  }

  .farmer-detail-panel .fd-lot-stat:last-child {
    border-right: 0;
  }

  .farmer-detail-panel .fd-lot-stat-label {
    margin-bottom: 5px;
    color: var(--fd-muted);
    font-size: 9px;
    font-weight: 900;
    text-transform: uppercase;
  }

  .farmer-detail-panel .fd-lot-stat-value {
    overflow: hidden;
    color: var(--fd-text);
    font-size: 11px;
    font-weight: 900;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  /*
  |--------------------------------------------------------------------------
  | Plot Name Input
  |--------------------------------------------------------------------------
  */

  .farmer-detail-panel .fd-input-card {
    margin-top: 11px;
    padding: 12px;
    border: 1px solid var(--fd-border);
    border-radius: 16px;
    background: var(--fd-bg);
  }

  .farmer-detail-panel .fd-input-label {
    display: block;
    margin: 0 0 6px;
    color: var(--fd-text);
    font-size: 11px;
    font-weight: 900;
  }

  .farmer-detail-panel .fd-input {
    width: 100%;
    min-height: 42px;
    padding: 10px 12px;
    border: 1px solid var(--fd-border);
    border-radius: 12px;
    outline: none;
    color: var(--fd-text);
    background: #ffffff;
    font-size: 12px;
    font-weight: 750;
    transition:
      border-color 0.16s ease,
      box-shadow 0.16s ease;
  }

  .farmer-detail-panel .fd-input:focus {
    border-color: rgba(34, 197, 94, 0.65);
    box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.11);
  }

  .farmer-detail-panel .fd-input-help {
    margin-top: 6px;
    color: var(--fd-muted);
    font-size: 10px;
    line-height: 1.4;
  }

  /*
  |--------------------------------------------------------------------------
  | Plot List
  |--------------------------------------------------------------------------
  */

  .farmer-detail-panel .fd-plot-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 11px;
  }

  .farmer-detail-panel .fd-plot-list .map-plot-item {
    border-radius: 15px;
    padding: 11px;
  }

  .farmer-detail-panel .fd-empty {
    display: grid;
    place-items: center;
    min-height: 105px;
    padding: 18px 14px;
    border: 1px dashed #cbd5e1;
    border-radius: 16px;
    color: var(--fd-muted);
    background:
      linear-gradient(
        145deg,
        #ffffff,
        #f8fafc
      );
    text-align: center;
    font-size: 11px;
    font-weight: 750;
    line-height: 1.5;
  }

  .farmer-detail-panel .fd-empty-icon {
    width: 38px;
    height: 38px;
    display: grid;
    place-items: center;
    margin-bottom: 7px;
    border-radius: 13px;
    color: var(--fd-green-dark);
    background: var(--fd-green-soft);
  }

  .farmer-detail-panel .fd-empty-icon svg {
    width: 19px;
    height: 19px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
  }

  /*
  |--------------------------------------------------------------------------
  | Help Section
  |--------------------------------------------------------------------------
  */

  .farmer-detail-panel .fd-help {
    margin-top: 13px;
    overflow: hidden;
    border: 1px solid var(--fd-border);
    border-radius: 15px;
    background: #ffffff;
  }

  .farmer-detail-panel .fd-help summary {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 11px 12px;
    color: var(--fd-text);
    font-size: 11px;
    font-weight: 900;
    cursor: pointer;
    list-style: none;
  }

  .farmer-detail-panel .fd-help summary::-webkit-details-marker {
    display: none;
  }

  .farmer-detail-panel .fd-help summary::after {
    content: "+";
    margin-left: auto;
    color: var(--fd-muted);
    font-size: 16px;
    font-weight: 700;
  }

  .farmer-detail-panel .fd-help[open] summary::after {
    content: "−";
  }

  .farmer-detail-panel .fd-help-body {
    padding: 0 13px 13px;
    color: var(--fd-muted);
    font-size: 11px;
    line-height: 1.55;
  }

  .farmer-detail-panel .fd-help-list {
    margin: 0;
    padding-left: 18px;
  }

  .farmer-detail-panel .fd-help-list li + li {
    margin-top: 5px;
  }

  /*
  |--------------------------------------------------------------------------
  | Responsive
  |--------------------------------------------------------------------------
  */

  @media (max-width: 1240px) {
    .farmer-detail-panel .fd-metrics {
      grid-template-columns: repeat(3, minmax(100px, 1fr));
    }
  }

  @media (max-width: 700px) {
    .farmer-detail-panel {
      padding: 9px;
    }

    .farmer-detail-panel .fd-profile-header,
    .farmer-detail-panel .fd-body {
      padding: 14px;
    }

    .farmer-detail-panel .fd-profile-top {
      grid-template-columns: 46px minmax(0, 1fr) auto;
    }

    .farmer-detail-panel .fd-avatar {
      width: 46px;
      height: 46px;
      border-radius: 15px;
    }

    .farmer-detail-panel .fd-farmer-name {
      font-size: 16px;
    }

    .farmer-detail-panel .fd-metrics {
      grid-template-columns: 1fr;
    }

    .farmer-detail-panel .fd-actions {
      grid-template-columns: 1fr;
    }

    .farmer-detail-panel .fd-focus-action {
      width: 100%;
    }

    .farmer-detail-panel .fd-lot-grid {
      grid-template-columns: 1fr;
    }

    .farmer-detail-panel .fd-lot-stat {
      border-right: 0;
      border-bottom: 1px solid var(--fd-border);
    }

    .farmer-detail-panel .fd-lot-stat:last-child {
      border-bottom: 0;
    }
  }
</style>
@endpush

<aside
  class="map-panel farmer-detail-panel"
  id="mapPanel"
  aria-label="Selected farmer details"
>
  <div class="map-panel-card fd-card">

    {{-- FARMER PROFILE HEADER --}}
    <header class="fd-profile-header">
      <div class="fd-profile-top">

        <div class="fd-avatar" aria-hidden="true">
          <svg viewBox="0 0 24 24">
            <circle cx="12" cy="8" r="4"></circle>
            <path d="M4 21a8 8 0 0 1 16 0"></path>
            <path d="M3 5h4"></path>
            <path d="M5 3v4"></path>
          </svg>
        </div>

        <div class="fd-profile-copy">
          <div class="fd-eyebrow">
            <span class="fd-eyebrow-dot"></span>
            Selected Farmer
          </div>

          <h3
            class="fd-farmer-name"
            id="selName"
            aria-live="polite"
          >
            No farmer selected
          </h3>

          <div class="fd-identity-row">
            <span class="fd-identity-chip">
              FFRS:
              <strong id="selFfrs">—</strong>
            </span>
          </div>
        </div>

        <button
          type="button"
          class="btn btn-soft btn-sm fd-clear-button"
          id="clearSelectionBtn"
          title="Clear selected farmer"
          aria-label="Clear selected farmer"
        >
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M18 6 6 18"></path>
            <path d="m6 6 12 12"></path>
          </svg>
        </button>
      </div>

      <div class="fd-location-card">
        <div class="fd-location-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24">
            <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"></path>
            <circle cx="12" cy="10" r="2.5"></circle>
          </svg>
        </div>

        <div>
          <div class="fd-location-label">Farm Location</div>
          <div
            class="fd-location-value"
            id="selLocation"
            aria-live="polite"
          >
            Select a farmer marker or table row
          </div>
        </div>
      </div>
    </header>

    <div class="fd-body">

      {{-- FARMER SUMMARY --}}
      <section class="fd-section">
        <div class="fd-section-heading">
          <div class="fd-section-title-row">
            <div class="fd-section-icon">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 19V9"></path>
                <path d="M10 19V5"></path>
                <path d="M16 19v-7"></path>
                <path d="M22 19H2"></path>
              </svg>
            </div>

            <div>
              <h4 class="fd-section-title">Farmer Summary</h4>
              <p class="fd-section-description">
                Distribution activity linked to this farmer.
              </p>
            </div>
          </div>
        </div>

        <div class="fd-metrics">
          <article class="fd-metric green">
            <div class="fd-metric-label">Records</div>
            <div
              class="fd-metric-value"
              id="selRecords"
              aria-live="polite"
            >
              0
            </div>
          </article>

          <article class="fd-metric blue">
            <div class="fd-metric-label">Total Kgs</div>
            <div
              class="fd-metric-value"
              id="selKgs"
              aria-live="polite"
            >
              0.00
            </div>
          </article>

          <article class="fd-metric amber">
            <div class="fd-metric-label">Last Received</div>
            <div
              class="fd-metric-value is-date"
              id="selLast"
              aria-live="polite"
            >
              —
            </div>
          </article>
        </div>

        <div class="fd-actions">
          <a
            href="#"
            class="btn btn-sm fd-primary-action"
            id="viewRecordsBtn"
            style="text-decoration:none;"
          >
            <svg
              class="fd-action-icon"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <path d="M4 5h16v14H4z"></path>
              <path d="M8 9h8"></path>
              <path d="M8 13h5"></path>
            </svg>

            View Farmer Profile
          </a>

          <button
            type="button"
            class="btn btn-soft btn-sm fd-focus-action"
            id="focusSelectedBtn"
            title="Centre the map on this farmer"
          >
            <svg
              class="fd-action-icon"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <circle cx="12" cy="12" r="3"></circle>
              <path d="M12 2v3"></path>
              <path d="M12 19v3"></path>
              <path d="M2 12h3"></path>
              <path d="M19 12h3"></path>
            </svg>

            Focus
          </button>
        </div>
      </section>

      {{-- LAND PLOTS --}}
      <section class="fd-section">
        <div class="fd-section-heading">
          <div class="fd-section-title-row">
            <div class="fd-section-icon blue">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Z"></path>
                <path d="M9 3v15"></path>
                <path d="M15 6v15"></path>
              </svg>
            </div>

            <div>
              <h4 class="fd-section-title">Land Plots</h4>
              <p class="fd-section-description">
                Saved boundaries and total mapped area.
              </p>
            </div>
          </div>
        </div>

        <div class="fd-plot-badges" id="plotMeta">
          <span
            class="fd-badge green"
            id="plotCountPill"
          >
            0 plots
          </span>

          <span
            class="fd-badge"
            id="plotAreaTotalPill"
          >
            0.00 ha total
          </span>

          <div
            class="fd-draft-status"
            id="plotDraftInfo"
            style="display:none;"
          >
            Current draft:
            <strong id="plotDraftPoints">0</strong>
            points · approximately
            <strong id="plotDraftArea">0.00</strong>
            ha
          </div>
        </div>
      </section>

      {{-- SELECTED LOT --}}
      <section class="fd-section">
        <div class="fd-section-heading">
          <div class="fd-section-title-row">
            <div class="fd-section-icon purple">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 4h16v16H4z"></path>
                <path d="m4 14 6-6 4 4 6-6"></path>
              </svg>
            </div>

            <div>
              <h4 class="fd-section-title">Selected Lot</h4>
              <p class="fd-section-description">
                Details of the highlighted farm boundary.
              </p>
            </div>
          </div>
        </div>

        <div class="fd-lot-card">
          <div class="fd-lot-main">
            <div class="fd-lot-label">Plot Name</div>
            <div
              class="fd-lot-name"
              id="selPlotName"
              aria-live="polite"
            >
              No plot selected
            </div>
          </div>

          <div class="fd-lot-grid">
            <div class="fd-lot-stat">
              <div class="fd-lot-stat-label">Area</div>
              <div class="fd-lot-stat-value">
                <span id="selPlotArea">0.00</span> ha
              </div>
            </div>

            <div class="fd-lot-stat">
              <div class="fd-lot-stat-label">Corners</div>
              <div
                class="fd-lot-stat-value"
                id="selPlotPts"
              >
                0
              </div>
            </div>

            <div class="fd-lot-stat">
              <div class="fd-lot-stat-label">Created</div>
              <div
                class="fd-lot-stat-value"
                id="selPlotDate"
              >
                —
              </div>
            </div>
          </div>
        </div>

        <div class="fd-input-card">
          <label
            class="fd-input-label"
            for="plotNameInput"
          >
            New plot name
          </label>

          <input
            id="plotNameInput"
            class="map-input fd-input"
            type="text"
            maxlength="120"
            placeholder="Example: North Field"
            autocomplete="off"
          >

          <div class="fd-input-help">
            Give the plot a recognisable name before saving its boundary.
          </div>
        </div>

        <div
          class="map-plot-list fd-plot-list"
          id="plotList"
        >
          <div class="map-empty fd-empty">
            <div>
              <div class="fd-empty-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                  <path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Z"></path>
                  <path d="M9 3v15"></path>
                  <path d="M15 6v15"></path>
                </svg>
              </div>

              <strong>No saved plots yet</strong><br>
              Select a farmer, click <b>Plot Land</b>, and draw at least
              three corners on the map.
            </div>
          </div>
        </div>
      </section>

      {{-- HELP --}}
      <details class="map-help fd-help">
        <summary>
          Map controls and keyboard shortcuts
        </summary>

        <div class="map-help-body fd-help-body">
          <ul class="map-help-list fd-help-list">
            <li>
              <b>Fit to results</b> adjusts the map to visible farmers.
            </li>

            <li>
              <b>Plot Land</b> starts drawing a new farm boundary.
            </li>

            <li>
              Click the map to add at least three boundary corners.
            </li>

            <li>
              Press <b>Backspace</b> to remove the latest corner.
            </li>

            <li>
              Press <b>Enter</b> to save or <b>Esc</b> to cancel.
            </li>
          </ul>
        </div>
      </details>

    </div>
  </div>
</aside>