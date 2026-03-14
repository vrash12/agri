{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'LGU Agriculture System')</title>

  <!-- Roboto -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap" rel="stylesheet">

  <!-- DataTables -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">

  <!-- Tom Select -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">

  <style>
    :root{
      --bg1:#f6fff4;
      --bg2:#fffbe6;
      --card:#ffffff;
      --text:#0f172a;
      --muted:#64748b;
      --border:#e5e7eb;

      --green:#166534;
      --green2:#22c55e;
      --yellow:#facc15;
      --yellow2:#fde68a;

      --shadow: 0 14px 40px rgba(2,6,23,.10);
      --radius: 18px;

      --dangerBg:#fef2f2;
      --dangerText:#991b1b;
      --dangerBorder:#fecaca;

      --successBg:#ecfdf5;
      --successText:#065f46;
      --successBorder:#a7f3d0;

      --focus: rgba(34,197,94,.20);

      --sidebarW: 280px;
      --sidebarCollapsedW: 0px; /* ✅ when collapsed, sidebar becomes visually gone */
      --navBtnSize: 44px;       /* button size */
    }

    *{ box-sizing: border-box; font-family: 'Roboto', system-ui, -apple-system, Segoe UI, Arial, sans-serif; }
    body{
      margin:0;
      color: var(--text);
      background:
        radial-gradient(900px 450px at 15% 10%, var(--yellow2) 0%, transparent 55%),
        radial-gradient(900px 450px at 80% 20%, rgba(34,197,94,.18) 0%, transparent 55%),
        linear-gradient(135deg, var(--bg1), var(--bg2));
      min-height: 100vh;
    }

    /* =========================================================
       APP SHELL
       ========================================================= */
    .app-shell{
      display:flex;
      min-height: 100vh;
    }

    /* =========================================================
       SIDEBAR (normal)
       ========================================================= */
    .sidebar{
      width: var(--sidebarW);
      background: #fff;
      border-right: 1px solid var(--border);
      position: fixed;
      top: 0;
      left: 0;
      bottom: 0;
      z-index: 70;
      overflow: auto;
      transition: width .18s ease, transform .18s ease, background .18s ease, border-color .18s ease;
    }
    .sidebar::before{
      content:"";
      display:block;
      height: 6px;
      background: linear-gradient(90deg, var(--green2), var(--yellow));
    }
    .sidebar-inner{ padding: 14px 14px 18px; }

    /* =========================================================
       ✅ COLLAPSED = NAVBAR REMOVED (transparent) + ONLY BUTTON REMAINS
       - Sidebar becomes "transparent overlay" and does not block content.
       - Only the floating button is visible on the left.
       ========================================================= */
    body.nav-collapsed .sidebar{
      width: var(--sidebarCollapsedW);
      background: transparent;
      border-right-color: transparent;
      overflow: visible; /* allow button to show */
    }
    body.nav-collapsed .sidebar::before{ display:none; }
    body.nav-collapsed .sidebar-inner{
      padding: 0;
      width: 0;
    }
    body.nav-collapsed .hide-when-collapsed{ display:none !important; }

    /* =========================================================
       SIDEBAR TOP AREA (brand + button placement)
       ========================================================= */
    .sidebar-topbar{
      position: relative;
      margin-bottom: 12px;
    }

    .brand{
      display:flex;
      align-items:center;
      gap: 10px;
      padding: 10px 10px;
      padding-right: 54px; /* space for button overlay */
      border: 1px solid var(--border);
      border-radius: 16px;
      background: #fff;
      box-shadow: 0 14px 40px rgba(2,6,23,.06);
      overflow: hidden;
    }
    .brand img{
      width: 34px;
      height: 34px;
      object-fit: contain;
      background:#fff;
      border: 1px solid rgba(0,0,0,.08);
      border-radius: 12px;
      padding: 3px;
      flex: 0 0 auto;
    }
    .brand-text{ min-width:0; }
    .brand-title{ font-weight: 900; font-size: 14px; line-height: 1.1; }
    .brand-sub{ font-size: 12px; color: var(--muted); }

    /* =========================================================
       ✅ BUTTON (normal: top-right inside sidebar; collapsed: floating at left)
       ========================================================= */
    .nav-hide-btn{
      position:absolute;
      top: 10px;
      right: 10px;
      width: var(--navBtnSize);
      height: var(--navBtnSize);
      border-radius: 14px;
      border: 1px solid rgba(2,6,23,.10);
      background: #fff;
      display:flex;
      align-items:center;
      justify-content:center;
      cursor:pointer;
      color:#0b1220;
      box-shadow: 0 12px 28px rgba(2,6,23,.12);
      transition: transform .10s ease, background .12s ease, border-color .12s ease, box-shadow .12s ease;
      z-index: 200; /* above maps */
    }
    .nav-hide-btn:hover{
      background: #f8fafc;
      transform: translateY(-1px);
    }
    .nav-hide-btn:focus{
      outline: none;
      border-color: rgba(34,197,94,.65);
      box-shadow: 0 0 0 4px var(--focus), 0 12px 28px rgba(2,6,23,.12);
    }
    .nav-hide-btn svg{
      width: 18px;
      height: 18px;
      stroke: currentColor;
      fill: none;
      stroke-width: 2;
      stroke-linecap: round;
      stroke-linejoin: round;
    }

    /* collapsed: make it FLOATING on the far-left (like your screenshot request) */
    body.nav-collapsed .nav-hide-btn{
      position: fixed;
      top: 14px;
      left: 10px;
      right: auto;
      background: rgba(255,255,255,.65);
      backdrop-filter: blur(8px);
      border-color: rgba(2,6,23,.10);
      box-shadow: 0 16px 40px rgba(2,6,23,.18);
    }
    body.nav-collapsed .nav-hide-btn:hover{
      background: rgba(255,255,255,.85);
    }

    /* =========================================================
       NAV LINKS
       ========================================================= */
    .nav-section{ margin-top: 12px; }
    .nav-title{
      font-size: 12px;
      font-weight: 900;
      color: var(--muted);
      padding: 8px 10px;
      text-transform: uppercase;
      letter-spacing: .3px;
    }
    .navlinks{
      display:flex;
      flex-direction: column;
      gap: 8px;
      padding: 0 2px;
    }

    .link{
      text-decoration:none;
      color:#0b1220;
      font-weight: 900;
      font-size: 13px;
      padding: 10px 12px;
      border-radius: 14px;
      border: 1px solid var(--border);
      background: #fff;
      display:flex;
      align-items:center;
      justify-content:flex-start;
      white-space: nowrap;
      gap: 10px;
      position: relative;
      transition: transform .10s ease, background .12s ease, border-color .12s ease, box-shadow .12s ease;
      overflow: hidden;
    }
    .link:hover{
      background:#f8fafc;
      transform: translateY(-1px);
      box-shadow: 0 10px 24px rgba(2,6,23,.08);
    }

    .nav-ico{
      width: 34px;
      height: 34px;
      border-radius: 12px;
      border: 1px solid rgba(2,6,23,.08);
      background: rgba(2,6,23,.03);
      display:flex;
      align-items:center;
      justify-content:center;
      flex: 0 0 auto;
      transition: transform .18s ease, background .18s ease, border-color .18s ease;
      color:#0b1220;
    }
    .nav-ico svg{
      width: 18px;
      height: 18px;
      stroke: currentColor;
      fill: none;
      stroke-width: 2;
      stroke-linecap: round;
      stroke-linejoin: round;
      opacity: .95;
    }

    .link .nav-text{
      flex: 1;
      min-width: 0;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .link::before{
      content:"";
      position:absolute;
      left: 0;
      top: 8px;
      bottom: 8px;
      width: 4px;
      border-radius: 999px;
      background: transparent;
      transition: background .18s ease, transform .18s ease;
      transform: scaleY(.35);
      transform-origin: center;
    }

    .link.is-active{
      border-color: rgba(34,197,94,.28);
      background: rgba(34,197,94,.12);
      color: var(--green);
      box-shadow: 0 14px 38px rgba(2,6,23,.10);
    }
    .link.is-active::before{
      background: linear-gradient(180deg, var(--green2), var(--yellow));
      transform: scaleY(1);
    }
    .link.is-active .nav-ico{
      background: rgba(34,197,94,.12);
      border-color: rgba(34,197,94,.28);
      transform: scale(1.04);
      animation: navPulse 1.6s ease-in-out infinite;
      color: var(--green);
    }
    @keyframes navPulse{
      0%, 100% { box-shadow: 0 0 0 0 rgba(34,197,94,.00); }
      50%      { box-shadow: 0 0 0 8px rgba(34,197,94,.10); }
    }

    @media (prefers-reduced-motion: reduce){
      .link, .nav-ico{ transition:none; }
      .link.is-active .nav-ico{ animation:none; }
      .link:hover{ transform:none; }
    }

    .userpill{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap: 10px;
      padding: 10px 12px;
      border-radius: 16px;
      border: 1px solid var(--border);
      background:#fff;
      font-size: 12px;
      font-weight: 900;
      color:#0b1220;
      margin-top: 12px;
      overflow:hidden;
    }

    .rolebadge{
      display:inline-block;
      padding: 3px 8px;
      border-radius: 999px;
      border: 1px solid rgba(34,197,94,.25);
      background: rgba(34,197,94,.10);
      color:#166534;
      font-size: 11px;
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: .3px;
      white-space: nowrap;
    }
    .rolebadge.head{
      border-color: rgba(250,204,21,.35);
      background: rgba(250,204,21,.18);
      color:#854d0e;
    }

    .logout-btn{
      width: 100%;
      margin-top: 10px;
      border: 1px solid rgba(239,68,68,.25);
      background: #fff;
      color: #b91c1c;
      font-weight: 900;
      padding: 10px 12px;
      border-radius: 14px;
      cursor:pointer;
      white-space: nowrap;
    }
    .logout-btn:hover{ background:#fef2f2; }

    /* =========================================================
       MAIN
       ========================================================= */
    .main{
      flex: 1;
      margin-left: var(--sidebarW);
      min-width: 0;
      transition: margin-left .18s ease;
    }
    body.nav-collapsed .main{
      margin-left: 0; /* ✅ content uses full width when navbar removed */
    }

    .container{
      max-width: 1520px;
      margin: 0 auto;
      padding: 18px 16px 40px;
    }

    /* MOBILE TOP BAR + DRAWER */
    .mobilebar{
      display:none;
      position: sticky;
      top: 0;
      z-index: 60;
      background: rgba(255,255,255,.85);
      backdrop-filter: blur(8px);
      border-bottom: 1px solid var(--border);
    }
    .mobilebar::before{
      content:"";
      display:block;
      height: 6px;
      background: linear-gradient(90deg, var(--green2), var(--yellow));
    }
    .mobilebar-inner{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap: 10px;
      padding: 10px 12px;
      max-width: 1520px;
      margin: 0 auto;
    }
    .menu-btn{
      display:inline-flex;
      align-items:center;
      gap:8px;
      border: 1px solid var(--border);
      background:#fff;
      border-radius: 14px;
      padding: 10px 12px;
      cursor:pointer;
      font-weight: 900;
      font-size: 13px;
      color:#0b1220;
    }
    .menu-btn:focus{
      outline: none;
      border-color: rgba(34,197,94,.65);
      box-shadow: 0 0 0 4px var(--focus);
    }
    .mobilebar-title{
      font-weight: 900;
      font-size: 13px;
      color:#0b1220;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .overlay{
      display:none;
      position: fixed;
      inset: 0;
      background: rgba(2,6,23,.45);
      z-index: 69;
    }

    /* Responsive behavior */
    @media (max-width: 900px){
      /* on mobile: ignore nav-collapsed, use drawer only */
      body.nav-collapsed .sidebar{
        width: var(--sidebarW);
        background:#fff;
        border-right-color: var(--border);
        overflow:auto;
      }
      body.nav-collapsed .sidebar::before{ display:block; }
      body.nav-collapsed .sidebar-inner{ padding: 14px 14px 18px; width:auto; }
      body.nav-collapsed .hide-when-collapsed{ display:block !important; }
      body.nav-collapsed .nav-hide-btn{
        position:absolute;
        top:10px;
        right:10px;
        left:auto;
        background:#fff;
        backdrop-filter:none;
      }

      .sidebar{
        transform: translateX(-102%);
        transition: transform .18s ease;
        width: var(--sidebarW);
      }
      body.sidebar-open .sidebar{ transform: translateX(0); }
      .overlay{ display:none; }
      body.sidebar-open .overlay{ display:block; }
      .main{ margin-left: 0; }
      .mobilebar{ display:block; }
    }

    /* Flash */
    .flash-success{
      margin: 14px 0;
      border: 1px solid var(--successBorder);
      background: var(--successBg);
      color: var(--successText);
      padding: 10px 12px;
      border-radius: 14px;
      font-weight: 500;
      font-size: 13px;
    }

    /* ===== Core UI ===== */
    .card{
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      overflow: hidden;
    }

    .card-header{
      padding: 16px;
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap: 14px;
      flex-wrap: wrap;
      border-bottom: 1px solid var(--border);
      background: #fff;
    }

    .h1{
      margin: 0;
      font-size: 22px;
      font-weight: 900;
      letter-spacing: -0.2px;
    }
    .p{
      margin: 6px 0 0;
      color: var(--muted);
      font-size: 13px;
      line-height: 1.45;
    }

    .grid{ display:grid; gap: 12px; }
    .grid-3{ grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .col-span-3{ grid-column: span 3 / span 3; }

    @media (max-width: 900px){
      .grid-3{ grid-template-columns: 1fr; }
      .col-span-3{ grid-column: span 1 / span 1; }
    }

    .btn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap: 8px;
      padding: 10px 12px;
      border-radius: 12px;
      border: 1px solid rgba(34,197,94,.28);
      background: rgba(34,197,94,.12);
      color: var(--green);
      font-weight: 900;
      font-size: 13px;
      text-decoration: none;
      cursor: pointer;
      white-space: nowrap;
    }
    .btn:hover{ background: rgba(34,197,94,.18); }
    .btn-soft{
      border-color: rgba(2,6,23,.10);
      background: #f8fafc;
      color: #0b1220;
    }
    .btn-soft:hover{ background: #f1f5f9; }
    .btn-danger{
      border-color: rgba(239,68,68,.25);
      background: rgba(239,68,68,.10);
      color: #b91c1c;
    }
    .btn-danger:hover{ background: rgba(239,68,68,.14); }

    .input{
      width: 100%;
      padding: 10px 12px;
      border: 1px solid var(--border);
      border-radius: 12px;
      background: #fff;
      font-size: 13px;
      outline: none;
    }
    .input:focus{
      border-color: rgba(34,197,94,.65);
      box-shadow: 0 0 0 4px var(--focus);
    }

    select.input, textarea.input{
      width: 100%;
      padding: 10px 12px;
      border: 1px solid var(--border);
      border-radius: 12px;
      background: #fff;
      font-size: 13px;
      outline: none;
    }
    select.input:focus, textarea.input:focus{
      border-color: rgba(34,197,94,.65);
      box-shadow: 0 0 0 4px var(--focus);
    }

    /* ===== DataTables overrides ===== */
    .dataTables_wrapper{ font-size:13px; }
    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select{
      padding: 10px 12px;
      border: 1px solid var(--border);
      border-radius: 12px;
      outline: none;
    }
    .dataTables_wrapper .dataTables_filter input:focus,
    .dataTables_wrapper .dataTables_length select:focus{
      border-color: rgba(34,197,94,.65);
      box-shadow: 0 0 0 4px var(--focus);
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button{
      border: 1px solid var(--border) !important;
      border-radius: 12px !important;
      padding: 6px 10px !important;
      margin: 0 2px !important;
      background: #fff !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current{
      border-color: rgba(34,197,94,.28) !important;
      background: rgba(34,197,94,.12) !important;
      color: var(--green) !important;
      font-weight: 900;
    }
    table.dataTable thead th{
      background: #f8fafc;
      font-weight: 900;
      border-bottom: 1px solid var(--border);
    }
    table.dataTable tbody td{
      border-bottom: 1px solid var(--border);
    }

    /* ===== Tom Select theme ===== */
    .ts-wrapper{ width:100%; }
    .ts-wrapper.single .ts-control{
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 10px 12px;
      background: #fff;
      box-shadow: none;
      font-size: 13px;
    }
    .ts-wrapper.single.focus .ts-control{
      border-color: rgba(34,197,94,.65);
      box-shadow: 0 0 0 4px var(--focus);
    }
    .ts-dropdown{
      border: 1px solid var(--border);
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 14px 40px rgba(2,6,23,.10);
      font-size: 13px;
    }
    .ts-dropdown .option{ padding: 10px 12px; }
    .ts-dropdown .active{ background: rgba(34,197,94,.10); }
    .ts-control .item{ font-weight: 800; }

    /* ✅ FIX: GIANT PAGINATION ARROWS (Laravel pagination uses SVG) */
    nav[role="navigation"] svg,
    .pagination svg{
      width: 18px !important;
      height: 18px !important;
      max-width: 18px !important;
      max-height: 18px !important;
      flex: 0 0 auto !important;
    }
    nav[role="navigation"] a,
    nav[role="navigation"] span{
      display: inline-flex;
      align-items: center;
      gap: 6px;
      line-height: 1.2;
    }
    nav[role="navigation"] .relative{ border-radius: 12px; }

    .grid-2{ display:grid; gap: 12px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    @media (max-width: 900px){ .grid-2{ grid-template-columns: 1fr; } }

    label{ display:block; font-weight: 900; font-size: 13px; margin-bottom: 6px; }
    .help{ margin-top: 4px; font-size: 12px; color: var(--muted); }

    nav[role="navigation"]{ margin-top: 8px; }
  </style>

  @stack('styles')
</head>

<body>
  @auth
    @php
      $role = auth()->user()->role ?? 'admin';
      $isHead = $role === 'head_admin';
      $path = request()->path();
      $is = function($needle) use ($path) {
        return trim($path,'/') === trim($needle,'/');
      };

      // inline icon helper
      $ico = function($name){
        $icons = [
          'dashboard' => '<svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10Z"></path><path d="M13 21h8V11h-8v10Z"></path><path d="M13 3h8v6h-8V3Z"></path><path d="M3 17h8v4H3v-4Z"></path></svg>',
          'seed' => '<svg viewBox="0 0 24 24"><path d="M12 21s7-4.5 7-11A7 7 0 0 0 5 10c0 6.5 7 11 7 11Z"></path><path d="M12 21V10"></path></svg>',
          'farmers' => '<svg viewBox="0 0 24 24"><path d="M16 11a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"></path><path d="M4 21a8 8 0 0 1 16 0"></path></svg>',
          'vax' => '<svg viewBox="0 0 24 24"><path d="M6 9l9 9"></path><path d="M7.5 7.5l9 9"></path><path d="M9 6l9 9"></path><path d="M14 4l6 6"></path><path d="M4 14l6 6"></path></svg>',
          'backup' => '<svg viewBox="0 0 24 24"><path d="M4 19a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7l-4-4H6a2 2 0 0 0-2 2v14Z"></path><path d="M8 13h8"></path><path d="M12 9v8"></path></svg>',
          'users' => '<svg viewBox="0 0 24 24"><path d="M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0Z"></path><path d="M4 21a8 8 0 0 1 16 0"></path><path d="M19 8v3"></path><path d="M17.5 9.5h3"></path></svg>',
          'chev-left' => '<svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"></path></svg>',
          'chev-right' => '<svg viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"></path></svg>',
        ];
        return $icons[$name] ?? '<svg viewBox="0 0 24 24"><path d="M7 3h7l3 3v15a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"></path><path d="M14 3v4a1 1 0 0 0 1 1h4"></path></svg>';
      };
    @endphp
  @endauth

  <div class="app-shell">
    {{-- Mobile overlay --}}
    <div class="overlay" onclick="closeSidebar()" aria-hidden="true"></div>

    {{-- LEFT SIDEBAR --}}
    <aside class="sidebar" aria-label="Primary navigation">
      <div class="sidebar-inner">
        <div class="sidebar-topbar">
          {{-- Brand (hidden when collapsed) --}}
          <div class="brand hide-when-collapsed">
            <img src="{{ asset('images/ramos.jpg') }}" alt="Ramos Logo">
            <img src="{{ asset('images/da.jpg') }}" alt="DA Logo">
            <div class="brand-text">
              <div class="brand-title">LGU Agriculture System</div>
              <div class="brand-sub">Ramos, Tarlac</div>
            </div>
          </div>

          {{-- ✅ Toggle button --}}
          @auth
            <button
              class="nav-hide-btn"
              type="button"
              id="navHideBtn"
              title="Hide sidebar"
              aria-label="Hide sidebar"
              data-icon-collapse='{!! e($ico("chev-left")) !!}'
              data-icon-expand='{!! e($ico("chev-right")) !!}'
            >
              {!! $ico('chev-left') !!}
            </button>
          @endauth
        </div>

        @auth
          {{-- Everything below will disappear when collapsed --}}
          <div class="hide-when-collapsed">
            <div class="userpill" title="Logged in user">
              <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width: 160px;">
                {{ auth()->user()->name }}
              </span>
              <span class="rolebadge {{ $isHead ? 'head' : '' }}">
                {{ $isHead ? 'HEAD ADMIN' : 'ADMIN' }}
              </span>
            </div>

            <div class="nav-section">
              <div class="nav-title">Navigation</div>
              <div class="navlinks">
                <a class="link {{ $is('dashboard') ? 'is-active' : '' }}" href="{{ route('dashboard') }}">
                  <span class="nav-ico" aria-hidden="true">{!! $ico('dashboard') !!}</span>
                  <span class="nav-text">Dashboard</span>
                </a>

                <a class="link {{ $is('rice-seed-distributions') ? 'is-active' : '' }}" href="{{ route('rice-seed-distributions.index') }}">
                  <span class="nav-ico" aria-hidden="true">{!! $ico('seed') !!}</span>
                  <span class="nav-text">Rice Seed Distribution</span>
                </a>

                <a class="link {{ $is('farmers') ? 'is-active' : '' }}" href="{{ route('farmers.index') }}">
                  <span class="nav-ico" aria-hidden="true">{!! $ico('farmers') !!}</span>
                  <span class="nav-text">Farmers</span>
                </a>

                <a class="link {{ $is('anti-rabies-vaccinations') ? 'is-active' : '' }}" href="{{ route('anti-rabies-vaccinations.index') }}">
                  <span class="nav-ico" aria-hidden="true">{!! $ico('vax') !!}</span>
                  <span class="nav-text">Vaccination</span>
                </a>

                <a class="link {{ $is('backups') ? 'is-active' : '' }}" href="{{ route('backups.index') }}">
                  <span class="nav-ico" aria-hidden="true">{!! $ico('backup') !!}</span>
                  <span class="nav-text">Backup Folder</span>
                </a>

                @if($isHead)
                  <a class="link {{ $is('admins') ? 'is-active' : '' }}" href="{{ route('admins.index') }}">
                    <span class="nav-ico" aria-hidden="true">{!! $ico('users') !!}</span>
                    <span class="nav-text">Users</span>
                  </a>
                @endif
              </div>
            </div>

            <form method="POST" action="{{ route('logout') }}" style="margin:0;">
              @csrf
              <button class="logout-btn" type="submit">Logout</button>
            </form>
          </div>
        @endauth
      </div>
    </aside>

    {{-- MAIN --}}
    <main class="main">
      {{-- Mobile top bar --}}
      @auth
        <div class="mobilebar">
          <div class="mobilebar-inner">
            <button class="menu-btn" type="button" aria-expanded="false" aria-controls="sidebarNav" onclick="toggleSidebar()">
              ☰ Menu
            </button>
            <div class="mobilebar-title">
              @yield('title', 'LGU Agriculture System')
            </div>
          </div>
        </div>
      @endauth

      <div class="container">
        @if(session('success'))
          <div class="flash-success">{{ session('success') }}</div>
        @endif

        @yield('content')
      </div>
    </main>
  </div>

  <!-- jQuery + DataTables -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>

  <!-- Tom Select -->
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      // DataTables
      if (window.jQuery && jQuery.fn.DataTable) {
        $('.js-datatable').each(function () {
          if (!$.fn.DataTable.isDataTable(this)) {
            $(this).DataTable({
              pageLength: 10,
              lengthMenu: [10, 25, 50, 100]
            });
          }
        });
      }

      // Tom Select
      document.querySelectorAll('select.js-select').forEach(function (el) {
        if (el.tomselect) return;
        new TomSelect(el, {
          create: false,
          allowEmptyOption: true,
          sortField: { field: "text", direction: "asc" },
          placeholder: "Select..."
        });
      });

      // Restore desktop "removed navbar" state
      try{
        const collapsed = localStorage.getItem('nav_collapsed') === '1';
        if(collapsed && window.innerWidth > 900){
          document.body.classList.add('nav-collapsed');
        }
      }catch(e){}

      syncNavBtn();
    });

    function setAriaExpanded(isOpen){
      const btn = document.querySelector('.menu-btn');
      if(btn) btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    // Mobile drawer open/close
    function toggleSidebar(){
      const open = document.body.classList.toggle('sidebar-open');
      setAriaExpanded(open);
    }
    function closeSidebar(){
      document.body.classList.remove('sidebar-open');
      setAriaExpanded(false);
    }

    // Desktop: remove/restore navbar
    function toggleDesktopNav(){
      if(window.innerWidth <= 900) return; // desktop only
      const on = document.body.classList.toggle('nav-collapsed');
      try{ localStorage.setItem('nav_collapsed', on ? '1' : '0'); }catch(e){}
      syncNavBtn();
    }

    function syncNavBtn(){
      const btn = document.getElementById('navHideBtn');
      if(!btn) return;

      const collapsed = document.body.classList.contains('nav-collapsed');
      const iconCollapse = btn.getAttribute('data-icon-collapse');
      const iconExpand = btn.getAttribute('data-icon-expand');

      btn.innerHTML = collapsed ? iconExpand : iconCollapse;
      btn.setAttribute('aria-label', collapsed ? 'Show sidebar' : 'Hide sidebar');
      btn.setAttribute('title', collapsed ? 'Show sidebar' : 'Hide sidebar');
    }

    document.addEventListener('click', function(e){
      const btn = e.target.closest('#navHideBtn');
      if(btn){
        e.preventDefault();
        toggleDesktopNav();
      }
    });

    document.addEventListener('keydown', function(e){
      if(e.key === 'Escape') closeSidebar();

      // optional shortcut: Ctrl + \
      if((e.ctrlKey || e.metaKey) && e.key === '\\'){
        e.preventDefault();
        toggleDesktopNav();
      }
    });

    window.addEventListener('resize', function(){
      if(window.innerWidth > 900) closeSidebar();

      // On mobile, disable "navbar removed" mode (drawer controls nav)
      if(window.innerWidth <= 900){
        document.body.classList.remove('nav-collapsed');
        try{ localStorage.setItem('nav_collapsed','0'); }catch(e){}
      } else {
        try{
          const collapsed = localStorage.getItem('nav_collapsed') === '1';
          document.body.classList.toggle('nav-collapsed', collapsed);
        }catch(e){}
      }
      syncNavBtn();
    });
  </script>

  @stack('scripts')
</body>
</html>