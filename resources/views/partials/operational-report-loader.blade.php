<script>
(() => {
  const report = document.getElementById(@json($reportId));
  if (!report) return;
  const status = report.querySelector('[data-report-status]');
  let started = false;
  const fallback = message => {
    if (status) status.textContent = message;
    report.querySelectorAll('.module-chart-body').forEach(body => { body.hidden = true; });
  };
  const load = () => {
    if (!report.open || started) return;
    started = true;
    if (status) status.textContent = 'Loading charts. All figures are available below.';
    const ready = () => {
      try {
        report.querySelectorAll('.module-chart-body').forEach(body => { body.hidden = false; });
        report.renderOperationalCharts();
        if (status) status.textContent = 'Charts and figures reflect the current filters.';
      } catch (error) {
        fallback('Charts are unavailable. Use the figures below.');
      }
    };
    if (typeof Chart !== 'undefined') { ready(); return; }
    // Pinned and hashed in config/cdn.php. Without the integrity attribute the
    // browser would run whatever the CDN returned for this URL.
    const chartAsset = @json(\App\Support\Cdn::asset('chart_js'));
    const script = document.createElement('script');
    script.src = chartAsset.url;
    script.integrity = chartAsset.integrity;
    script.crossOrigin = 'anonymous';
    script.referrerPolicy = 'no-referrer';
    script.async = true;
    const timer = window.setTimeout(() => {
      fallback('Charts are taking longer to load. Use the figures below.');
    }, 10000);
    script.onload = () => { window.clearTimeout(timer); ready(); };
    script.onerror = () => {
      window.clearTimeout(timer);
      fallback('Charts could not load. Use the figures below.');
    };
    document.head.appendChild(script);
  };
  report.addEventListener('toggle', load);
  load();
})();
</script>
