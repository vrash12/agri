@extends('layouts.app')

@section('title', 'Weather & Agricultural Advisories')

@push('styles')
  @include('partials.operations-ui-styles')
  <style>
    .weather-page{--wx-blue:#28689c;--wx-blue-soft:#edf6fc;--wx-sky:#dff1fb;--wx-ink:#17251d;--wx-muted:#68766d;display:flex;flex-direction:column;gap:14px;color:var(--wx-ink)}
    .weather-hero{position:relative;overflow:hidden;display:grid;grid-template-columns:minmax(0,1.25fr) minmax(300px,.75fr);gap:18px;padding:22px;border:1px solid #d9e6dc;border-radius:14px;background:linear-gradient(120deg,#fff 0%,#f4faf5 48%,#e4f3fb 100%);box-shadow:0 4px 18px rgba(23,45,31,.045)}
    .weather-hero:after{content:"";position:absolute;right:-75px;top:-90px;width:260px;height:260px;border-radius:50%;background:rgba(255,255,255,.5);box-shadow:-75px 110px 0 12px rgba(255,255,255,.22)}
    .weather-hero-copy,.weather-controls{position:relative;z-index:1}.weather-context{display:flex;align-items:center;gap:8px;color:#17643a;font-size:10px;font-weight:900;letter-spacing:.075em;text-transform:uppercase}.weather-context span{width:8px;height:8px;border-radius:50%;background:#45a966;box-shadow:0 0 0 4px rgba(69,169,102,.13)}
    .weather-hero h1{margin:8px 0 5px;font-size:clamp(28px,3vw,38px);line-height:1.05;letter-spacing:-.045em}.weather-hero-copy>p{max-width:720px;margin:0;color:var(--wx-muted);font-size:12px;line-height:1.55}
    .weather-controls{align-self:end;padding:14px;border:1px solid rgba(205,222,210,.9);border-radius:11px;background:rgba(255,255,255,.82);backdrop-filter:blur(8px)}.weather-controls label{display:block;margin-bottom:5px;color:#5b6960;font-size:9px;font-weight:900;letter-spacing:.045em;text-transform:uppercase}.weather-controls-row{display:flex;gap:8px}.weather-controls select{min-width:0;flex:1;height:39px;padding:0 10px;border:1px solid #d2ddd5;border-radius:8px;color:var(--wx-ink);background:#fff;font:inherit;font-size:11px;font-weight:750}.weather-controls button{min-height:39px}.weather-scope-note{display:block;margin-top:7px;color:var(--wx-muted);font-size:9px;line-height:1.4}
    .weather-status{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 13px;border:1px solid #dfe7e1;border-radius:9px;background:#fff;color:var(--wx-muted);font-size:9px}.weather-status-main,.weather-status-links{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.weather-live{display:inline-flex;align-items:center;gap:6px;color:#17643a;font-weight:850}.weather-live:before{content:"";width:7px;height:7px;border-radius:50%;background:#37a95d}.weather-live.stale{color:#95630f}.weather-live.stale:before{background:#d89a2d}.weather-status a{color:#17643a;font-weight:800;text-decoration:none}.weather-status a:hover{text-decoration:underline}
    .weather-alert{padding:11px 13px;border:1px solid #e7c77e;border-radius:9px;color:#704a08;background:#fff8e7;font-size:10px;line-height:1.45}.weather-alert strong{font-weight:900}.weather-alert.error{color:#8e3030;border-color:#efc4c4;background:#fff2f2}
    .weather-current-grid{display:grid;grid-template-columns:minmax(290px,1.25fr) repeat(4,minmax(140px,.75fr));gap:10px}.weather-now{grid-row:span 1;display:flex;align-items:center;gap:16px;min-height:132px;padding:17px;border:1px solid #dbe5de;border-radius:12px;color:#fff;background:linear-gradient(135deg,#17643a 0%,#277a50 58%,#2e759f 100%);box-shadow:0 6px 18px rgba(23,100,58,.12)}.weather-now-icon{width:68px;height:68px;display:grid;place-items:center;flex:0 0 auto;border-radius:20px;background:rgba(255,255,255,.13)}.weather-now-icon svg{width:42px;height:42px;fill:none;stroke:#fff;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}.weather-now-copy span{display:block;color:rgba(255,255,255,.78);font-size:10px;font-weight:850;letter-spacing:.04em;text-transform:uppercase}.weather-now-copy strong{display:block;margin:3px 0;font-size:35px;line-height:1;font-weight:900;letter-spacing:-.05em}.weather-now-copy p{margin:0;color:#fff;font-size:12px;font-weight:800}.weather-metric{display:flex;flex-direction:column;justify-content:space-between;min-width:0;padding:14px;border:1px solid #dbe5de;border-radius:11px;background:#fff}.weather-metric-top{display:flex;align-items:center;justify-content:space-between;gap:8px}.weather-metric-label{color:var(--wx-muted);font-size:9px;font-weight:900;letter-spacing:.045em;text-transform:uppercase}.weather-metric-icon{width:28px;height:28px;display:grid;place-items:center;border-radius:8px;color:var(--wx-blue);background:var(--wx-blue-soft)}.weather-metric-icon svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}.weather-metric strong{display:block;margin-top:15px;font-size:23px;line-height:1;font-weight:900;letter-spacing:-.035em}.weather-metric strong small{font-size:10px;color:var(--wx-muted);letter-spacing:0}.weather-metric>small{display:block;margin-top:5px;overflow:hidden;color:var(--wx-muted);font-size:9px;line-height:1.35;text-overflow:ellipsis;white-space:nowrap}
    .weather-content-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(300px,.65fr);gap:12px;align-items:start}.weather-panel{overflow:hidden;border:1px solid #dce5df;border-radius:12px;background:#fff;box-shadow:0 2px 10px rgba(18,40,25,.025)}.weather-panel-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:14px 16px;border-bottom:1px solid #e3e9e5}.weather-panel-kicker{display:block;margin-bottom:3px;color:#17643a;font-size:8px;font-weight:900;letter-spacing:.07em;text-transform:uppercase}.weather-panel-head h2{margin:0;font-size:16px;font-weight:900;letter-spacing:-.025em}.weather-panel-head p{margin:4px 0 0;color:var(--wx-muted);font-size:9px;line-height:1.4}.weather-period{padding:5px 8px;border-radius:7px;color:#59685e;background:#f1f5f2;font-size:8px;font-weight:850;white-space:nowrap}
    .weather-advisories{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px;padding:13px}.weather-advisory{position:relative;overflow:hidden;padding:13px 13px 13px 17px;border:1px solid #dfe7e1;border-radius:10px;background:#fbfcfb}.weather-advisory:before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:#6193b9}.weather-advisory[data-severity="high"]{border-color:#efc7c7;background:#fff8f8}.weather-advisory[data-severity="high"]:before{background:#c84646}.weather-advisory[data-severity="moderate"]{border-color:#ead8ad;background:#fffaf0}.weather-advisory[data-severity="moderate"]:before{background:#d49420}.weather-advisory[data-severity="normal"]{border-color:#cce5d3;background:#f7fcf8}.weather-advisory[data-severity="normal"]:before{background:#3b9c5d}.weather-advisory-top{display:flex;align-items:center;justify-content:space-between;gap:8px}.weather-advisory-category{color:#647168;font-size:8px;font-weight:900;letter-spacing:.05em;text-transform:uppercase}.weather-severity{padding:3px 6px;border-radius:999px;color:#2b6691;background:#e5f1f8;font-size:7px;font-weight:900;text-transform:uppercase}.weather-advisory[data-severity="high"] .weather-severity{color:#a42e2e;background:#fae2e2}.weather-advisory[data-severity="moderate"] .weather-severity{color:#94600d;background:#f7e9c9}.weather-advisory[data-severity="normal"] .weather-severity{color:#17643a;background:#e1f2e6}.weather-advisory h3{margin:8px 0 4px;font-size:12px;font-weight:900}.weather-advisory p{margin:0;color:var(--wx-muted);font-size:9px;line-height:1.5}.weather-advisory-metric{display:block;margin-top:9px;color:#34443a;font-size:8px;font-weight:850}
    .weather-official{padding:14px}.weather-official-notice{padding:12px;border:1px solid #d7e5ef;border-radius:9px;color:#315b78;background:#f3f9fd;font-size:9px;line-height:1.55}.weather-official-notice strong{display:block;margin-bottom:3px;color:#204861;font-size:10px}.weather-official-links{display:grid;gap:7px;margin-top:10px}.weather-official-link{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px;border:1px solid #e0e7e2;border-radius:8px;color:#26372c;background:#fff;text-decoration:none}.weather-official-link:hover{border-color:#9db9a7;background:#f8fbf9}.weather-official-link span{min-width:0}.weather-official-link strong{display:block;font-size:10px}.weather-official-link small{display:block;margin-top:2px;color:var(--wx-muted);font-size:8px}.weather-official-link svg{width:15px;height:15px;flex:0 0 auto;fill:none;stroke:#17643a;stroke-width:2}
    .weather-days{display:grid;grid-template-columns:repeat(7,minmax(115px,1fr));gap:8px;padding:13px;overflow-x:auto}.weather-day{min-width:115px;padding:11px;border:1px solid #e0e7e2;border-radius:9px;background:#fbfcfb}.weather-day:first-child{border-color:#a9c9b4;background:#f2f9f4}.weather-day-label{color:#59675e;font-size:9px;font-weight:900}.weather-day-condition{display:flex;align-items:center;gap:7px;margin:9px 0}.weather-day-condition span:first-child{font-size:21px}.weather-day-condition strong{font-size:9px;line-height:1.2}.weather-temp{font-size:15px;font-weight:900}.weather-temp small{color:#78837c;font-size:10px}.weather-day-meta{display:grid;gap:4px;margin-top:9px;padding-top:8px;border-top:1px solid #e5ebe7}.weather-day-meta span{display:flex;justify-content:space-between;gap:8px;color:#6c7870;font-size:8px}.weather-day-meta b{color:#35443a;font-weight:850}
    .weather-hourly{display:flex;align-items:flex-end;gap:5px;min-height:190px;padding:18px 14px 13px;overflow-x:auto}.weather-hour{display:flex;flex-direction:column;align-items:center;justify-content:flex-end;min-width:44px;height:155px}.weather-hour-prob{margin-bottom:4px;color:#28689c;font-size:7px;font-weight:900}.weather-hour-bar-track{display:flex;align-items:flex-end;width:10px;height:100px;border-radius:999px;background:#edf2ef}.weather-hour-bar{width:100%;min-height:3px;border-radius:999px;background:linear-gradient(180deg,#55a6da,#2d6ea0)}.weather-hour-label{margin-top:7px;color:#6c7870;font-size:7px;font-weight:750;white-space:nowrap}.weather-empty{display:grid;place-items:center;min-height:320px;padding:30px;text-align:center}.weather-empty-icon{width:58px;height:58px;display:grid;place-items:center;margin:0 auto 12px;border-radius:16px;color:#2f769e;background:#eaf5fb}.weather-empty-icon svg{width:30px;height:30px;fill:none;stroke:currentColor;stroke-width:1.7}.weather-empty h2{margin:0;font-size:18px}.weather-empty p{max-width:520px;margin:7px auto 16px;color:var(--wx-muted);font-size:10px;line-height:1.6}
    .weather-outreach-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:0;border-bottom:1px solid #e2e9e4;background:#fbfcfb}.weather-outreach-summary article{padding:13px 15px;border-right:1px solid #e2e9e4}.weather-outreach-summary article:last-child{border-right:0}.weather-outreach-summary span{display:block;color:var(--wx-muted);font-size:8px;font-weight:900;letter-spacing:.045em;text-transform:uppercase}.weather-outreach-summary strong{display:block;margin-top:5px;font-size:18px;font-weight:900}.weather-outreach-summary small{display:block;margin-top:3px;color:var(--wx-muted);font-size:8px;line-height:1.35}.weather-outreach-table{width:100%;min-width:760px;border-collapse:collapse}.weather-outreach-table th{padding:9px 12px;color:#647168;background:#f6f9f7;font-size:8px;font-weight:900;letter-spacing:.045em;text-align:left;text-transform:uppercase}.weather-outreach-table td{padding:11px 12px;border-top:1px solid #e7ece8;color:#46544b;font-size:9px}.weather-outreach-table td strong{display:block;color:var(--wx-ink);font-size:10px}.weather-outreach-table td small{display:block;margin-top:2px;color:var(--wx-muted);font-size:8px}.weather-contact-ready{display:inline-flex;padding:4px 7px;border-radius:999px;color:#17643a;background:#e3f2e7;font-size:8px;font-weight:850}.weather-contact-missing{color:#9a650e;background:#faf0d8}.weather-table-wrap{overflow-x:auto}.weather-outreach-note{padding:10px 14px;border-top:1px solid #e7ece8;color:var(--wx-muted);background:#fbfcfb;font-size:8px;line-height:1.45}
    @media(max-width:1250px){.weather-current-grid{grid-template-columns:minmax(280px,1.2fr) repeat(2,minmax(150px,.8fr))}.weather-content-grid{grid-template-columns:1fr}.weather-official-links{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:820px){.weather-hero{grid-template-columns:1fr}.weather-current-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.weather-now{grid-column:1/-1}.weather-advisories{grid-template-columns:1fr}.weather-status{align-items:flex-start;flex-direction:column}.weather-outreach-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.weather-outreach-summary article:nth-child(2){border-right:0}}
    @media(max-width:560px){.weather-hero{padding:17px}.weather-controls-row{flex-direction:column}.weather-controls-row .module-button{width:100%}.weather-current-grid{grid-template-columns:1fr}.weather-now{grid-column:1}.weather-official-links{grid-template-columns:1fr}.weather-panel-head{align-items:flex-start;flex-direction:column}.weather-days{grid-template-columns:repeat(7,118px)}}
  </style>
@endpush

@php
  $current = $forecast['current'] ?? [];
  $daily = collect($forecast['daily'] ?? []);
  $hourly = collect($forecast['hourly'] ?? []);
  $summary = $forecast['summary'] ?? [];
  $advisories = collect($forecast['advisories'] ?? []);
  $affectedFarmers = collect($affectedFarmers ?? []);
  $outreachSummary = $outreachSummary ?? [];
  $isProvincialUser = auth()->user()->canAccessAllMunicipalities();
  $fetchedAt = null;

  if (!empty($forecast['fetched_at'])) {
      try {
          $fetchedAt = \Illuminate\Support\Carbon::parse($forecast['fetched_at'])->timezone(config('weather.timezone'));
      } catch (\Throwable $e) {
          $fetchedAt = null;
      }
  }
@endphp

@section('content')
<div class="weather-page">
  <header class="weather-hero">
    <div class="weather-hero-copy">
      <div class="weather-context"><span aria-hidden="true"></span>Agricultural decision support</div>
      <h1>Weather & agricultural advisories</h1>
      <p>Municipality-level forecast guidance for field scheduling, water management, crop protection, and equipment readiness.</p>
    </div>

    <div class="weather-controls">
      @if($isProvincialUser)
        <form method="GET" action="{{ route('weather.index') }}">
          <label for="weatherMunicipality">Forecast municipality</label>
          <div class="weather-controls-row">
            <select id="weatherMunicipality" name="municipality_id" required>
              @foreach($municipalities as $municipality)
                <option value="{{ $municipality->id }}" @selected($selectedMunicipality->id === $municipality->id)>{{ $municipality->name }}</option>
              @endforeach
            </select>
            <button class="module-button module-button-primary" type="submit">View forecast</button>
          </div>
          <span class="weather-scope-note">Provincial accounts may compare active municipalities one at a time.</span>
        </form>
      @else
        <label>Forecast municipality</label>
        <div class="weather-controls-row">
          <select aria-label="Assigned municipality" disabled>
            <option>{{ $selectedMunicipality->name }}</option>
          </select>
          <form method="POST" action="{{ route('weather.refresh') }}">
            @csrf
            <input type="hidden" name="municipality_id" value="{{ $selectedMunicipality->id }}">
            <button class="module-button" type="submit">Refresh</button>
          </form>
        </div>
        <span class="weather-scope-note">The forecast is locked to your assigned municipal office.</span>
      @endif
    </div>
  </header>

  @if(session('success'))
    <div class="module-alert">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="module-alert module-alert-error">{{ session('error') }}</div>
  @endif
  @if($errors->any())
    <div class="module-alert module-alert-error">{{ $errors->first() }}</div>
  @endif

  <div class="weather-status">
    <div class="weather-status-main">
      <span class="weather-live {{ !empty($forecast['is_stale']) ? 'stale' : '' }}">
        {{ !empty($forecast['is_stale']) ? 'Cached forecast' : 'Forecast service active' }}
      </span>
      <span>{{ $selectedMunicipality->name }}, Tarlac</span>
      @if($fetchedAt)<span>Updated {{ $fetchedAt->format('M j, Y · g:i A') }}</span>@endif
      @if(!empty($forecast['coordinates']))
        <span>{{ number_format($forecast['coordinates']['latitude'], 4) }}, {{ number_format($forecast['coordinates']['longitude'], 4) }}</span>
      @endif
    </div>
    <div class="weather-status-links">
      <span>Forecast: <a href="{{ $forecast['provider_url'] ?? 'https://open-meteo.com/' }}" target="_blank" rel="noopener">{{ $forecast['provider'] ?? 'Open-Meteo' }}</a></span>
      @if($isProvincialUser)
        <form method="POST" action="{{ route('weather.refresh') }}">
          @csrf
          <input type="hidden" name="municipality_id" value="{{ $selectedMunicipality->id }}">
          <button class="module-button module-button-small" type="submit">Refresh data</button>
        </form>
      @endif
    </div>
  </div>

  @if(!empty($forecast['status_message']))
    <div class="weather-alert {{ empty($forecast['available']) ? 'error' : '' }}">
      <strong>{{ !empty($forecast['is_stale']) ? 'Cached data notice:' : 'Forecast notice:' }}</strong>
      {{ $forecast['status_message'] }}
    </div>
  @endif

  @if(!empty($forecast['available']))
    <section class="weather-current-grid" aria-label="Current weather and forecast summary">
      <article class="weather-now">
        <div class="weather-now-icon" aria-hidden="true">
          @if(($current['condition_icon'] ?? '') === 'rain')
            <svg viewBox="0 0 48 48"><path d="M15 30h20a9 9 0 0 0 0-18 13 13 0 0 0-24 5A7 7 0 0 0 15 30Z"></path><path d="m17 35-2 5m10-5-2 5m10-5-2 5"></path></svg>
          @elseif(($current['condition_icon'] ?? '') === 'storm')
            <svg viewBox="0 0 48 48"><path d="M15 28h20a9 9 0 0 0 0-18 13 13 0 0 0-24 5A7 7 0 0 0 15 28Z"></path><path d="m26 28-6 10h6l-3 8 10-13h-7Z"></path></svg>
          @elseif(($current['condition_icon'] ?? '') === 'sun')
            <svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="8"></circle><path d="M24 4v6m0 28v6M4 24h6m28 0h6M10 10l5 5m18 18 5 5m0-28-5 5M15 33l-5 5"></path></svg>
          @else
            <svg viewBox="0 0 48 48"><path d="M15 32h20a9 9 0 0 0 0-18 13 13 0 0 0-24 5A7 7 0 0 0 15 32Z"></path></svg>
          @endif
        </div>
        <div class="weather-now-copy">
          <span>Current conditions</span>
          <strong>{{ is_null($current['temperature'] ?? null) ? '—' : number_format($current['temperature'], 1) . '°' }}</strong>
          <p>{{ $current['condition'] ?? 'Conditions unavailable' }}</p>
        </div>
      </article>

      <article class="weather-metric">
        <div class="weather-metric-top"><span class="weather-metric-label">Feels like</span><span class="weather-metric-icon"><svg viewBox="0 0 24 24"><path d="M14 14.8V5a4 4 0 0 0-8 0v9.8a6 6 0 1 0 8 0Z"></path><path d="M10 6v10"></path></svg></span></div>
        <strong>{{ is_null($current['apparent_temperature'] ?? null) ? '—' : number_format($current['apparent_temperature'], 1) }}<small> °C</small></strong>
        <small>Heat exposure reference</small>
      </article>
      <article class="weather-metric">
        <div class="weather-metric-top"><span class="weather-metric-label">Humidity</span><span class="weather-metric-icon"><svg viewBox="0 0 24 24"><path d="M12 2S5 10 5 15a7 7 0 0 0 14 0c0-5-7-13-7-13Z"></path></svg></span></div>
        <strong>{{ is_null($current['humidity'] ?? null) ? '—' : number_format($current['humidity']) }}<small> %</small></strong>
        <small>Current relative humidity</small>
      </article>
      <article class="weather-metric">
        <div class="weather-metric-top"><span class="weather-metric-label">7-day rainfall</span><span class="weather-metric-icon"><svg viewBox="0 0 24 24"><path d="M7 15h10a5 5 0 0 0 0-10 7 7 0 0 0-13 3 4 4 0 0 0 3 7Z"></path><path d="m8 18-1 3m5-3-1 3m5-3-1 3"></path></svg></span></div>
        <strong>{{ number_format((float) ($summary['seven_day_rain'] ?? 0), 1) }}<small> mm</small></strong>
        <small>Forecast precipitation total</small>
      </article>
      <article class="weather-metric">
        <div class="weather-metric-top"><span class="weather-metric-label">Peak wind gust</span><span class="weather-metric-icon"><svg viewBox="0 0 24 24"><path d="M3 8h11a3 3 0 1 0-3-3M3 12h16a3 3 0 1 1-3 3M3 16h8"></path></svg></span></div>
        <strong>{{ number_format((float) ($summary['maximum_wind_gust'] ?? 0), 0) }}<small> km/h</small></strong>
        <small>Highest forecast gust</small>
      </article>
    </section>

    <div class="weather-content-grid">
      <section class="weather-panel">
        <div class="weather-panel-head">
          <div><span class="weather-panel-kicker">Decision support</span><h2>Agricultural advisories</h2><p>Rule-based guidance from the forecast; review field conditions before acting.</p></div>
          <span class="weather-period">Next 3–7 days</span>
        </div>
        <div class="weather-advisories">
          @foreach($advisories as $advisory)
            <article class="weather-advisory" data-severity="{{ $advisory['severity'] }}">
              <div class="weather-advisory-top"><span class="weather-advisory-category">{{ $advisory['category'] }}</span><span class="weather-severity">{{ $advisory['severity'] }}</span></div>
              <h3>{{ $advisory['title'] }}</h3>
              <p>{{ $advisory['message'] }}</p>
              <span class="weather-advisory-metric">{{ $advisory['metric'] }}</span>
            </article>
          @endforeach
        </div>
      </section>

      <aside class="weather-panel">
        <div class="weather-panel-head"><div><span class="weather-panel-kicker">Official authority</span><h2>PAGASA bulletins</h2><p>Verify safety-critical decisions against official Philippine warnings.</p></div></div>
        <div class="weather-official">
          <div class="weather-official-notice"><strong>Forecast guidance is not an official warning.</strong>Typhoon, rainfall, thunderstorm, and flood alerts must come from PAGASA and local disaster-risk authorities.</div>
          <div class="weather-official-links">
            <a class="weather-official-link" href="{{ $pagasaLinks['weather'] ?? 'https://www.pagasa.dost.gov.ph/weather' }}" target="_blank" rel="noopener"><span><strong>Weather bulletins</strong><small>Forecasts and rainfall information</small></span><svg viewBox="0 0 24 24"><path d="M14 3h7v7M10 14 21 3M21 14v7H3V3h7"></path></svg></a>
            <a class="weather-official-link" href="{{ $pagasaLinks['tropical_cyclone'] ?? '#' }}" target="_blank" rel="noopener"><span><strong>Tropical cyclone</strong><small>Official severe-weather bulletins</small></span><svg viewBox="0 0 24 24"><path d="M14 3h7v7M10 14 21 3M21 14v7H3V3h7"></path></svg></a>
            <a class="weather-official-link" href="{{ $pagasaLinks['flood'] ?? '#' }}" target="_blank" rel="noopener"><span><strong>Flood information</strong><small>Flood advisories and monitoring</small></span><svg viewBox="0 0 24 24"><path d="M14 3h7v7M10 14 21 3M21 14v7H3V3h7"></path></svg></a>
            <a class="weather-official-link" href="{{ $pagasaLinks['agri_weather'] ?? '#' }}" target="_blank" rel="noopener"><span><strong>Agri-weather outlook</strong><small>Farm activity and crop guidance</small></span><svg viewBox="0 0 24 24"><path d="M14 3h7v7M10 14 21 3M21 14v7H3V3h7"></path></svg></a>
          </div>
        </div>
      </aside>
    </div>

    <section class="weather-panel">
      <div class="weather-panel-head"><div><span class="weather-panel-kicker">Planning horizon</span><h2>Seven-day forecast</h2><p>Rainfall, temperature, wind, and crop-water demand indicators.</p></div><span class="weather-period">Asia/Manila</span></div>
      <div class="weather-days">
        @foreach($daily as $day)
          <article class="weather-day">
            <div class="weather-day-label">{{ $loop->first ? 'Today · ' : '' }}{{ $day['day_label'] }}</div>
            <div class="weather-day-condition"><span aria-hidden="true">{{ $day['condition_icon'] === 'sun' ? '☀' : ($day['condition_icon'] === 'storm' ? '⛈' : ($day['condition_icon'] === 'rain' ? '🌧' : '☁')) }}</span><strong>{{ $day['condition'] }}</strong></div>
            <div class="weather-temp">{{ is_null($day['temperature_max']) ? '—' : number_format($day['temperature_max'], 0) }}° <small>/ {{ is_null($day['temperature_min']) ? '—' : number_format($day['temperature_min'], 0) }}°</small></div>
            <div class="weather-day-meta">
              <span>Rain <b>{{ number_format((float) ($day['precipitation_sum'] ?? 0), 1) }} mm</b></span>
              <span>Chance <b>{{ number_format((int) ($day['precipitation_probability'] ?? 0)) }}%</b></span>
              <span>Gust <b>{{ number_format((float) ($day['wind_gust_max'] ?? 0), 0) }} km/h</b></span>
              <span>ET₀ <b>{{ number_format((float) ($day['et0'] ?? 0), 1) }} mm</b></span>
            </div>
          </article>
        @endforeach
      </div>
    </section>

    <section class="weather-panel">
      <div class="weather-panel-head"><div><span class="weather-panel-kicker">Fieldwork window</span><h2>Rain chance — next 24 hours</h2><p>Use this timing view before spraying, fertilizer application, crop drying, or machinery dispatch.</p></div><span class="weather-period">Hourly</span></div>
      @if($hourly->isNotEmpty())
        <div class="weather-hourly" role="img" aria-label="Hourly probability of precipitation chart">
          @foreach($hourly as $hour)
            @php $probability = max(0, min(100, (int) ($hour['precipitation_probability'] ?? 0))); @endphp
            <div class="weather-hour" title="{{ $hour['label'] }} · {{ $probability }}% rain · {{ number_format((float) ($hour['temperature'] ?? 0), 1) }}°C">
              <span class="weather-hour-prob">{{ $probability }}%</span>
              <span class="weather-hour-bar-track"><span class="weather-hour-bar" style="height:{{ max(3, $probability) }}%"></span></span>
              <span class="weather-hour-label">{{ $hour['label'] }}</span>
            </div>
          @endforeach
        </div>
      @else
        <div class="module-empty"><strong>Hourly data is unavailable</strong><span>The seven-day forecast can still be used for general planning.</span></div>
      @endif
    </section>

    @if(!empty($outreachSummary['active']))
      <section class="weather-panel">
        <div class="weather-panel-head">
          <div><span class="weather-panel-kicker">Preparedness workflow</span><h2>Mapped-farmer outreach queue</h2><p>Farmers with saved parcel boundaries in {{ $selectedMunicipality->name }} who may need forecast follow-up.</p></div>
          <span class="weather-period">{{ implode(' · ', $outreachSummary['risk_categories'] ?? []) }}</span>
        </div>
        <div class="weather-outreach-summary">
          <article><span>Mapped farmers</span><strong>{{ number_format((int) ($outreachSummary['total_mapped_farmers'] ?? 0)) }}</strong><small>Potential follow-up scope</small></article>
          <article><span>Contact ready</span><strong>{{ number_format((int) ($outreachSummary['contactable_farmers'] ?? 0)) }}</strong><small>Contact number recorded</small></article>
          <article><span>Missing contact</span><strong>{{ number_format((int) ($outreachSummary['missing_contact'] ?? 0)) }}</strong><small>Registry update needed</small></article>
          <article><span>Queue shown</span><strong>{{ number_format($affectedFarmers->count()) }}</strong><small>First 12 mapped farmers</small></article>
        </div>
        @if($affectedFarmers->isNotEmpty())
          <div class="weather-table-wrap">
            <table class="weather-outreach-table">
              <thead><tr><th>Farmer</th><th>Farm location</th><th>Mapped parcels</th><th>Contact readiness</th><th>Follow up</th></tr></thead>
              <tbody>
                @foreach($affectedFarmers as $farmer)
                  @php
                    $farmerName = trim(collect([$farmer->first_name, $farmer->middle_name, $farmer->last_name, $farmer->ext_name])->filter()->implode(' '));
                  @endphp
                  <tr>
                    <td><strong>{{ $farmerName ?: 'Unnamed farmer' }}</strong><small>{{ $farmer->ffrs ?: $farmer->registry_id }}</small></td>
                    <td><strong>{{ $farmer->farm_location ?: 'Location not recorded' }}</strong><small>{{ $selectedMunicipality->name }}, Tarlac</small></td>
                    <td><strong>{{ number_format((int) $farmer->farm_plots_count) }} {{ Str::plural('parcel', (int) $farmer->farm_plots_count) }}</strong><small>{{ number_format((float) ($farmer->mapped_area_ha ?? 0), 2) }} ha mapped</small></td>
                    <td><span class="weather-contact-ready {{ $farmer->contact_number ? '' : 'weather-contact-missing' }}">{{ $farmer->contact_number ?: 'Contact missing' }}</span></td>
                    <td><a class="module-button module-button-small" href="{{ route('farmers.records', $farmer) }}">Open record</a></td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @else
          <div class="module-empty"><strong>No mapped farmers in the outreach queue</strong><span>Add parcel boundaries to identify farms for location-based preparedness follow-up.</span></div>
        @endif
        <div class="weather-outreach-note">This queue does not automatically send SMS or emergency notices. Staff must confirm PAGASA or local DRRM guidance, verify actual affected locations, and use approved communication channels.</div>
      </section>
    @endif
  @else
    <section class="weather-panel weather-empty">
      <div>
        <div class="weather-empty-icon"><svg viewBox="0 0 24 24"><path d="M7 16h10a5 5 0 0 0 0-10 7 7 0 0 0-13 3 4 4 0 0 0 3 7Z"></path><path d="M12 19v3M8 20l-1 2m9-2 1 2"></path></svg></div>
        <h2>Forecast is temporarily unavailable</h2>
        <p>{{ $forecast['status_message'] ?? 'Try again later and use PAGASA for official weather information.' }}</p>
        <form method="POST" action="{{ route('weather.refresh') }}">
          @csrf
          <input type="hidden" name="municipality_id" value="{{ $selectedMunicipality->id }}">
          <button class="module-button module-button-primary" type="submit">Try again</button>
        </form>
      </div>
    </section>
  @endif
</div>
@endsection
