<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Vendor Portal') — QuakeLogic</title>
    <style>
        :root { --navy:#262261; --orange:#F26522; --ink:#1f2433; --muted:#6b7280; --line:#e5e7eb; --bg:#f4f5f8; }
        * { box-sizing: border-box; }
        body { margin:0; background:var(--bg); color:var(--ink); font-family:Helvetica,Arial,sans-serif; font-size:14px; line-height:1.5; }
        a { color:var(--navy); }
        .topbar { background:var(--navy); color:#fff; padding:14px 20px; display:flex; align-items:center; justify-content:space-between; }
        .topbar .brand { display:flex; align-items:center; gap:12px; }
        .topbar img { height:26px; }
        .topbar .kicker { color:var(--orange); font-size:11px; font-weight:bold; letter-spacing:2px; }
        .topbar form { margin:0; }
        .btn { display:inline-flex; align-items:center; gap:6px; background:var(--navy); color:#fff; border:0; border-radius:8px; padding:9px 16px; font-size:14px; font-weight:600; cursor:pointer; text-decoration:none; }
        .btn.orange { background:var(--orange); }
        .btn.ghost { background:transparent; color:#fff; border:1px solid rgba(255,255,255,.4); }
        .btn.sm { padding:5px 11px; font-size:12px; }
        .wrap { max-width:960px; margin:0 auto; padding:24px 20px; }
        .card { background:#fff; border:1px solid var(--line); border-radius:12px; padding:20px; margin-bottom:20px; }
        .card h2 { margin:0 0 12px; font-size:13px; text-transform:uppercase; letter-spacing:1px; color:var(--muted); }
        table { width:100%; border-collapse:collapse; }
        th { text-align:left; font-size:11px; text-transform:uppercase; color:var(--muted); padding:8px; border-bottom:1px solid var(--line); }
        td { padding:10px 8px; border-bottom:1px solid #f0f1f6; }
        tr:last-child td { border-bottom:0; }
        .num { font-family:ui-monospace,Menlo,monospace; font-weight:600; }
        .right { text-align:right; }
        .pill { display:inline-block; padding:2px 9px; border-radius:999px; font-size:11px; font-weight:600; background:#eef0f8; color:var(--navy); }
        .muted { color:var(--muted); }
        .flash { border-radius:10px; padding:11px 15px; margin-bottom:16px; font-size:13px; }
        .flash.err { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; }
        .flash.ok { background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; }
        .login { max-width:400px; margin:8vh auto; }
        .field { margin-bottom:14px; }
        .field label { display:block; font-size:12px; font-weight:600; color:var(--muted); margin-bottom:5px; }
        .field input { width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:8px; font-size:14px; }
        .err-text { color:#b91c1c; font-size:12px; margin-top:5px; }
        .empty { color:var(--muted); padding:14px 8px; }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="brand">
            <img src="{{ asset('quakelogic-logo.png') }}" alt="QuakeLogic">
            <span class="kicker">VENDOR PORTAL</span>
        </div>
        @yield('topbar-actions')
    </div>

    <div class="wrap">
        @if(session('success'))<div class="flash ok">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="flash err">{{ session('error') }}</div>@endif
        @yield('content')
    </div>
</body>
</html>
