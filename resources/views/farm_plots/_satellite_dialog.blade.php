<dialog class="parcel-satellite-modal" id="parcelSatelliteModal" aria-modal="true" aria-labelledby="parcelSatelliteModalTitle">
  <div class="parcel-satellite-modal-shell">
    <header class="parcel-satellite-modal-header">
      <div>
        <span class="parcel-satellite-modal-kicker">Vegetation colours · NDVI</span>
        <h2 id="parcelSatelliteModalTitle">Satellite field check</h2>
        <p class="parcel-satellite-modal-subtitle">See how green this parcel looked from space.</p>
      </div>
      <button type="button" id="parcelSatelliteModalClose" aria-label="Close satellite view" autofocus>Close <span aria-hidden="true">×</span></button>
    </header>
    <div class="parcel-satellite-modal-body">
      <p class="parcel-satellite-modal-status" id="parcelSatelliteModalStatus" role="status" aria-live="polite">Opening satellite field check…</p>
      <div class="parcel-satellite-modal-error" id="parcelSatelliteModalError" hidden>
        <h3>Satellite view unavailable</h3>
        <p id="parcelSatelliteModalErrorText" role="alert"></p>
        <button type="button" id="parcelSatelliteModalRetry">Try again</button>
        <a href="{{ route('login') }}" id="parcelSatelliteModalLogin" hidden>Sign in again</a>
      </div>
      <iframe id="parcelSatelliteFrame" title="Satellite vegetation observations for the selected parcel" hidden></iframe>
    </div>
  </div>
</dialog>
