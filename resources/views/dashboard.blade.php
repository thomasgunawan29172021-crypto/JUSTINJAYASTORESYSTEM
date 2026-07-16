@extends('layouts.app')

@section('title', 'Dashboard')

@php $n = fn ($x) => number_format((int) $x, 0, ',', '.'); @endphp

@section('content')
    <h1 class="text-2xl font-bold">Hello {{ auth()->user()->name }} 👋</h1>

        @unless($isCeo)
            <p class="text-slate-500 mt-1 mb-6">Selamat datang di Justin Jaya Store.</p>

            @if($reminder)
                <div id="attReminder" class="hidden mb-4 max-w-md rounded-xl px-4 py-3 text-sm font-semibold"></div>
            @endif

            <a href="{{ route('attendance.index') }}"
            class="block max-w-md rounded-2xl bg-emerald-500 hover:bg-emerald-400 transition text-white p-6 shadow-lg shadow-emerald-500/20">
                <div class="flex items-center gap-4">
                    <span class="text-4xl">📍</span>
                    <div>
                        <p class="text-xl font-bold">Absen Sekarang</p>
                        <p class="text-sm text-emerald-50 mt-0.5">Klik untuk clock-in / clock-out hari ini</p>
                    </div>
                    <span class="ml-auto text-2xl">→</span>
                </div>
            </a>

            @if($reminder)
                <script>
                (function () {
                    var clockIn = @json($reminder['clockInTime']);   // "09:00:00"
                    var tol     = @json($reminder['toleranceMin']);  // 5
                    var box     = document.getElementById('attReminder');
                    if (!box) return;

                    // Kelas dasar di-reset penuh tiap render — mencegah kelas warna lama
                    // menumpuk saat fase geser (upcoming → now → late).
                    var BASE = 'mb-4 max-w-md rounded-xl px-4 py-3 text-sm font-semibold';

                    function parts(t) { var p = t.split(':'); return [+p[0], +p[1]]; }
                    var hm = parts(clockIn), h = hm[0], m = hm[1];

                    function scheduledToday() {
                        var d = new Date();
                        d.setHours(h, m, 0, 0);
                        return d;
                    }

                    var STYLES = {
                        upcoming: 'bg-sky-50 border border-sky-300 text-sky-700',
                        now:      'bg-amber-50 border border-amber-300 text-amber-700',
                        late:     'bg-rose-50 border border-rose-300 text-rose-700',
                    };

                    function render() {
                        var now = new Date();
                        var sched = scheduledToday();
                        var toleranceEnd = new Date(sched.getTime() + tol * 60000);
                        var reminderStart = new Date(sched.getTime() - 15 * 60000);

                        var state = null;
                        if (now >= reminderStart && now < sched) {
                            state = ['upcoming', '⏰ Jangan lupa absen ya — jadwal masuk pukul ' + clockIn.slice(0,5) + '.'];
                        } else if (now >= sched && now <= toleranceEnd) {
                            state = ['now', '📍 Sudah waktunya absen — yuk clock-in sekarang.'];
                        } else if (now > toleranceEnd) {
                            state = ['late', '🔴 Anda telat, silakan absen segera. Kalau lupa absen, hubungi CEO.'];
                        }

                        if (state) {
                            box.className = BASE + ' ' + STYLES[state[0]];
                            box.textContent = state[1];
                        } else {
                            box.className = BASE + ' hidden';
                        }
                    }

                    render();
                    setInterval(render, 30000); // recompute tiap 30 detik, tanpa request server
                })();
                </script>
            @endif
        @else
        <p class="text-slate-500 mt-1 mb-5">Pusat komando — ringkasan lintas modul.</p>

        {{-- ===== ALERT GABUNGAN ===== --}}
        @if($alerts->isNotEmpty())
            <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3">
                <p class="text-sm font-bold text-rose-800 mb-2">⚠️ Perlu Perhatian</p>
                <ul class="space-y-1.5">
                    @foreach($alerts as $a)
                        <li class="flex items-start gap-2 text-sm {{ $a['level'] === 'red' ? 'text-rose-700' : 'text-amber-700' }}">
                            <span>{{ $a['level'] === 'red' ? '🔴' : '🟡' }}</span>
                            <span class="flex-1">
                                <span class="text-[10px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded bg-white/70 mr-1">{{ $a['modul'] }}</span>
                                {{ $a['msg'] }}
                                <a href="{{ route($a['route']) }}" class="underline font-semibold ml-1">lihat →</a>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @else
            <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 font-semibold">
                ✅ Semua modul dalam kondisi baik.
            </div>
        @endif

        {{-- ===== CUTI PENDING (semua yang menunggu) ===== --}}
        @if($pendingLeavesCount > 0)
            <div class="mb-5 rounded-xl bg-amber-50 border border-amber-300 px-4 py-3 flex items-center justify-between gap-2">
                <p class="text-sm font-bold text-amber-800">
                    🟡 {{ $pendingLeavesCount }} pengajuan cuti/izin menunggu keputusan
                </p>
                <a href="{{ route('leaves.manage') }}" class="text-xs font-semibold text-amber-800 underline shrink-0">Tinjau sekarang →</a>
            </div>
        @endif

        {{-- ===== CUTI NUNGGAK ===== --}}
        @if($overdueLeaves->isNotEmpty())
            <div class="mb-5 rounded-xl bg-rose-50 border border-rose-300 px-4 py-3">
                <p class="text-sm font-bold text-rose-700">🔴 {{ $overdueLeaves->count() }} pengajuan cuti/izin belum diputuskan, tanggalnya sudah lewat</p>
                <ul class="text-xs text-rose-600 mt-1 space-y-0.5">
                    @foreach($overdueLeaves as $l)
                        <li>{{ $l->user->name }} — {{ $l->type->label() }} ({{ $l->date_from->format('d/m') }}–{{ $l->date_to->format('d/m') }})</li>
                    @endforeach
                </ul>
                <a href="{{ route('leaves.manage') }}" class="text-xs font-semibold text-rose-700 underline">Putuskan sekarang →</a>
            </div>
        @endif

        {{-- ===== KARTU KPI PER MODUL ===== --}}
        <div class="grid md:grid-cols-3 gap-4 mb-6">

            {{-- Servis --}}
            <a href="{{ route('service.dashboard') }}" class="block bg-white rounded-xl border border-slate-200 p-4 hover:border-emerald-400 transition">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide">🔧 Servis</h2>
                    <span class="text-[11px] text-slate-400">detail →</span>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><p class="text-2xl font-bold">{{ $n($kpi['svcOpen']) }}</p><p class="text-[11px] text-slate-500">tiket aktif</p></div>
                    <div><p class="text-2xl font-bold {{ $kpi['svcMacet7'] > 0 ? 'text-rose-600' : '' }}">{{ $n($kpi['svcMacet7']) }}</p><p class="text-[11px] text-slate-500">macet ≥ 7 hari</p></div>
                    <div><p class="text-lg font-bold {{ $kpi['svcBelumKabar'] > 0 ? 'text-amber-600' : '' }}">{{ $n($kpi['svcBelumKabar']) }}</p><p class="text-[11px] text-slate-500">belum dikabari</p></div>
                    <div><p class="text-lg font-bold">{{ $n($kpi['svcKonfirmasi']) }}</p><p class="text-[11px] text-slate-500">tunggu konfirmasi</p></div>
                </div>
            </a>

            {{-- Marketplace --}}
            <a href="{{ route('marketplace.dashboard') }}" class="block bg-white rounded-xl border border-slate-200 p-4 hover:border-emerald-400 transition">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide">🛒 Marketplace</h2>
                    <span class="text-[11px] text-slate-400">detail →</span>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><p class="text-2xl font-bold text-emerald-700">{{ $n($kpi['mpPosted']) }}</p><p class="text-[11px] text-slate-500">total terposting</p></div>
                    <div><p class="text-2xl font-bold {{ $kpi['mpPending'] > 0 ? 'text-slate-900' : '' }}">{{ $n($kpi['mpPending']) }}</p><p class="text-[11px] text-slate-500">tugas antri</p></div>
                    <div><p class="text-lg font-bold {{ $kpi['mpPrice'] > 0 ? 'text-amber-600' : '' }}">{{ $n($kpi['mpPrice']) }}</p><p class="text-[11px] text-slate-500">update harga</p></div>
                    <div><p class="text-lg font-bold {{ $kpi['mpRevisi'] > 0 ? 'text-rose-600' : '' }}">{{ $n($kpi['mpRevisi']) }}</p><p class="text-[11px] text-slate-500">revisi</p></div>
                </div>
            </a>

            {{-- Sosmed --}}
            <a href="{{ route('sosmed.report') }}" class="block bg-white rounded-xl border border-slate-200 p-4 hover:border-emerald-400 transition">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide">🎬 Sosmed</h2>
                    <span class="text-[11px] text-slate-400">detail →</span>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><p class="text-2xl font-bold">{{ $n($kpi['smVideosBulan']) }}</p><p class="text-[11px] text-slate-500">video bulan ini</p></div>
                    <div><p class="text-2xl font-bold {{ $kpi['smDue'] > 0 ? 'text-amber-600' : '' }}">{{ $n($kpi['smDue']) }}</p><p class="text-[11px] text-slate-500">due update</p></div>
                </div>
            </a>
        </div>

        {{-- ===== GRAFIK 30 HARI TERAKHIR ===== --}}
        <div class="flex flex-wrap items-center gap-2 mb-3">
            <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mr-2">Grafik 30 Hari Terakhir</h2>
            <label class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white cursor-pointer select-none">
                <input type="checkbox" class="accent-emerald-500 chart-toggle" data-panel="panelMp" checked> 📦 Posting Marketplace
            </label>
            <label class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white cursor-pointer select-none">
                <input type="checkbox" class="accent-emerald-500 chart-toggle" data-panel="panelSm" checked> 🎬 Setoran Sosmed
            </label>
        </div>

        <div class="grid lg:grid-cols-2 gap-4 mb-6">
            <section id="panelMp" class="bg-white rounded-xl border border-slate-200 p-4">
                <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">📦 Posting Marketplace / hari</h3>
                @if($chartMp->isEmpty())
                    <p class="text-sm text-slate-400 py-8 text-center">Belum ada tugas posting selesai 30 hari terakhir.</p>
                @else
                    <div style="position:relative;height:240px;"><canvas id="cvMp"></canvas></div>
                @endif
            </section>
            <section id="panelSm" class="bg-white rounded-xl border border-slate-200 p-4">
                <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">🎬 Setoran Video Sosmed / hari</h3>
                @if($chartSm->isEmpty())
                    <p class="text-sm text-slate-400 py-8 text-center">Belum ada video tercatat 30 hari terakhir.</p>
                @else
                    <div style="position:relative;height:240px;"><canvas id="cvSm"></canvas></div>
                @endif
            </section>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
        (function () {
            if (typeof Chart === 'undefined') return;

            var raw    = @json($chartLabels);
            var labels = raw.map(function (d) { var p = d.split('-'); return p[2] + '/' + p[1]; });

            function mkLine(canvasId, datasets) {
                var el = document.getElementById(canvasId);
                if (!el || !datasets.length) return;
                new Chart(el, {
                    type: 'line',
                    data: { labels: labels, datasets: datasets },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } },
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } },
                                  x: { ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } } }
                    }
                });
            }

            mkLine('cvMp', @json($chartMp));
            mkLine('cvSm', @json($chartSm));

            // Toggle panel
            var toggles = document.querySelectorAll('.chart-toggle');
            toggles.forEach(function (t) {
                t.addEventListener('change', function () {
                    var panel = document.getElementById(t.getAttribute('data-panel'));
                    if (panel) panel.style.display = t.checked ? '' : 'none';
                });
            });
        })();
        </script>
    @endunless
@endsection
