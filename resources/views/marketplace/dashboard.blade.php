@extends('layouts.app')

@section('title', 'Dashboard Marketplace')

@section('content')
    {{-- Filter periode --}}
    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
        <h1 class="text-xl font-bold">Dashboard Marketplace</h1>
        <form method="GET" class="flex items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Dari</label>
                <input type="date" name="from" value="{{ $from->toDateString() }}"
                       class="rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Sampai</label>
                <input type="date" name="to" value="{{ $to->toDateString() }}"
                       class="rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
            </div>
            <button class="rounded-lg bg-slate-900 text-white px-4 py-2 text-sm font-semibold">Terapkan</button>
        </form>
    </div>
    <p class="text-xs text-slate-400 mb-5">Cakupan posting = snapshot saat ini. Kinerja & kecepatan = periode terpilih.</p>

    {{-- ===== PERLU PERHATIAN ===== --}}
    @if($alerts->isNotEmpty())
        <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3">
            <p class="text-sm font-bold text-rose-800 mb-2">⚠️ Perlu Perhatian</p>
            <ul class="space-y-1">
                @foreach($alerts as $a)
                    <li class="flex items-start gap-2 text-sm
                        {{ $a['level'] === 'red' ? 'text-rose-700' : 'text-amber-700' }}">
                        <span>{{ $a['level'] === 'red' ? '🔴' : '🟡' }}</span>
                        <span>{{ $a['msg'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 font-semibold">
            ✅ Semua dalam kondisi baik.
        </div>
    @endif

    {{-- ===== DISKON ALERT ===== --}}
    @if($discountAlerts->isNotEmpty())
        <div class="mb-5 rounded-xl bg-amber-50 border border-amber-300 px-4 py-3">
            <p class="text-sm font-bold text-amber-800 mb-1">🏷️ {{ $discountAlerts->count() }} diskon berakhir ≤ 30 hari / sudah lewat</p>
            <ul class="text-xs text-amber-700 space-y-0.5">
                @foreach($discountAlerts as $d)
                    <li>
                        {{ $d->name }} <span class="text-amber-600">({{ $d->stores->pluck('name')->join(', ') ?: 'tanpa toko' }})</span>:
                        @if($d->hasEnded())
                            <b class="text-rose-600">SUDAH BERAKHIR {{ $d->ends_at->translatedFormat('d M H:i') }} — cabut diskonnya!</b>
                        @else
                            berakhir {{ $d->ends_at->translatedFormat('d M Y H:i') }}
                        @endif
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('marketplace.discounts.index') }}" class="text-xs font-semibold text-amber-800 underline">Kelola diskon →</a>
        </div>
    @endif

    {{-- ===== RINGKASAN PER MARKETPLACE ===== --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-6">
        @foreach($byMarketplace as $mp => $m)
            <div class="bg-white rounded-xl border border-slate-200 px-4 py-3">
                <p class="text-xs text-slate-500">{{ ucfirst($mp) }}</p>
                <p class="text-lg font-bold">{{ $m['posted'] }}
                    <span class="text-sm font-normal text-slate-400">/ {{ $m['target'] }}</span>
                </p>
                @if($m['unposted'] > 0)
                    <p class="text-[11px] text-rose-600 font-semibold">{{ $m['unposted'] }} belum</p>
                @else
                    <p class="text-[11px] text-emerald-600 font-semibold">✓ lengkap</p>
                @endif
            </div>
        @endforeach
    </div>

    {{-- ===== PER TOKO ===== --}}
    <section class="mb-6">
        <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-2">Per Toko</h2>
        <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-slate-500 uppercase tracking-wide bg-slate-50">
                    <tr>
                        <th class="px-4 py-3">Toko</th>
                        <th class="px-2 py-3">Target</th>
                        <th class="px-2 py-3">Posted</th>
                        <th class="px-2 py-3">Antri Post</th>
                        <th class="px-2 py-3">Antri Harga</th>
                        <th class="px-2 py-3">Revisi</th>
                        <th class="px-2 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($stores as $r)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-2.5 font-semibold">
                                {{ $r['store']->label() }}
                                <p class="text-[11px] text-slate-400 font-normal">
                                    PIC: {{ $r['store']->pics->pluck('name')->join(', ') ?: '—' }}
                                </p>
                            </td>
                            <td class="px-2 py-2.5">{{ $r['target'] }}</td>
                            <td class="px-2 py-2.5 text-emerald-700 font-semibold">{{ $r['posted'] }}</td>
                            <td class="px-2 py-2.5">{{ $r['pending_post'] }}</td>
                            <td class="px-2 py-2.5 {{ $r['pending_price'] > 0 ? 'text-amber-600 font-semibold' : '' }}">
                                {{ $r['pending_price'] }}
                            </td>
                            <td class="px-2 py-2.5 {{ $r['pending_revisi'] > 0 ? 'text-rose-600 font-semibold' : '' }}">
                                {{ $r['pending_revisi'] }}
                            </td>
                            <td class="px-2 py-2.5 text-right">
                                @php $needTask = $r['unposted'] - $r['pending_post']; @endphp
                                @if($needTask > 0)
                                    <form method="POST"
                                          action="{{ route('marketplace.dashboard.generate', $r['store']) }}"
                                          onsubmit="return confirm('Buat {{ $needTask }} tugas posting untuk {{ $r['store']->name }}?')">
                                        @csrf
                                        <button class="rounded-lg bg-slate-900 text-white text-xs font-semibold px-3 py-1.5">
                                            + {{ $needTask }} backlog
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-400">✓ tercakup</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <div class="grid lg:grid-cols-2 gap-4 mb-6">
        {{-- Per brand --}}
        <section class="bg-white rounded-xl border border-slate-200 p-4">
            <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-3">Per Brand</h2>
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-slate-400">
                    <tr><th class="py-1.5">Brand</th><th>Target</th><th>Posted</th><th>Belum</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($brands as $b)
                        <tr>
                            <td class="py-2 font-medium">{{ $b['brand']->name }}</td>
                            <td>{{ $b['target'] }}</td>
                            <td class="text-emerald-700">{{ $b['posted'] }}</td>
                            <td class="{{ $b['unposted'] > 0 ? 'text-rose-600 font-semibold' : '' }}">
                                {{ $b['unposted'] }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-3 text-slate-400">Belum ada brand terpetakan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        {{-- Per PIC brand --}}
        <section class="bg-white rounded-xl border border-slate-200 p-4">
            <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-3">
                Kinerja PIC Brand · {{ $from->translatedFormat('d M') }}–{{ $to->translatedFormat('d M Y') }}
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-slate-400">
                        <tr>
                            <th class="py-1.5">PIC</th>
                            <th>Brand</th>
                            <th title="Total produk × toko target">Target</th>
                            <th>Posted</th>
                            <th>Belum</th>
                            <th title="Tugas selesai periode ini">✓ Post</th>
                            <th title="Rata-rata jam dari tugas harga dibuat → selesai">⚡ Harga</th>
                            <th title="Tugas update harga tertua yang masih pending">🕐 Nunggu</th>
                            <th title="Jumlah hari absen masuk di periode ini">🗓 Masuk</th>
                            <th title="Rata-rata tugas posting selesai per hari masuk">📈 /hari</th>
                            <th title="Selesai periode ini ÷ (selesai + antrian pending sekarang)">✅ %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($people as $p)
                            <tr>
                                <td class="py-2 font-semibold">{{ $p['user']->name }}</td>
                                <td class="text-xs text-slate-500">{{ $p['brands'] }}</td>
                                <td>{{ $p['target'] }}</td>
                                <td class="text-emerald-700">{{ $p['posted'] }}</td>
                                <td class="{{ $p['unposted'] > 0 ? 'text-rose-600 font-semibold' : '' }}">
                                    {{ $p['unposted'] }}
                                </td>
                                <td>{{ $p['posting_done'] }}</td>
                                <td class="{{ $p['avg_hours_price'] !== null && $p['avg_hours_price'] > 24 ? 'text-rose-600 font-semibold' : '' }}">
                                    {{ $p['avg_hours_price'] !== null ? $p['avg_hours_price'].' j' : '—' }}
                                </td>
                                <td class="{{ $p['oldest_hours'] !== null && $p['oldest_hours'] >= 72 ? 'text-rose-600 font-bold' : ($p['oldest_hours'] >= 24 ? 'text-amber-600 font-semibold' : '') }}">
                                    @if($p['oldest_hours'] !== null)
                                        {{ round($p['oldest_hours']) }} j
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                <td>{{ $p['days_present'] > 0 ? $p['days_present'] : '—' }}</td>
                                <td class="font-semibold">
                                    {{ $p['avg_post_day'] !== null ? $p['avg_post_day'] : '—' }}
                                </td>
                                <td class="{{ $p['completion_pct'] !== null && $p['completion_pct'] < 50 ? 'text-rose-600 font-semibold' : '' }}">
                                    {{ $p['completion_pct'] !== null ? $p['completion_pct'].'%' : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="py-3 text-slate-400">
                                    Belum ada PIC brand. Assign PIC di menu Brand → Edit.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">
                ⚡ Harga = rata-rata jam dari tugas update harga dibuat sampai diselesaikan.
                🕐 Nunggu = umur tugas update harga tertua yang belum dikerjakan.
                Merah ≥ 72 jam, Kuning ≥ 24 jam.
                🗓 Masuk = hari absen masuk di periode. 📈 /hari = posting selesai ÷ hari masuk. "—" = belum ada absensi di periode ini.
            </p>
        </section>
    </div>

    {{-- ===== GRAFIK POSTING HARIAN PER KARYAWAN ===== --}}
    @if($people->isNotEmpty())
        <section class="bg-white rounded-xl border border-slate-200 p-4 mb-6">
            <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-3">
                Posting Harian per Karyawan · {{ $from->translatedFormat('d M') }}–{{ $to->translatedFormat('d M Y') }}
            </h2>
            <div style="position:relative;height:300px;">
                <canvas id="chartPosting"></canvas>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">
                Satu garis = satu orang. Titik = jumlah tugas posting selesai di tanggal itu (sama definisinya dengan kolom ✓ Post).
                Ganti periode lewat filter Dari/Sampai di atas.
            </p>
        </section>

        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
        (function () {
            var el = document.getElementById('chartPosting');
            if (!el || typeof Chart === 'undefined') return;

            var rawLabels = @json($chartLabels);
            var labels = rawLabels.map(function (d) {
                var p = d.split('-'); return p[2] + '/' + p[1];   // 2026-07-12 → 12/07
            });

            new Chart(el, {
                type: 'line',
                data: { labels: labels, datasets: @json($chartDatasets) },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                        tooltip: { callbacks: { title: function (items) { return 'Tanggal ' + items[0].label; } } }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Posting selesai' } },
                        x: { ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 16 } }
                    }
                }
            });
        })();
        </script>
    @endif
@endsection