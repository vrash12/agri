{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Agriculture Information System')</title>

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
      --sidebarCollapsedW: 82px; /* compact ChatGPT-style icon rail */
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
       COLLAPSED = COMPACT ICON RAIL
       The sidebar remains visible, but labels and secondary text disappear.
       ========================================================= */
    body.nav-collapsed .sidebar{
      width: var(--sidebarCollapsedW);
      background: #fff;
      border-right-color: var(--border);
      overflow: visible;
    }
    body.nav-collapsed .sidebar::before{
      display:block;
      height:4px;
    }
    body.nav-collapsed .sidebar-inner{
      width: auto;
      min-height:100%;
      padding: 12px 10px 14px;
    }
    body.nav-collapsed .hide-when-collapsed{
      display:none !important;
    }

    /* =========================================================
       SIDEBAR TOP AREA
       ========================================================= */
    .sidebar-inner{
      display:flex;
      flex-direction:column;
      min-height:100%;
    }

    .sidebar-topbar{
      position: relative;
      margin-bottom: 12px;
      flex:0 0 auto;
    }

    .brand{
      display:flex;
      align-items:center;
      gap: 10px;
      min-height:58px;
      padding: 10px 54px 10px 10px;
      border: 1px solid var(--border);
      border-radius: 16px;
      background: #fff;
      box-shadow: 0 10px 26px rgba(2,6,23,.06);
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
    .brand-title{
      font-weight: 900;
      font-size: 12px;
      line-height: 1.2;
      white-space:normal;
      overflow:hidden;
      display:-webkit-box;
      -webkit-box-orient:vertical;
      -webkit-line-clamp:2;
    }
    .brand-sub{
      margin-top:3px;
      font-size: 10px;
      color: var(--muted);
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }

    .rail-brand{
      display:none;
      width:52px;
      height:52px;
      margin:0 auto;
      padding:7px;
      border:0;
      border-radius:14px;
      background:transparent;
      cursor:pointer;
      transition:background .15s ease, transform .15s ease;
    }
    .rail-brand:hover{
      background:#f1f5f9;
      transform:translateY(-1px);
    }
    .rail-brand img{
      display:block;
      width:38px;
      height:38px;
      object-fit:contain;
      border-radius:12px;
      background:#fff;
      border:1px solid rgba(2,6,23,.08);
      padding:3px;
    }

    .nav-hide-btn{
      position:absolute;
      top: 7px;
      right: 7px;
      width: 42px;
      height: 42px;
      border-radius: 13px;
      border: 1px solid rgba(2,6,23,.10);
      background: #fff;
      display:flex;
      align-items:center;
      justify-content:center;
      cursor:pointer;
      color:#0b1220;
      box-shadow: 0 8px 20px rgba(2,6,23,.10);
      transition: transform .10s ease, background .12s ease, border-color .12s ease, box-shadow .12s ease;
      z-index: 200;
    }
    .nav-hide-btn:hover{
      background: #f8fafc;
      transform: translateY(-1px);
    }
    .nav-hide-btn:focus,
    .rail-brand:focus{
      outline: none;
      border-color: rgba(34,197,94,.65);
      box-shadow: 0 0 0 4px var(--focus);
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

    body.nav-collapsed .brand,
    body.nav-collapsed .nav-hide-btn{
      display:none;
    }
    body.nav-collapsed .rail-brand{
      display:flex;
      align-items:center;
      justify-content:center;
    }

    /* =========================================================
       NAV LINKS
       ========================================================= */
    .nav-section{ margin-top: 10px; }
    .nav-title{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:8px;
      font-size: 10px;
      font-weight: 900;
      color: var(--muted);
      padding: 7px 10px;
      text-transform: uppercase;
      letter-spacing: .08em;
    }
    .nav-title small{display:grid;place-items:center;min-width:20px;height:18px;padding:0 5px;border-radius:999px;color:#607067;background:#f0f4f1;font-size:8px;letter-spacing:0}
    .navlinks{
      display:flex;
      flex-direction: column;
      gap: 6px;
      padding: 0 2px;
    }

    .link{
      text-decoration:none;
      color:#0b1220;
      font-weight: 900;
      font-size: 12px;
      min-height:58px;
      padding: 8px 10px;
      border-radius: 14px;
      border: 1px solid var(--border);
      background: #fff;
      display:flex;
      align-items:center;
      justify-content:flex-start;
      white-space: nowrap;
      gap: 8px;
      position: relative;
      transition: transform .15s ease, background .15s ease, border-color .15s ease, box-shadow .15s ease;
      overflow: hidden;
    }
    .link:hover{
      border-color:#cbd9d0;
      background:#f8fbf9;
      transform: translateX(2px);
      box-shadow: 0 8px 20px rgba(2,6,23,.07);
    }
    .link:focus-visible{outline:none;border-color:#4d9a68;box-shadow:0 0 0 4px rgba(34,197,94,.14)}

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

    .nav-copy{
      display:grid;
      flex: 1;
      min-width: 0;
      gap:2px;
    }
    .link .nav-text{
      display:block;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .nav-description{display:block;overflow:hidden;color:#77847c;font-size:9px;font-weight:650;line-height:1.2;text-overflow:ellipsis;white-space:nowrap}
    .nav-badge{flex:0 0 auto;padding:3px 5px;border-radius:999px;color:#17643a;background:#e7f4eb;font-size:7px;font-weight:950;letter-spacing:.03em;text-transform:uppercase}
    .nav-arrow{display:grid;place-items:center;flex:0 0 auto;width:14px;color:#98a39c;font-size:18px;font-weight:500;transition:transform .15s ease,color .15s ease}
    .nav-badge + .nav-arrow{display:none}
    .link:hover .nav-arrow{color:#17643a;transform:translateX(2px)}
    .nav-tooltip{display:none}
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


    .sidebar-content{
      display:flex;
      flex:1;
      min-height:0;
      flex-direction:column;
    }

    .sidebar-nav-scroll{
      flex:1;
      min-height:0;
      overflow-y:auto;
      overflow-x:visible;
      scrollbar-width:thin;
      scrollbar-color:#cbd5e1 transparent;
    }

    body.nav-collapsed .sidebar-content{
      align-items:stretch;
    }
    body.nav-collapsed .sidebar-nav-scroll{
      overflow-y:auto;
      overflow-x:visible;
    }
    body.nav-collapsed .nav-section{
      margin-top:8px;
    }
    body.nav-collapsed .nav-title,
    body.nav-collapsed .nav-copy,
    body.nav-collapsed .nav-badge,
    body.nav-collapsed .nav-arrow,
    body.nav-collapsed .user-meta,
    body.nav-collapsed .logout-text{
      display:none !important;
    }
    body.nav-collapsed .navlinks{
      gap:4px;
      padding:0;
    }
    body.nav-collapsed .link{
      width:52px;
      min-height:48px;
      margin:0 auto;
      padding:3px;
      justify-content:center;
      gap:0;
      overflow:visible;
      border-color:transparent;
      border-radius:13px;
      background:transparent;
      box-shadow:none;
    }
    body.nav-collapsed .link:hover{
      transform:none;
      background:#f1f5f9;
      border-color:transparent;
      box-shadow:none;
    }
    body.nav-collapsed .link::before{
      left:-7px;
      top:11px;
      bottom:11px;
      width:3px;
    }
    body.nav-collapsed .nav-ico{
      width:42px;
      height:42px;
      border:0;
      border-radius:12px;
      background:transparent;
    }
    body.nav-collapsed .link.is-active{
      border-color:transparent;
      background:#ecfdf5;
      color:var(--green);
      box-shadow:none;
    }
    body.nav-collapsed .link.is-active .nav-ico{
      border:0;
      background:transparent;
      transform:none;
      animation:none;
    }
    body.nav-collapsed .link:hover .nav-tooltip,
    body.nav-collapsed .link:focus-visible .nav-tooltip{
      position:absolute;z-index:500;left:58px;top:50%;display:block;width:max-content;
      max-width:210px;padding:7px 9px;transform:translateY(-50%);border:1px solid #d8e2db;
      border-radius:8px;color:#fff;background:#17211b;box-shadow:0 10px 25px rgba(2,6,23,.18);
      font-size:9px;font-weight:800;pointer-events:none;
    }

    @media (prefers-reduced-motion: reduce){
      .link, .nav-ico{ transition:none; }
      .link.is-active .nav-ico{ animation:none; }
      .link:hover{ transform:none; }
    }

    .userpill{
      display:flex;
      align-items:center;
      gap:10px;
      min-height:54px;
      padding:8px 10px;
      border-radius:15px;
      border:1px solid var(--border);
      background:#fff;
      margin-top:12px;
      overflow:hidden;
    }
    .user-avatar{
      width:36px;
      height:36px;
      display:flex;
      align-items:center;
      justify-content:center;
      flex:0 0 auto;
      border-radius:12px;
      color:#fff;
      background:linear-gradient(135deg, var(--green2), var(--green));
      font-size:13px;
      font-weight:900;
    }
    .user-meta{
      min-width:0;
      flex:1;
    }
    .user-name{
      overflow:hidden;
      text-overflow:ellipsis;
      white-space:nowrap;
      color:#0f172a;
      font-size:12px;
      font-weight:900;
    }
    .user-role{
      margin-top:3px;
      overflow:hidden;
      text-overflow:ellipsis;
      white-space:nowrap;
      color:var(--muted);
      font-size:10px;
      font-weight:700;
    }

    body.nav-collapsed .userpill{
      width:52px;
      min-height:48px;
      margin:10px auto 0;
      padding:6px;
      justify-content:center;
      border-color:transparent;
      background:transparent;
    }
    body.nav-collapsed .user-avatar{
      width:40px;
      height:40px;
    }

    .sidebar-footer{
      flex:0 0 auto;
      padding-top:8px;
    }

    .logout-btn{
      width:100%;
      min-height:44px;
      display:flex;
      align-items:center;
      justify-content:center;
      gap:9px;
      margin-top:8px;
      border:1px solid rgba(239,68,68,.20);
      background:#fff;
      color:#b91c1c;
      font-weight:900;
      padding:9px 12px;
      border-radius:13px;
      cursor:pointer;
      white-space:nowrap;
    }
    .logout-btn:hover{ background:#fef2f2; }
    .logout-icon{
      width:20px;
      height:20px;
      display:flex;
      align-items:center;
      justify-content:center;
      flex:0 0 auto;
    }
    .logout-icon svg{
      width:18px;
      height:18px;
      fill:none;
      stroke:currentColor;
      stroke-width:2;
      stroke-linecap:round;
      stroke-linejoin:round;
    }
    body.nav-collapsed .logout-btn{
      width:52px;
      height:48px;
      min-height:48px;
      margin:6px auto 0;
      padding:0;
      border-color:transparent;
      background:transparent;
    }
    body.nav-collapsed .logout-btn:hover{
      background:#fef2f2;
    }

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
      margin-left: var(--sidebarCollapsedW);
    }

.container{
  max-width: 2360px;
  margin: 0 auto;
  padding: 18px 10px 40px;
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
      /* Mobile always uses the full-width drawer. */
      body.nav-collapsed .sidebar{
        width:min(310px, calc(100vw - 32px));
        background:#fff;
        border-right-color: var(--border);
        overflow:auto;
      }
      body.nav-collapsed .sidebar::before{ display:block; }
      body.nav-collapsed .sidebar-inner{ padding:14px 14px 18px; width:auto; }
      body.nav-collapsed .brand{ display:flex; }
      body.nav-collapsed .nav-hide-btn{ display:flex; }
      body.nav-collapsed .rail-brand{ display:none; }
      body.nav-collapsed .nav-title,
      body.nav-collapsed .nav-text,
      body.nav-collapsed .user-meta,
      body.nav-collapsed .logout-text{ display:block !important; }
      body.nav-collapsed .link,
      body.nav-collapsed .userpill,
      body.nav-collapsed .logout-btn{
        width:auto;
        margin-left:0;
        margin-right:0;
      }
      body.nav-collapsed .link{
        min-height:48px;
        padding:8px 10px;
        justify-content:flex-start;
        gap:10px;
        border-color:var(--border);
        background:#fff;
      }
      body.nav-collapsed .nav-ico{
        width:34px;
        height:34px;
        border:1px solid rgba(2,6,23,.08);
        background:rgba(2,6,23,.03);
      }
      body.nav-collapsed .nav-copy{display:grid!important}
      body.nav-collapsed .nav-description{display:block!important}
      body.nav-collapsed .nav-badge{display:inline-flex!important}
      body.nav-collapsed .nav-arrow{display:grid!important}
      body.nav-collapsed .nav-tooltip{display:none!important}

      .sidebar{
        transform: translateX(-102%);
        transition: transform .18s ease;
        width:min(310px, calc(100vw - 32px));
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

    .flash-error{
      margin: 14px 0;
      border: 1px solid var(--dangerBorder);
      background: var(--dangerBg);
      color: var(--dangerText);
      padding: 10px 12px;
      border-radius: 14px;
      font-weight: 700;
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

    /* Shared pagination used by every Laravel paginator. */
    .agri-pagination{display:flex!important;align-items:center;justify-content:flex-end;width:auto;max-width:100%;margin:0!important;color:#536159;font-size:10px}
    .agri-pagination svg{width:14px!important;height:14px!important;max-width:14px!important;max-height:14px!important;flex:0 0 auto!important;fill:none;stroke:currentColor;stroke-width:2.1;stroke-linecap:round;stroke-linejoin:round}
    .agri-pagination-desktop,.agri-pagination-simple{display:flex;align-items:center;justify-content:flex-end;gap:6px}
    .agri-pagination-mobile{display:none;align-items:center;justify-content:space-between;width:100%;gap:8px}
    .agri-page-list{display:flex;align-items:center;gap:4px}
    .agri-page-control,.agri-page-link,.agri-page-ellipsis{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 8px;border:1px solid #dce4de;border-radius:7px;color:#435047;background:#fff;font:inherit;font-weight:850;line-height:1;text-decoration:none;box-shadow:none!important;transition:border-color .15s ease,color .15s ease,background .15s ease}
    .agri-page-control-wide{gap:5px;padding-inline:10px}
    .agri-page-control:hover,.agri-page-link:hover{color:#17643a;border-color:#9eb5a6;background:#f7faf8}
    .agri-page-link.is-current{color:#fff;border-color:#17643a;background:#17643a;box-shadow:0 3px 8px rgba(23,100,58,.16)!important}
    .agri-page-control.is-disabled{color:#a0aaa3;border-color:#e7ece8;background:#f8faf8;cursor:not-allowed}
    .agri-page-ellipsis{min-width:22px;padding:0;border-color:transparent;color:#89938c;background:transparent}
    .agri-page-position{display:inline-flex;align-items:center;justify-content:center;min-height:32px;padding:0 10px;border:1px solid #e1e7e3;border-radius:7px;color:#66736b;background:#f8faf8;white-space:nowrap}
    .agri-page-position strong{margin:0 3px;color:#17211b}
    .app-list-footer{display:flex;align-items:center;justify-content:space-between;gap:14px;min-height:53px;padding:9px 14px;border-top:1px solid #dde5df;background:linear-gradient(180deg,#fff,#fbfcfb)}
    .app-list-range{display:flex;align-items:center;gap:8px;min-width:0;color:#68756d;font-size:9px;line-height:1.4}.app-list-range strong{color:#25342b;font-weight:900}.app-list-range-icon{width:27px;height:27px;display:grid;place-items:center;flex:0 0 auto;border:1px solid #dfe7e1;border-radius:7px;color:#17643a;background:#f4f8f5}.app-list-range-icon svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.app-list-complete{display:inline-flex;align-items:center;gap:6px;padding:6px 8px;border-radius:999px;color:#4f6255;background:#edf4ef;font-size:8px;font-weight:850;white-space:nowrap}.app-list-complete i{width:6px;height:6px;border-radius:50%;background:#3b9a5c}
    @media(max-width:620px){.agri-pagination{width:100%}.agri-pagination-desktop{display:none}.agri-pagination-mobile{display:flex}.agri-pagination-simple{width:100%;justify-content:space-between}.agri-page-control{min-width:36px;height:34px}.agri-page-control-wide span{display:none}}
    @media(max-width:620px){.app-list-footer{align-items:stretch;flex-direction:column;padding:10px 12px}.app-list-range{justify-content:center}.app-list-complete{align-self:center}.app-list-footer .agri-pagination{width:100%}}

    .grid-2{ display:grid; gap: 12px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    @media (max-width: 900px){ .grid-2{ grid-template-columns: 1fr; } }

    label{ display:block; font-weight: 900; font-size: 13px; margin-bottom: 6px; }
    .help{ margin-top: 4px; font-size: 12px; color: var(--muted); }

  </style>

  @stack('styles')
</head>

<body>
  @auth
    @php
      $user = auth()->user();
      $role = $user->role ?? 'municipal_staff';

      $roleLabels = [
        'super_admin' => 'Super Admin',
        'provincial_staff' => 'Provincial Staff',
        'municipal_head' => 'Head Agriculturist',
        'municipal_staff' => 'Municipal Staff',
        'head_admin' => 'Head Admin',
        'admin' => 'Admin',
      ];

      $roleLabel = $roleLabels[$role] ?? ucwords(str_replace('_', ' ', $role));
      $isProvincialUser = in_array($role, ['super_admin', 'provincial_staff'], true);
      $canManageUsers = $user->canManageMunicipalStaff();
      $municipalityName = optional($user->municipality)->name;
      $officeLabel = $isProvincialUser
          ? 'Provincial Agriculture Office'
          : (($municipalityName ?: 'Municipality not assigned') . ', Tarlac');

      $initials = collect(explode(' ', trim($user->name ?? 'User')))
          ->filter()
          ->take(2)
          ->map(fn ($part) => strtoupper(mb_substr($part, 0, 1)))
          ->join('');

      $ico = function($name){
        $icons = [
          'dashboard' => '<svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10Z"></path><path d="M13 21h8V11h-8v10Z"></path><path d="M13 3h8v6h-8V3Z"></path><path d="M3 17h8v4H3v-4Z"></path></svg>',
          'seed' => '<svg viewBox="0 0 24 24"><path d="M12 21s7-4.5 7-11A7 7 0 0 0 5 10c0 6.5 7 11 7 11Z"></path><path d="M12 21V10"></path></svg>',
          'farmers' => '<svg viewBox="0 0 24 24"><path d="M16 11a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"></path><path d="M4 21a8 8 0 0 1 16 0"></path></svg>',
          'vax' => '<svg viewBox="0 0 24 24"><path d="M6 9l9 9"></path><path d="M7.5 7.5l9 9"></path><path d="M9 6l9 9"></path><path d="M14 4l6 6"></path><path d="M4 14l6 6"></path></svg>',
          'backup' => '<svg viewBox="0 0 24 24"><path d="M4 19a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7l-4-4H6a2 2 0 0 0-2 2v14Z"></path><path d="M8 13h8"></path><path d="M12 9v8"></path></svg>',
          'users' => '<svg viewBox="0 0 24 24"><path d="M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0Z"></path><path d="M4 21a8 8 0 0 1 16 0"></path><path d="M19 8v3"></path><path d="M17.5 9.5h3"></path></svg>',
          'audit' => '<svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path><path d="M9 11l2 2 4-4"></path><path d="M9 17h6"></path></svg>',
          'cooperative' => '<svg viewBox="0 0 24 24"><circle cx="8" cy="8" r="3"></circle><circle cx="17" cy="9" r="2.5"></circle><path d="M2.5 20a5.5 5.5 0 0 1 11 0"></path><path d="M13 19a4.5 4.5 0 0 1 8.5 0"></path></svg>',
          'machinery' => '<svg viewBox="0 0 24 24"><path d="M3 15h18v4H3z"></path><path d="M6 15V9h8l3 6"></path><path d="M9 9V6h4"></path><circle cx="7" cy="19" r="2"></circle><circle cx="18" cy="19" r="2"></circle></svg>',
          'chev-left' => '<svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"></path></svg>',
          'logout' => '<svg viewBox="0 0 24 24"><path d="M10 17l5-5-5-5"></path><path d="M15 12H3"></path><path d="M14 4h5a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-5"></path></svg>',
        ];

        return $icons[$name] ?? '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"></circle></svg>';
      };

      $operationBadge = $user->isSuperAdmin() ? 'View' : null;
      $navigationGroups = [
        'operations' => [
          'label' => 'Operations',
          'items' => [
            [
              'label' => 'Dashboard',
              'description' => 'Overview and trends',
              'icon' => 'dashboard',
              'route' => 'dashboard',
              'patterns' => ['dashboard'],
              'badge' => null,
            ],
            [
              'label' => 'Agriculture & Fisheries',
              'description' => 'Inputs, fingerlings, and gear',
              'icon' => 'seed',
              'route' => 'rice-seed-distributions.index',
              'patterns' => ['rice-seed-distributions.*'],
              'badge' => $operationBadge,
            ],
            [
              'label' => 'Farmers',
              'description' => 'Registry and parcel map',
              'icon' => 'farmers',
              'route' => 'farmers.index',
              'patterns' => ['farmers.*', 'farm-plots.*', 'weather.*'],
              'badge' => $operationBadge,
            ],
            [
              'label' => 'Vaccination',
              'description' => 'Animal health records',
              'icon' => 'vax',
              'route' => 'anti-rabies-vaccinations.index',
              'patterns' => ['anti-rabies-vaccinations.*'],
              'badge' => $operationBadge,
            ],
            [
              'label' => 'Farmers Cooperative',
              'description' => 'Organizations and members',
              'icon' => 'cooperative',
              'route' => 'farmers-cooperatives.index',
              'patterns' => ['farmers-cooperatives.*'],
              'badge' => $operationBadge,
            ],
            [
              'label' => 'Machinery Inventory',
              'description' => 'Assets, assignment, and service',
              'icon' => 'machinery',
              'route' => 'machinery-inventory.index',
              'patterns' => ['machinery-inventory.*'],
              'badge' => $operationBadge,
            ],
          ],
        ],
      ];

      if (! $user->isSuperAdmin()) {
        $navigationGroups['operations']['items'][] = [
          'label' => 'Backup Folder',
          'description' => 'Protected files and exports',
          'icon' => 'backup',
          'route' => 'backups.index',
          'patterns' => ['backups.*'],
          'badge' => null,
        ];
      }

      if ($canManageUsers && Route::has('admins.index')) {
        $navigationGroups['administration'] = [
          'label' => 'Administration',
          'items' => [[
            'label' => $user->isMunicipalHead() ? 'Municipal Staff' : 'User Management',
            'description' => $user->isMunicipalHead()
                ? 'Manage municipal staff'
                : 'Accounts, roles, and access',
            'icon' => 'users',
            'route' => 'admins.index',
            'patterns' => ['admins.*'],
            'badge' => $user->isSuperAdmin() ? 'Manage' : null,
          ]],
        ];
      }

      if ($user->canViewAuditTrail() && Route::has('audit-logs.index')) {
        $navigationGroups['administration']['items'][] = [
          'label' => 'Audit Trail',
          'description' => 'Security and change history',
          'icon' => 'audit',
          'route' => 'audit-logs.index',
          'patterns' => ['audit-logs.*'],
          'badge' => 'Monitor',
        ];
      }

    @endphp
  @endauth

  <div class="app-shell">
    <div class="overlay" onclick="closeSidebar()" aria-hidden="true"></div>

    <aside class="sidebar" id="sidebarNav" aria-label="Primary navigation">
      <div class="sidebar-inner">
        <div class="sidebar-topbar">
          @auth
            <div class="brand">

              <img src="{{ asset('images/da.jpg') }}" alt="Department of Agriculture logo">
              <div class="brand-text">
                <div class="brand-title">Agriculture Information System</div>
                <div class="brand-sub">{{ $officeLabel }}</div>
              </div>
            </div>

            <button
              class="nav-hide-btn"
              type="button"
              id="navHideBtn"
              title="Collapse sidebar"
              aria-label="Collapse sidebar"
            >
              {!! $ico('chev-left') !!}
            </button>

          <button
  class="rail-brand"
  type="button"
  id="railExpandBtn"
  title="Expand navigation"
  aria-label="Expand navigation"
>
  <img
    src="{{ asset('images/da.jpg') }}"
    alt="Department of Agriculture logo"
  >
</button>
          @endauth
        </div>

        @auth
          <div class="sidebar-content">
            <div class="sidebar-nav-scroll">
              @foreach($navigationGroups as $groupKey => $group)
                <section class="nav-section">
                  <div class="nav-title"><span>{{ $group['label'] }}</span><small>{{ count($group['items']) }}</small></div>

                  <nav class="navlinks" aria-label="{{ $group['label'] }} modules">
                    @foreach($group['items'] as $item)
                      @php($isActiveNavItem = request()->routeIs(...$item['patterns']))
                      <a
                        class="link {{ $isActiveNavItem ? 'is-active' : '' }}"
                        href="{{ route($item['route']) }}"
                        title="{{ $item['label'] }}"
                        data-nav-item
                        @if($isActiveNavItem) aria-current="page" @endif
                      >
                        <span class="nav-ico" aria-hidden="true">{!! $ico($item['icon']) !!}</span>
                        <span class="nav-copy">
                          <span class="nav-text">{{ $item['label'] }}</span>
                          <span class="nav-description">{{ $item['description'] }}</span>
                        </span>
                        @if($item['badge'])<span class="nav-badge">{{ $item['badge'] }}</span>@endif
                        <span class="nav-arrow" aria-hidden="true">›</span>
                        <span class="nav-tooltip" role="tooltip">{{ $item['label'] }}</span>
                      </a>
                    @endforeach
                  </nav>
                </section>
              @endforeach
            </div>

            <div class="sidebar-footer">
              <div class="userpill" title="{{ $user->name }} — {{ $roleLabel }}">
                <div class="user-avatar">{{ $initials ?: 'U' }}</div>
                <div class="user-meta">
                  <div class="user-name">{{ $user->name }}</div>
                  <div class="user-role">{{ $roleLabel }}</div>
                </div>
              </div>

              <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                @csrf
                <button class="logout-btn" type="submit" title="Logout">
                  <span class="logout-icon" aria-hidden="true">{!! $ico('logout') !!}</span>
                  <span class="logout-text">Logout</span>
                </button>
              </form>
            </div>
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
              @yield('title', 'Agriculture Information System')
            </div>
          </div>
        </div>
      @endauth

      <div class="container">
        @if(session('success'))
          <div class="flash-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
          <div class="flash-error">{{ session('error') }}</div>
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

      // Restore the desktop compact icon-rail state
      try{
        const collapsed = localStorage.getItem('nav_collapsed') === '1';
        if(collapsed && window.innerWidth > 900){
          document.body.classList.add('nav-collapsed');
        }
      }catch(e){}

      syncNavBtn();
      initializeNavigationLinks();
    });

    function initializeNavigationLinks(){
      const items = Array.from(document.querySelectorAll('[data-nav-item]'));
      if(!items.length) return;

      items.forEach(item => {
        item.addEventListener('keydown', event => {
          if(!['ArrowDown','ArrowUp'].includes(event.key)) return;
          event.preventDefault();
          const currentIndex = items.indexOf(item);
          const offset = event.key === 'ArrowDown' ? 1 : -1;
          items[(currentIndex + offset + items.length) % items.length]?.focus();
        });
        item.addEventListener('click', () => {
          if(window.innerWidth <= 900) closeSidebar();
        });
      });

      document.querySelector('[data-nav-item][aria-current="page"]')
        ?.scrollIntoView({block:'nearest'});
    }

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

    // Desktop: expand/collapse into the compact icon rail
    function toggleDesktopNav(){
      if(window.innerWidth <= 900) return; // desktop only
      const on = document.body.classList.toggle('nav-collapsed');
      try{ localStorage.setItem('nav_collapsed', on ? '1' : '0'); }catch(e){}
      syncNavBtn();
    }

    function syncNavBtn(){
      const collapsed = document.body.classList.contains('nav-collapsed');
      const btn = document.getElementById('navHideBtn');
      const railBtn = document.getElementById('railExpandBtn');

      if(btn){
        btn.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        btn.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
      }

      if(railBtn){
        railBtn.setAttribute('aria-label', 'Expand sidebar');
        railBtn.setAttribute('title', 'Expand sidebar');
      }
    }

    document.addEventListener('click', function(e){
      const btn = e.target.closest('#navHideBtn');
      if(btn){
        e.preventDefault();
        toggleDesktopNav();
        return;
      }

      const railBtn = e.target.closest('#railExpandBtn');
      if(railBtn){
        e.preventDefault();
        if(window.innerWidth > 900){
          document.body.classList.remove('nav-collapsed');
          try{ localStorage.setItem('nav_collapsed', '0'); }catch(e){}
          syncNavBtn();
        }
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

  <script>
    (function () {
      function resetSubmissionState(form) {
        delete form.dataset.submitPending;
        delete form.dataset.submitting;
        form.removeAttribute('aria-busy');
        form.querySelectorAll('[data-disabled-by-submit="true"]').forEach(function (control) {
          control.disabled = false;
          control.removeAttribute('data-disabled-by-submit');
        });
      }

      document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-allow-concurrent-submit')) {
          return;
        }

        if (form.dataset.submitPending === 'true' || form.dataset.submitting === 'true') {
          event.preventDefault();
          return;
        }

        // Mark immediately to catch a rapid second click, then wait until all
        // form-specific submit handlers have had a chance to cancel the event.
        form.dataset.submitPending = 'true';
        window.setTimeout(function () {
          if (event.defaultPrevented) {
            resetSubmissionState(form);
            return;
          }

          delete form.dataset.submitPending;
          form.dataset.submitting = 'true';
          form.setAttribute('aria-busy', 'true');
          form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (control) {
            if (!control.disabled) {
              control.disabled = true;
              control.setAttribute('data-disabled-by-submit', 'true');
            }
          });
        }, 0);
      });

      // Browsers can restore a submitted page from their back/forward cache.
      window.addEventListener('pageshow', function () {
        document.querySelectorAll('form[data-submitting="true"], form[data-submit-pending="true"]')
          .forEach(resetSubmissionState);
      });
    })();
  </script>

  @stack('scripts')
</body>
</html>
