<section class="fps" id="farmerParcelStats" data-parcel-stats aria-labelledby="farmerParcelStatsTitle">
  <header class="fps-header">
    <div>
      <p class="fps-eyebrow">Selected farmer</p>
      <h4 class="fps-title" id="farmerParcelStatsTitle">Land &amp; crops</h4>
    </div>
    <span class="fps-period" data-parcel-stats-period></span>
  </header>

  <p class="fps-status" data-parcel-stats-status role="status" aria-live="polite" aria-atomic="true">
    Choose a farmer to see their saved parcels and crops.
  </p>
  <button class="fps-retry" type="button" data-parcel-stats-retry hidden>Try again</button>

  <div data-parcel-stats-content hidden>
    <div class="fps-metrics" data-parcel-stats-metrics></div>
    <p class="fps-note fps-area-note" data-parcel-stats-area-note></p>

    <section class="fps-section" aria-labelledby="farmerParcelCropCoverageTitle">
      <h5 class="fps-section-title" id="farmerParcelCropCoverageTitle">Crop records this season</h5>
      <div class="fps-coverage" data-parcel-stats-coverage></div>
    </section>

    <section class="fps-section" aria-labelledby="farmerParcelAreaChartTitle">
      <div class="fps-section-heading">
        <h5 class="fps-section-title" id="farmerParcelAreaChartTitle">Land by parcel</h5>
        <span class="fps-unit">hectares</span>
      </div>
      <div class="fps-bars" data-parcel-stats-land-chart></div>
      <div data-parcel-stats-land-all></div>
    </section>

    <section class="fps-section" aria-labelledby="farmerParcelCropChartTitle">
      <div class="fps-section-heading">
        <h5 class="fps-section-title" id="farmerParcelCropChartTitle">Crops on these parcels</h5>
        <span class="fps-unit">parcel count</span>
      </div>
      <div class="fps-bars" data-parcel-stats-crop-chart></div>
      <p class="fps-note" data-parcel-stats-crop-note></p>
    </section>
  </div>
</section>
