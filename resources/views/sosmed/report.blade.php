@extends('layouts.app')

@section('title', 'Laporan Sosmed')

@php $n = fn ($x) => number_format((int) $x, 0, ',', '.'); @endphp

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
        <h1 class="text-xl font-bold">Laporan Sosmed — {{ $month->translatedFormat('F Y') }}</h1>
        <form method="GET" class="flex items-end gap-2">
            <input type="month" name="month" value="{{ $month->format('Y-m') }}"
                   class="rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
            <button class="rounded-lg bg-slate-900 text-white px-4 py-2 text-sm font-semibold">Terapkan</button>
        </form>
    </div>

    {{-- ===== ALERT ===== --}}
    @if($alerts->isNotEmpty())
        <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3">
            <p class="text-sm font-bold text-rose-800 mb-2">⚠️ Perlu Perhatian</p>
            <ul class="space-y-1">
                @foreach($alerts as $a)
                    <li class="flex items-start gap-2 text-sm {{ $a['level'] === 'red' ? 'text-rose-700' : 'text-amber-700' }}">
                        <span>{{ $a['level'] === 'red' ? '🔴' : '🟡' }}</span><span>{{ $a['msg'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 font-semibold">
            ✅ Semua dalam kondisi baik.
        </div>
    @endif

    {{-- ===== TARGET (CEO) ===== --}}
    <div class="mb-5 bg-white rounded-xl border border-slate-200 p-4 flex flex-wrap items-end justify-between gap-3">
        <p class="text-sm">
            🎯 Target berlaku: <b>{{ $currentTarget !== null ? $currentTarget.' video/hari' : 'belum diatur' }}</b>
            <span class="block text-[11px] text-slate-400 mt-0.5">Dinilai hanya di hari pegawai absen masuk. Riwayat target tersimpan — laporan lama tidak berubah.</span>
        </p>
        @if(auth()->user()->role->isCeo())
            <form method="POST" action="{{ route('sosmed.targets.store') }}" class="flex items-end gap-2">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Video/hari</label>
                    <input type="number" name="video_count" min="1" max="100" required value="{{ $currentTarget ?? 4 }}"
                           class="w-24 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Berlaku mulai</label>
                    <input type="date" name="effective_from" required value="{{ now()->toDateString() }}"
                           class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <button class="rounded-lg bg-slate-900 text-white px-4 py-2 text-sm font-semibold">Ubah Target</button>
            </form>
        @endif
    </div>

    {{-- ===== PER CABANG ===== --}}
    @foreach($byBranch as $branchName => $rows)
        <section class="mb-6">
            <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-2">🏢 {{ $branchName }}</h2>
            <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-slate-500 uppercase tracking-wide bg-slate-50">
                        <tr>
                            <th class="px-4 py-3">Pegawai</th>
                            <th class="px-2 py-3" title="Video disetor bulan ini">Video</th>
                            <th class="px-2 py-3" title="Hari absen masuk">Masuk</th>
                            <th class="px-2 py-3" title="Video ÷ hari masuk">/hari</th>
                            <th class="px-2 py-3" title="Hari masuk yang setorannya ≥ target">Capai Target</th>
                            <th class="px-2 py-3">%</th>
                            <th class="px-2 py-3" title="Total views (pencatatan terakhir per video)">Views</th>
                            <th class="px-2 py-3">Like</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($rows as $r)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2.5 font-semibold">{{ $r['user']->name }}
                                    <p class="text-[11px] text-slate-400 font-normal">{{ $r['user']->role->label() }}</p>
                                </td>
                                <td class="px-2 py-2.5">{{ $r['videos'] }}</td>
                                <td class="px-2 py-2.5">{{ $r['days_present'] > 0 ? $r['days_present'] : '—' }}</td>
                                <td class="px-2 py-2.5 font-semibold">{{ $r['avg_per_day'] ?? '—' }}</td>
                                <td class="px-2 py-2.5">{{ $r['days_present'] > 0 ? $r['days_met'].' / '.$r['days_present'] : '—' }}</td>
                                <td class="px-2 py-2.5 font-bold
                                    {{ $r['met_pct'] === null ? 'text-slate-300' : ($r['met_pct'] >= 80 ? 'text-emerald-600' : ($r['met_pct'] >= 50 ? 'text-amber-600' : 'text-rose-600')) }}">
                                    {{ $r['met_pct'] !== null ? $r['met_pct'].'%' : '—' }}
                                </td>
                                <td class="px-2 py-2.5">{{ $n($r['views']) }}</td>
                                <td class="px-2 py-2.5">{{ $n($r['likes']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">
                Konsistensi (%) = hari masuk yang setorannya ≥ target ÷ total hari masuk. Hari tanpa absen tidak dihitung.
                Video colab hanya dihitung untuk PIC-nya. Target yang dipakai adalah target yang berlaku di tanggal itu.
            </p>
        </section>
    @endforeach

    {{-- ===== GRAFIK MULTI-METRIK ===== --}}
    @if(!empty($chartData['videos']) && $chartData['videos']->isNotEmpty())
        <section class="bg-white rounded-xl border border-slate-200 p-4 mb-6">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide">Grafik Performa Harian</h2>
                <div class="flex flex-wrap gap-1.5" id="metricToggles">
                    @foreach(['videos' => 'Setoran', 'views' => 'Views', 'likes' => 'Likes', 'comments' => 'Komen', 'saves' => 'Save'] as $key => $lbl)
                        <label class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1.5 rounded-lg border cursor-pointer
                                      border-slate-300 bg-white text-slate-600 hover:border-emerald-400 select-none" data-metric-label="{{ $key }}">
                            <input type="checkbox" value="{{ $key }}" class="accent-emerald-500" @checked($key === 'videos')> {{ $lbl }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div style="position:relative;height:320px;"><canvas id="chartSosmed"></canvas></div>
            <p class="text-[11px] text-slate-400 mt-2">
                Centang metrik yang mau ditampilkan — bisa gabung. Sumbu kiri = angka besar (views/like/komen/save), sumbu kanan = setoran.
                Garis putus merah = target harian (muncul saat Setoran aktif). Titik ditaruh di tanggal tayang video.
            </p>
        </section>

        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
        (function () {
            var el = document.getElementById('chartSosmed');
            if (!el || typeof Chart === 'undefined') return;

            var raw    = @json($chartLabels);
            var labels = raw.map(function (d) { var p = d.split('-'); return p[2] + '/' + p[1]; });
            var ALL    = @json($chartData);           // { videos:[...], views:[...], ... }
            var target = @json($currentTarget);
            var METRIC_LABEL = { videos:'Setoran', views:'Views', likes:'Likes', comments:'Komen', saves:'Save' };

            var chart = null;

            function selected() {
                return Array.prototype.map.call(
                    document.querySelectorAll('#metricToggles input:checked'),
                    function (c) { return c.value; }
                );
            }

            function build() {
                var metrics = selected();
                var datasets = [];

                metrics.forEach(function (metric) {
                    (ALL[metric] || []).forEach(function (ds) {
                        // beri tahu metrik apa di legend kalau lebih dari 1 metrik dipilih
                        var d = Object.assign({}, ds);
                        d.label = metrics.length > 1 ? ds.label + ' · ' + METRIC_LABEL[metric] : ds.label;
                        datasets.push(d);
                    });
                });

                // garis target hanya saat Setoran dipilih
                if (metrics.indexOf('videos') !== -1 && target !== null) {
                    datasets.push({
                        label: 'Target', data: labels.map(function () { return target; }),
                        borderColor: '#f43f5e', borderDash: [6, 4], pointRadius: 0, fill: false, yAxisID: 'ySmall'
                    });
                }

                var usesLeft  = datasets.some(function (d) { return d.yAxisID === 'yBig'; });
                var usesRight = datasets.some(function (d) { return d.yAxisID === 'ySmall'; });

                if (chart) chart.destroy();
                chart = new Chart(el, {
                    type: 'line',
                    data: { labels: labels, datasets: datasets },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } } },
                        scales: {
                            yBig:   { display: usesLeft,  position: 'left',  beginAtZero: true, title: { display: true, text: 'Views / Like / Komen / Save' } },
                            ySmall: { display: usesRight, position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { precision: 0 }, title: { display: true, text: 'Setoran' } },
                            x: { ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 16 } }
                        }
                    }
                });
            }

            document.getElementById('metricToggles').addEventListener('change', build);
            build();
        })();
        </script>
    @endif
@endsection