<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') — Justin Jaya Command Center</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    @hasSection('autorefresh')
        <meta http-equiv="refresh" content="60">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>

    @php
        $u = auth()->user();
        $isCeo     = $u->role->isCeo();
        $isManager = $u->role->isManager();
        $isFinance = $u->role->canAccessFinance();
        $canService = $u->role->canAccessService();
        $isPic     = $u->brands()->exists();
        $canRetur   = $u->role->canProcessWarrantyClaim();
        $canInputRetur = $u->role->canCreateWarrantyClaim() || $canRetur;

        // [label, route name, boleh diakses?]
        $modules = [
            'servis' => ['label' => 'Servis', 'tiles' => [
                ['Dashboard Servis', 'service.dashboard', $canService],
                ['Tiket',            'service.tickets.index', $canService],
                ['KPI',              'service.kpi', $canService],
            ]],
            'retur' => ['label' => 'Returan', 'tiles' => [
                ['Klaim Retur',  'warranty.claims.index', $canInputRetur],
                ['Klaim Baru',   'warranty.claims.create', $canInputRetur],
                ['Vendor Retur', 'warranty.vendors.index', $canRetur],
            ]],
            'marketplace' => ['label' => 'Marketplace', 'tiles' => [
                ['Tugas Saya',   'marketplace.tasks.index', $isCeo || $isPic],
                ['Dashboard MP', 'marketplace.dashboard', $isCeo],
                ['Produk',       'marketplace.products.index', $isCeo],
                ['Brand',        'marketplace.brands.index', $isCeo],
                ['Toko',         'marketplace.stores.index', $isCeo],
                ['Diskon',       'marketplace.discounts.index', $isCeo],
            ]],
            'sosmed' => ['label' => 'Sosmed', 'tiles' => [
                ['Video Sosmed', 'sosmed.videos.index', $isCeo || $u->role === \App\Enums\UserRole::Sosmed],
                ['Update Metrik', 'sosmed.metrics.index', $isCeo || $u->role === \App\Enums\UserRole::Sosmed],
                ['Laporan Sosmed', 'sosmed.report', $isCeo || $u->role === \App\Enums\UserRole::Sosmed],
                ['Platform Sosmed', 'sosmed.platforms.index', $isCeo],   // master data — CEO only
                ['Leaderboard', 'sosmed.leaderboard', true],
            ]],
            'kalender' => ['label' => 'Kalender', 'tiles' => [
                ['Kalender', 'calendar.index', true],
            ]],
            'kepegawaian' => ['label' => 'Kepegawaian', 'tiles' => [
                ['Absensi',        'attendance.index', true],
                ['Izin & Cuti',    'leaves.index', true],
                ['Rekap Saya',     'attendance.myrecap', true],
                ['Approval Izin',  'leaves.manage', $isManager],
                ['Rekap Absensi',  'attendance.recap', $isCeo],
                ['Jadwal Kerja',   'attendance.schedules', $isCeo],
                ['Libur Nasional', 'holidays.index', $isCeo],
            ]],
            'keuangan' => ['label' => 'Keuangan', 'tiles' => [
                ['Payroll', 'payroll.index', $isFinance],
            ]],
            'pengaturan' => ['label' => 'Pengaturan', 'tiles' => [
                ['User Management',   'users.index', $isCeo],
                ['Pengaturan Cabang', 'branches.index', $isCeo],
                ['Pengaturan Harga',  'pricing.settings.index', $isCeo],
                ['Program Brand',     'pricing.brand-programs.index', $isCeo],
            ]],
        ];

        // Buang tile tanpa akses; buang modul yang jadi kosong (permission di level rail)
        foreach ($modules as $k => $m) {
            $modules[$k]['tiles'] = array_values(array_filter($m['tiles'], fn ($t) => $t[2]));
            if (empty($modules[$k]['tiles'])) unset($modules[$k]);
        }

        // Ikon garis per modul
        $icons = [
            'dashboard'   => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
            'servis'      => '<path d="M14.5 6.5a3.5 3.5 0 0 0-4.7 4.7L4 17v3h3l5.8-5.8a3.5 3.5 0 0 0 4.7-4.7l-2.2 2.2-2-2 2.2-2.2z"/>',
            'marketplace' => '<path d="M6 7h12l-1 13H7L6 7z"/><path d="M9 7a3 3 0 0 1 6 0"/>',
            'retur'       => '<path d="M3 9l4-5h10l4 5"/><path d="M3 9h18v11H3z"/><path d="M12 13v4M10 15l2 2 2-2"/>',
            'sosmed'      => '<rect x="2.5" y="5" width="15" height="14" rx="3"/><path d="M17.5 10.5 21.5 8v8l-4-2.5"/><circle cx="10" cy="12" r="2.5"/>',
            'kepegawaian' => '<circle cx="9" cy="8" r="3"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><path d="M16 5.5a3 3 0 0 1 0 6"/><path d="M20.5 20a5 5 0 0 0-3.5-4.8"/>',
            'keuangan'    => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18"/><circle cx="16.5" cy="14" r="1.2"/>',
            'pengaturan'  => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5.2 5.2l2.1 2.1M16.7 16.7l2.1 2.1M18.8 5.2l-2.1 2.1M7.3 16.7l-2.1 2.1"/>',
            'kalender'    => '<rect x="3" y="4.5" width="18" height="17" rx="2.5"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/><circle cx="12" cy="15" r="1"/>',
        ];

        // Emoji untuk logo tile di mega menu
        $tileEmojis = [
            'service.dashboard'             => '📊',
            'service.tickets.index'         => '🎫',
            'service.kpi'                   => '🎯',
            'marketplace.tasks.index'       => '✅',
            'marketplace.dashboard'         => '🛒',
            'marketplace.products.index'    => '📦',
            'marketplace.brands.index'      => '🏷️',
            'marketplace.stores.index'      => '🏬',
            'marketplace.discounts.index'   => '💸',
            'warranty.claims.index'         => '🔁',
            'warranty.claims.create'        => '📥',
            'warranty.vendors.index'        => '🚚',
            'attendance.index'              => '🕒',
            'leaves.index'                  => '🌿',
            'attendance.myrecap'            => '🧾',
            'leaves.manage'                 => '📝',
            'attendance.recap'              => '📅',
            'attendance.schedules'          => '🗓️',
            'holidays.index'                => '🏖️',
            'payroll.index'                 => '💰',
            'users.index'                   => '👥',
            'branches.index'                => '🏢',
            'pricing.settings.index'        => '🧮',
            'pricing.brand-programs.index'  => '🎁',
            'sosmed.videos.index'           => '🎬',
            'sosmed.metrics.index'          => '📈',
            'sosmed.report'                 => '📋',
            'sosmed.leaderboard'            => '🏆',
            'sosmed.platforms.index'        => '🌐',
            'calendar.index'                => '📅',
        ];

        // Peta route → label tab (dari menu + halaman detail yang tak ada di menu)
        $tabLabelMap = ['dashboard' => 'Dashboard'];
        foreach ($modules as $m) foreach ($m['tiles'] as $t) $tabLabelMap[$t[1]] = $t[0];
        $tabLabelMap += [
            'service.tickets.create'          => 'Tiket Baru',
            'service.tickets.show'            => 'Detail Tiket',
            'users.create'                    => 'User Baru',
            'users.edit'                      => 'Edit User',
            'attendance.recap.show'           => 'Rekap Karyawan',
            'attendance.corrections.edit'     => 'Koreksi Absen',
            'attendance.corrections.create'   => 'Input Absen Manual',
            'marketplace.products.create'     => 'Produk Baru',
            'marketplace.products.edit'       => 'Edit Produk',
            'marketplace.products.import.form'=> 'Import Produk',
            'marketplace.products.trash'      => 'Sampah Produk',
            'marketplace.brands.edit'         => 'Edit Brand',
            'marketplace.brands.trash'        => 'Sampah Brand',
            'marketplace.stores.edit'         => 'Edit Toko',
            'marketplace.stores.trash'        => 'Sampah Toko',
            'payroll.show'                    => 'Slip Gaji',
            'sosmed.videos.create'            => 'Catat Video',
            'sosmed.videos.edit'              => 'Edit Video',
            'warranty.claims.show'            => 'Detail Klaim',
            'warranty.claims.receipt'         => 'Nota Retur',
        ];

        $curRoute = request()->route()?->getName() ?? 'dashboard';
        $curLabel = $tabLabelMap[$curRoute] ?? 'Halaman';
        $curUrl   = request()->fullUrl();

        $activeModule = match (true) {
            str_starts_with($curRoute, 'service.')                            => 'servis',
            str_starts_with($curRoute, 'marketplace.')                        => 'marketplace',
            str_starts_with($curRoute, 'sosmed.')                             => 'sosmed',
            str_starts_with($curRoute, 'attendance.'),
            str_starts_with($curRoute, 'leaves.'),
            str_starts_with($curRoute, 'holidays.')                           => 'kepegawaian',
            str_starts_with($curRoute, 'payroll.')                            => 'keuangan',
            str_starts_with($curRoute, 'users.'),
            str_starts_with($curRoute, 'branches.'),
            str_starts_with($curRoute, 'pricing.')                            => 'pengaturan',
            $curRoute === 'dashboard'                                         => 'dashboard',
            str_starts_with($curRoute, 'calendar.')                            => 'kalender',
            str_starts_with($curRoute, 'warranty.')                           => 'retur',
            default                                                           => null,
        };
    @endphp

    <style>
        :root { --rail-w: 60px; }
        * { box-sizing: border-box; }
        body { margin: 0; }

        /* ---------- RAIL ---------- */
        .rail {
            position: fixed; inset: 0 auto 0 0; width: var(--rail-w); z-index: 50;
            background: #0f172a; display: flex; flex-direction: column; align-items: center;
            padding: 10px 0; gap: 6px; transition: transform .2s ease;
        }
        .rail-logo {
            width: 40px; height: 40px; border-radius: 11px; display: grid; place-items: center;
            background: #10b981; color: #fff; font-weight: 800; font-size: 18px; text-decoration: none; margin-bottom: 6px;
        }
        .rail-item {
            width: 42px; height: 42px; border-radius: 11px; display: grid; place-items: center;
            background: transparent; border: 0; color: #94a3b8; cursor: pointer; transition: .15s;
        }
        .rail-item:hover { background: rgba(255,255,255,.07); color: #fff; }
        .rail-item.active { background: #10b981; color: #fff; }
        .rail-item svg { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
        .rail-logout { margin-top: auto; color: #64748b; }
        .rail-logout:hover { color: #f43f5e; background: rgba(255,255,255,.05); }

/* ---------- MEGA MENU (floating card) ---------- */
        .mega {
            position: fixed;
            top: 72px;                 /* jarak dari atas — melayang, bukan nempel plafon */
            left: calc(var(--rail-w) + 12px);
            width: 560px;
            max-width: calc(100vw - var(--rail-w) - 24px);
            max-height: calc(100vh - 96px);   /* gak sampai dasar layar */
            z-index: 45;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            box-shadow: 0 24px 60px rgba(15,23,42,.22);
            overflow-y: auto;
            padding: 22px 24px;
            opacity: 0;
            transform: translateY(-8px) scale(.98);
            pointer-events: none;
            transition: opacity .16s ease, transform .16s ease;
        }
        .mega.show { opacity: 1; transform: none; pointer-events: auto; }
        .mega::-webkit-scrollbar { width: 8px; }
        .mega::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }

        .mega h2 { font-size: 20px; font-weight: 800; color: #0f172a; margin: 0 0 2px; }
        .mega p  { font-size: 13px; color: #64748b; margin: 0 0 8px; padding-bottom: 14px; border-bottom: 2px solid #10b981; }

        .mega-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 16px;
        }
        .tile {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 10px; padding: 20px 12px; min-height: 104px;
            border: 1px solid #bfdbfe; border-radius: 14px; background: #eff6ff;
            text-decoration: none; color: #1e3a5f; font-size: 13px; font-weight: 700; text-align: center;
            transition: .16s ease;
        }
        .tile:hover { transform: translateY(-3px); box-shadow: 0 12px 22px rgba(15,23,42,.12); border-color: #10b981; }
        .tile-ico {
            width: 46px; height: 46px; border-radius: 12px; display: grid; place-items: center;
            background: #dbeafe; color: #2563eb; font-size: 20px; font-weight: 800;
        }

        /* ---------- SCRIM ---------- */
        .scrim { position: fixed; inset: 0; background: rgba(15,23,42,.45); z-index: 44; display: none; }
        .scrim.show { display: block; }

        /* ---------- MAIN ---------- */
        .main { margin-left: var(--rail-w); min-height: 100vh; background: #f1f5f9; display: flex; flex-direction: column; }
        .topbar {
            position: sticky; top: 0; z-index: 40; height: 56px; background: #fff; border-bottom: 1px solid #e2e8f0;
            display: flex; align-items: center; gap: 12px; padding: 0 16px;
        }
        .burger { display: none; background: none; border: 0; font-size: 22px; color: #0f172a; cursor: pointer; }
        .topbar-brand { font-weight: 800; color: #0f172a; }
        .topbar-brand span { color: #10b981; }
        .topbar-user { margin-left: auto; display: flex; align-items: center; gap: 10px; }
        .topbar-ava { width: 32px; height: 32px; border-radius: 50%; background: #10b981; color: #fff; display: grid; place-items: center; font-weight: 700; font-size: 13px; }

        /* ---------- TAB BAR ---------- */
        .tabbar {
            display: flex; gap: 4px; padding: 6px 12px 4px; background: #f1f5f9; overflow-x: auto;
            scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent;   /* Firefox */
        }
        .tabbar::-webkit-scrollbar { height: 6px; }
        .tabbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
        .tabbar::-webkit-scrollbar-track { background: transparent; }
        .jj-tab.dragging { opacity: .45; }
        .jj-tab {
            display: inline-flex; align-items: center; gap: 6px; padding: 7px 10px 7px 12px; white-space: nowrap;
            background: #e2e8f0; color: #475569; border-radius: 8px 8px 0 0; font-size: 12.5px; font-weight: 600; cursor: pointer;
        }
        .jj-tab.active { background: #fff; color: #0f172a; }
        .jj-tab-x { border: 0; background: none; color: #94a3b8; font-size: 15px; line-height: 1; cursor: pointer; padding: 0 2px; }
        .jj-tab-x:hover { color: #f43f5e; }

        .content-wrap { flex: 1; padding: 16px; }
        @media (min-width: 640px) { .content-wrap { padding: 24px; } }

        /* ---------- MOBILE ---------- */
        @media (max-width: 1023px) {
            .rail { transform: translateX(-100%); }
            .app.rail-open .rail { transform: translateX(0); }
            .mega {
                top: 64px;
                left: calc(var(--rail-w) + 8px);
                right: 8px;
                width: auto; max-width: none;
                max-height: calc(100vh - 80px);
            }
            .main { margin-left: 0; }
            .burger { display: block; }
        }

        /* scrollbar konten */
        .content-wrap::-webkit-scrollbar, body::-webkit-scrollbar { width: 8px; height: 8px; }
        .content-wrap::-webkit-scrollbar-thumb, body::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
    </style>
</head>
<body class="text-slate-800 antialiased">

<div class="app" id="app">

    {{-- ================= RAIL ================= --}}
    <aside class="rail" id="rail">
        <a href="{{ route('dashboard') }}" class="rail-logo" title="Dashboard">J</a>

        @foreach($modules as $key => $mod)
            <button class="rail-item {{ $activeModule === $key ? 'active' : '' }}"
                    data-module="{{ $key }}" title="{{ $mod['label'] }}" aria-label="{{ $mod['label'] }}">
                <svg viewBox="0 0 24 24">{!! $icons[$key] ?? $icons['dashboard'] !!}</svg>
            </button>
        @endforeach

        <form method="POST" action="{{ route('logout') }}" class="rail-logout rail-item" style="margin-top:auto;">
            @csrf
            <button type="submit" title="Keluar" style="background:none;border:0;color:inherit;cursor:pointer;">
                <svg viewBox="0 0 24 24" style="width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>
                </svg>
            </button>
        </form>
    </aside>

    {{-- ================= MEGA MENU (satu per modul, permission sudah difilter di atas) ================= --}}
    @foreach($modules as $key => $mod)
        <div class="mega" data-mega="{{ $key }}">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                    <h2>{{ $mod['label'] }}</h2>
                </div>
                <button class="mega-close" data-close-mega title="Tutup"
                        style="width:34px;height:34px;border-radius:50%;border:0;background:#f1f5f9;color:#64748b;font-size:20px;cursor:pointer;flex-shrink:0;">×</button>
            </div>
            <p>Pilih menu di modul {{ strtolower($mod['label']) }}.</p>
            <div class="mega-grid">
                @foreach($mod['tiles'] as $t)
                    <a href="{{ route($t[1]) }}" class="tile">
                        <span class="tile-ico">{{ $tileEmojis[$t[1]] ?? '✨' }}</span>
                        {{ $t[0] }}
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach

    <div class="scrim" id="scrim"></div>

    {{-- ================= MAIN ================= --}}
    <div class="main">
        <header class="topbar">
            <button class="burger" id="burger" aria-label="Menu">☰</button>
            <span class="topbar-brand">Justin Jaya<span>.</span></span>

            <div class="topbar-user">
                <div style="text-align:right;line-height:1.1;">
                    <p style="font-size:13px;font-weight:600;margin:0;">{{ $u->name }}</p>
                    <p style="font-size:11px;color:#64748b;margin:0;">
                        {{ $u->role->label() }}{{ $u->branch ? ' · '.$u->branch->code : '' }}
                    </p>
                </div>
                <div class="topbar-ava">{{ strtoupper(mb_substr($u->name, 0, 1)) }}</div>
            </div>
        </header>

        {{-- TAB BAR (diisi JS dari localStorage) --}}
        <div class="tabbar" id="tabbar"></div>

        <main class="content-wrap">
            @if(session('ok'))
                <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">
                    {{ session('ok') }}
                </div>
            @endif
            @if($errors->any())
                <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script>
    window.__TAB__       = { key: @json($curRoute), label: @json($curLabel), url: @json($curUrl) };
    window.__DASHBOARD__ = @json(route('dashboard'));
</script>

<script>
/* ============ NAV: rail + mega-menu + burger mobile ============ */
(function () {
    var app    = document.getElementById('app');
    var scrim  = document.getElementById('scrim');
    var burger = document.getElementById('burger');
    var railBtns = document.querySelectorAll('.rail-item[data-module]');
    var megas  = document.querySelectorAll('.mega');

    function closeMega() {
        megas.forEach(function (m) { m.classList.remove('show'); });
        scrim.classList.remove('show');
        app.classList.remove('rail-open');
    }
    function openMega(key) {
        var target = document.querySelector('.mega[data-mega="' + key + '"]');
        megas.forEach(function (m) { m.classList.remove('show'); });
        if (target) { target.classList.add('show'); scrim.classList.add('show'); }
    }

    railBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var key = btn.getAttribute('data-module');
            var already = document.querySelector('.mega[data-mega="' + key + '"]').classList.contains('show');
            if (already) { closeMega(); } else { openMega(key); }
        });
    });

    document.querySelectorAll('[data-close-mega]').forEach(function (b) {
        b.addEventListener('click', closeMega);
    });

    scrim.addEventListener('click', closeMega);
    burger && burger.addEventListener('click', function () {
        app.classList.toggle('rail-open');
        scrim.classList.toggle('show', app.classList.contains('rail-open'));
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeMega(); });
})();

/* ============ TAB BAR: riwayat + drag-reorder via localStorage ============ */
(function () {
    var CUR = window.__TAB__ || {};
    var KEY = 'jj_tabs_v1';
    var bar = document.getElementById('tabbar');

    function load() { try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { return []; } }
    function save(t) { try { localStorage.setItem(KEY, JSON.stringify(t)); } catch (e) {} }
    function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

    var tabs = load();
    if (CUR.key) {
        var found = false;
        tabs = tabs.map(function (t) { if (t.key === CUR.key) { found = true; return { key: CUR.key, label: CUR.label, url: CUR.url }; } return t; });
        if (!found) tabs.push({ key: CUR.key, label: CUR.label, url: CUR.url });
        save(tabs);
    }

    function render() {
        if (!bar) return;
        bar.innerHTML = tabs.map(function (t) {
            var active = CUR.key && t.key === CUR.key;
            return '<div class="jj-tab' + (active ? ' active' : '') + '" draggable="true" data-key="' + esc(t.key) + '" data-url="' + encodeURI(t.url) + '">' +
                   '<span>' + esc(t.label) + '</span>' +
                   '<button class="jj-tab-x" data-key="' + esc(t.key) + '" aria-label="Tutup">×</button>' +
                   '</div>';
        }).join('');
    }
    render();

    /* --- klik: navigasi + tutup tab --- */
    bar && bar.addEventListener('click', function (e) {
        var x = e.target.closest('.jj-tab-x');
        if (x) {
            e.stopPropagation();
            var key = x.getAttribute('data-key');
            tabs = tabs.filter(function (t) { return t.key !== key; });
            save(tabs);
            if (CUR.key && key === CUR.key) {
                var next = tabs[tabs.length - 1];
                window.location = next ? next.url : window.__DASHBOARD__;
            } else { render(); }
            return;
        }
        var tab = e.target.closest('.jj-tab');
        if (tab) { window.location = tab.getAttribute('data-url'); }
    });

    /* --- drag-reorder (delegasi di bar, gak perlu re-attach tiap render) --- */
    var dragEl = null;

    function afterElement(x) {
        var els = Array.prototype.slice.call(bar.querySelectorAll('.jj-tab:not(.dragging)'));
        var closest = { offset: -Infinity, el: null };
        els.forEach(function (child) {
            var box = child.getBoundingClientRect();
            var offset = x - box.left - box.width / 2;
            if (offset < 0 && offset > closest.offset) closest = { offset: offset, el: child };
        });
        return closest.el;
    }

    bar && bar.addEventListener('dragstart', function (e) {
        var tab = e.target.closest('.jj-tab');
        if (!tab) return;
        dragEl = tab;
        tab.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        try { e.dataTransfer.setData('text/plain', tab.getAttribute('data-key')); } catch (_) {}
    });

    bar && bar.addEventListener('dragover', function (e) {
        if (!dragEl) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        var after = afterElement(e.clientX);
        if (after == null) { if (bar.lastElementChild !== dragEl) bar.appendChild(dragEl); }
        else if (after !== dragEl) { bar.insertBefore(dragEl, after); }
    });

    bar && bar.addEventListener('dragend', function () {
        if (!dragEl) return;
        dragEl.classList.remove('dragging');
        dragEl = null;
        var order = Array.prototype.map.call(bar.querySelectorAll('.jj-tab'), function (el) {
            return el.getAttribute('data-key');
        });
        tabs.sort(function (a, b) { return order.indexOf(a.key) - order.indexOf(b.key); });
        save(tabs);
    });
})();

/* ============ Money-input (dipertahankan) ============ */
(function () {
    document.querySelectorAll('.money-input').forEach(function (el) {
        el.addEventListener('input', function () {
            var before = el.value, cursor = el.selectionStart;
            var raw = before.replace(/\D/g, '');
            var grouped = raw ? raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';
            var digitsLeft = before.slice(0, cursor).replace(/\D/g, '').length;
            var newCursor = 0, seen = 0;
            for (var i = 0; i < grouped.length; i++) {
                if (seen >= digitsLeft) break;
                if (/\d/.test(grouped[i])) seen++;
                newCursor++;
            }
            el.value = grouped;
            el.setSelectionRange(newCursor, newCursor);
        });
        el.addEventListener('blur', function () {
            var raw = el.value.replace(/\D/g, '').replace(/^0+/, '');
            el.value = raw ? raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';
        });
    });
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function () {
            form.querySelectorAll('.money-input').forEach(function (el) { el.value = el.value.replace(/\D/g, ''); });
        });
    });
})();

/* ============ Input format Indonesia (pricing) ============ */
(function () {
    /* --- Rupiah: 10000 → 10.000. BEDA dari .money-input: nol TIDAK dibuang.
           Di pricing, 0 = "memang gratis" dan null = "belum diisi" — dua hal beda.
           .money-input punya .replace(/^0+/,'') yang bikin "0" jadi kosong. --- */
    document.querySelectorAll('.rp-input').forEach(function (el) {
        el.addEventListener('input', function () {
            var raw = el.value.replace(/\D/g, '');
            el.value = raw ? raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';
        });
    });

    /* --- Persen: izinkan angka, koma, titik. "3,5" dan "3.5" dua-duanya sah.
           Titik TIDAK dianggap pemisah ribuan di sini — persen gak pernah ribuan. --- */
    document.querySelectorAll('.percent-input').forEach(function (el) {
        el.addEventListener('input', function () {
            el.value = el.value.replace(/[^\d.,]/g, '');
        });
    });

    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function () {
            form.querySelectorAll('.rp-input').forEach(function (el) {
                el.value = el.value.replace(/\D/g, '');
            });
            form.querySelectorAll('.percent-input').forEach(function (el) {
                el.value = el.value.replace(',', '.');
            });
        });
    });
})();
</script>

<script>
/* ============ Draft form (anti-ilang pas pindah tab) ============
   Pasang: data-draft="nama-unik" di <form>. HANYA buat form CREATE —
   form edit jangan, restore draft basi di atas data server itu bahaya.
   Batas: file/foto GAK bisa disimpen (dilarang browser) — harus pilih ulang.

   URUTAN PENTING: blok ini WAJIB di atas searchable. Draft mulihin value
   <select> dulu, baru searchable baca value itu buat nampilin teksnya. */
(function () {
    document.querySelectorAll('form[data-draft]').forEach(function (f) {
        var key = 'jjdraft:' + f.getAttribute('data-draft');
        var saved = {};
        try { saved = JSON.parse(sessionStorage.getItem(key) || '{}'); } catch (e) {}

        function fields() {
            return Array.prototype.filter.call(f.elements, function (el) {
                return el.name && el.type !== 'file' && el.type !== 'password' && el.name !== '_token';
            });
        }

        /* Restore — old() dari server MENANG: field yang udah keisi gak ditimpa,
           biar abis gagal validasi gak ketimpa draft yang lebih lama. */
        fields().forEach(function (el) {
            if (!(el.name in saved)) return;
            if (el.type === 'checkbox' || el.type === 'radio') {
                if (!f.querySelector('[name="' + el.name.replace(/"/g, '') + '"]:checked')) {
                    el.checked = Array.isArray(saved[el.name])
                        ? saved[el.name].indexOf(el.value) !== -1
                        : saved[el.name] === el.value;
                }
            } else if (el.value === '') {
                el.value = saved[el.name];
            }
        });

        function snapshot() {
            var out = {};
            fields().forEach(function (el) {
                if (el.type === 'checkbox') {
                    out[el.name] = out[el.name] || [];
                    if (el.checked) out[el.name].push(el.value);
                } else if (el.type === 'radio') {
                    if (el.checked) out[el.name] = el.value;
                } else if (el.value !== '') {
                    out[el.name] = el.value;
                }
            });
            try { sessionStorage.setItem(key, JSON.stringify(out)); } catch (e) {}
        }

        f.addEventListener('input',  snapshot);
        f.addEventListener('change', snapshot);
        f.addEventListener('submit', function () { sessionStorage.removeItem(key); });
    });
})();
</script>

<script>
/* ============ Searchable dropdown (semua select produk) ============
   Pasang: kasih atribut data-searchable ke <select> mana pun.
   Select aslinya TETAP yang ke-submit — komponen ini cuma kulit pencarian,
   jadi validasi server & controller gak perlu diubah sama sekali. */
(function () {
    window.jjSearchable = function (sel) {
        if (!sel || sel.dataset.jjDone) return;
        sel.dataset.jjDone = '1';

        var wrap = document.createElement('div');
        wrap.className = 'relative';
        /* Bawa kelas layout dari select ke pembungkus. Tanpa ini, select yang
           tadinya flex-1 (mis. baris komponen bundle) kolaps jadi sempit begitu
           dibungkus div biasa — div-nya gak ikut melar di flex row. */
        ['flex-1', 'min-w-0'].forEach(function (c) {
            if (sel.classList.contains(c)) wrap.classList.add(c);
        });

        sel.parentNode.insertBefore(wrap, sel);
        wrap.appendChild(sel);
        sel.classList.add('hidden');

        var input = document.createElement('input');
        input.type = 'text';
        input.autocomplete = 'off';
        input.placeholder = 'Ketik untuk cari…';
        input.className = sel.className.replace('hidden', '') +
            ' w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white';

        var panel = document.createElement('div');
        panel.className = 'hidden absolute z-30 mt-1 w-full max-h-56 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg';

        wrap.appendChild(input);
        wrap.appendChild(panel);

        function options() {
            return Array.prototype.filter.call(sel.options, function (o) { return o.value !== ''; });
        }
        function syncFromSelect() {
            var o = sel.options[sel.selectedIndex];
            input.value = (o && o.value !== '') ? o.text : '';
        }
        function render(q) {
            q = (q || '').toLowerCase();
            var hits = options().filter(function (o) { return o.text.toLowerCase().indexOf(q) !== -1; });
            panel.innerHTML = hits.length
                ? hits.slice(0, 50).map(function (o) {
                      return '<button type="button" data-v="' + o.value + '" class="block w-full text-left px-3 py-2 text-sm hover:bg-emerald-50">'
                          + o.text.replace(/</g, '&lt;') + '</button>';
                  }).join('')
                : '<p class="px-3 py-2 text-sm text-slate-400">Tidak ketemu.</p>';
            panel.classList.remove('hidden');
        }

        input.addEventListener('focus', function () { input.select(); render(input.value); });
        input.addEventListener('input', function () { render(input.value); });
        input.addEventListener('blur',  function () {
            setTimeout(function () { panel.classList.add('hidden'); syncFromSelect(); }, 150);
        });
        panel.addEventListener('mousedown', function (e) {
            var b = e.target.closest('[data-v]');
            if (!b) return;
            sel.value = b.getAttribute('data-v');
            syncFromSelect();
            panel.classList.add('hidden');
            /* Penting: panel harga rekomendasi & bundle dengerin event ini */
            sel.dispatchEvent(new Event('change', { bubbles: true }));
            sel.dispatchEvent(new Event('input',  { bubbles: true }));
        });

        syncFromSelect();
    };

    document.querySelectorAll('select[data-searchable]').forEach(window.jjSearchable);
})();
</script>
</body>
</html>