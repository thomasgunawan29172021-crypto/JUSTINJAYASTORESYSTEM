@extends('layouts.app')

@section('title', 'Absensi')

@section('content')
    <h1 class="text-xl font-bold mb-1">Absensi</h1>
    <a href="{{ route('attendance.myrecap') }}" class="text-sm text-emerald-700 hover:underline">Rekap bulanan saya →</a>

    @if($schedule)
        <p class="text-sm text-slate-500 mb-5">
            Jadwal Anda: masuk <b>{{ substr($schedule->clock_in_time, 0, 5) }}</b>,
            pulang <b>{{ substr($schedule->clock_out_time, 0, 5) }}</b>,
            off hari <b>{{ $schedule->offDayName() }}</b>
            @if($branch) · {{ $branch->name }} (radius {{ $branch->geofence_radius_m }} m) @endif
        </p>
    @else
        <p class="text-sm text-rose-600 mb-5">⚠️ Jadwal kerja Anda belum diatur — hubungi CEO sebelum absen.</p>
    @endif

    {{-- ============ PERMINTAAN FOTO ULANG DARI CEO ============ --}}
    @php
        $retakes = \App\Models\Attendance::where('user_id', auth()->id())
            ->where(fn ($q) => $q->where('retake_in_requested', true)->orWhere('retake_out_requested', true))
            ->orderByDesc('work_date')->get();
    @endphp

    @foreach($retakes as $rt)
        @foreach([['in', $rt->retake_in_requested, 'masuk'], ['out', $rt->retake_out_requested, 'pulang']] as [$type, $flagged, $label])
            @if($flagged)
                <div class="mb-4 rounded-xl bg-rose-50 border border-rose-300 px-4 py-3">
                    <p class="text-sm font-bold text-rose-700">🔄 CEO meminta foto ulang — absen {{ $label }} {{ $rt->work_date->translatedFormat('d M Y') }}</p>
                    @if($rt->retake_reason)<p class="text-xs text-rose-600 mt-0.5">Alasan: {{ $rt->retake_reason }}</p>@endif

                    <form method="POST" action="{{ route('attendance.retake', $rt) }}" class="mt-2 retake-form">
                        @csrf
                        <input type="hidden" name="type" value="{{ $type }}">
                        <input type="hidden" name="photo" class="rt-photo">
                        <video autoplay playsinline muted class="rt-cam w-full max-w-xs rounded-xl bg-slate-900 aspect-video object-cover"></video>
                        <img class="rt-preview hidden w-full max-w-xs rounded-xl aspect-video object-cover" alt="">
                        <canvas class="rt-canvas hidden"></canvas>
                        <div class="flex gap-2 mt-2 max-w-xs">
                            <button type="button" class="rt-capture flex-1 rounded-lg bg-slate-900 text-white text-xs font-semibold py-2">📸 Ambil</button>
                            <button type="button" class="rt-retry hidden flex-1 rounded-lg border border-slate-300 text-xs font-semibold py-2">🔄 Ulangi</button>
                            <button type="submit" disabled class="rt-submit flex-1 rounded-lg bg-emerald-500 text-white text-xs font-bold py-2 disabled:opacity-40">Kirim</button>
                        </div>
                    </form>
                </div>
            @endif
        @endforeach
    @endforeach

    <div class="grid lg:grid-cols-2 gap-4">
        {{-- ============ KARTU HARI INI ============ --}}
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-3">
                Hari Ini — {{ now()->translatedFormat('l, d M Y') }}
            </h2>

            @if($today)
                <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm mb-3">
                    <p class="font-semibold text-emerald-800">
                        ✅ Masuk {{ $today->clock_in_at->format('H:i') }}
                        @if($today->is_off_day)
                            <span class="ml-1 px-1.5 py-0.5 rounded bg-sky-100 text-sky-700 text-[10px]">hari off — sukarela</span>
                        @elseif($today->isLate())
                            <span class="ml-1 px-1.5 py-0.5 rounded bg-rose-100 text-rose-700 text-[10px]">telat {{ $today->late_minutes }} mnt</span>
                        @endif
                    </p>
                    <p class="text-xs text-emerald-700 mt-0.5">
                        📍 {{ $today->clock_in_distance_m }} m dari toko
                        · koordinat {{ $today->clock_in_lat }}, {{ $today->clock_in_lng }}
                    </p>
                </div>

                @if($today->clock_out_at)
                    <div class="rounded-lg bg-slate-50 border border-slate-200 px-4 py-3 text-sm">
                        <p class="font-semibold">
                            🏁 Pulang {{ $today->clock_out_at->format('H:i') }}
                            @if($today->auto_closed)
                                <span class="ml-1 px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 text-[10px]">ditutup sistem — perlu review CEO</span>
                            @endif
                        </p>
                        @if($today->clock_out_distance_m !== null)
                            <p class="text-xs text-slate-500 mt-0.5">📍 {{ $today->clock_out_distance_m }} m dari toko</p>
                        @endif
                    </div>
                @endif
            @endif

            @php $needForm = $schedule && (! $today || ! $today->clock_out_at); @endphp

            @if($needForm)
                <form method="POST" id="absen-form"
                      action="{{ ! $today ? route('attendance.clockin') : route('attendance.clockout') }}"
                      class="space-y-3 {{ $today ? 'mt-3' : '' }}">
                    @csrf
                    <input type="hidden" name="latitude"  id="inp-lat">
                    <input type="hidden" name="longitude" id="inp-lng">
                    <input type="hidden" name="photo"     id="inp-photo">

                    <video id="cam" autoplay playsinline muted
                           class="w-full rounded-xl bg-slate-900 aspect-video object-cover"></video>
                    <img id="preview" class="hidden w-full rounded-xl aspect-video object-cover" alt="foto absen">
                    <canvas id="canvas" class="hidden"></canvas>

                    <p id="cam-status" class="text-xs text-slate-500"></p>

                    <div class="flex gap-2">
                        <button type="button" id="btn-capture"
                                class="flex-1 rounded-lg bg-slate-900 text-white text-sm font-semibold py-2.5">
                            📸 Ambil Foto
                        </button>
                        <button type="button" id="btn-retake"
                                class="hidden flex-1 rounded-lg border border-slate-300 text-sm font-semibold py-2.5">
                            🔄 Ulangi
                        </button>
                    </div>

                    <p id="loc-status" class="text-xs text-slate-500">📍 Mengambil lokasi…</p>

                    <button id="btn-submit" disabled
                            class="w-full rounded-lg bg-emerald-500 text-white font-bold py-3 text-sm
                                   disabled:opacity-40 disabled:cursor-not-allowed hover:bg-emerald-400">
                        {{ ! $today ? '✅ Absen Masuk' : '🏁 Absen Pulang' }}
                    </button>
                    <p class="text-[11px] text-slate-400">Tombol aktif setelah foto diambil dan lokasi didapat.</p>
                </form>
            @elseif($today && $today->clock_out_at)
                <p class="text-sm text-slate-400 mt-2">Absensi hari ini lengkap. 👍</p>
            @endif
        </div>

        {{-- ============ RIWAYAT 14 HARI ============ --}}
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-3">Riwayat Saya</h2>
            @if($history->isEmpty())
                <p class="text-sm text-slate-400">Belum ada riwayat absen.</p>
            @else
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-slate-400">
                        <tr><th class="py-1.5">Tanggal</th><th>Masuk</th><th>Pulang</th><th>Ket.</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($history as $a)
                            <tr>
                                <td class="py-2">{{ $a->work_date->translatedFormat('d M') }}</td>
                                <td>{{ $a->clock_in_at->format('H:i') }}</td>
                                <td>{{ $a->clock_out_at?->format('H:i') ?? '—' }}</td>
                                <td class="space-x-1">
                                    @if($a->is_off_day)
                                        <span class="px-1.5 py-0.5 rounded bg-sky-100 text-sky-700 text-[10px]">off/sukarela</span>
                                    @elseif($a->isLate())
                                        <span class="px-1.5 py-0.5 rounded bg-rose-100 text-rose-700 text-[10px]">telat {{ $a->late_minutes }}m</span>
                                    @endif
                                    @if($a->auto_closed)
                                        <span class="px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 text-[10px]">auto-close</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    @if($needForm ?? false)
        <script>
            (() => {
                const video    = document.getElementById('cam');
                const preview  = document.getElementById('preview');
                const canvas   = document.getElementById('canvas');
                const btnCap   = document.getElementById('btn-capture');
                const btnRetry = document.getElementById('btn-retake');
                const btnGo    = document.getElementById('btn-submit');
                const inpLat   = document.getElementById('inp-lat');
                const inpLng   = document.getElementById('inp-lng');
                const inpPhoto = document.getElementById('inp-photo');
                const locStat  = document.getElementById('loc-status');
                const camStat  = document.getElementById('cam-status');

                let photoOk = false, locOk = false;
                const refresh = () => { btnGo.disabled = !(photoOk && locOk); };

                // Kamera depan
                navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
                    .then((stream) => { video.srcObject = stream; })
                    .catch(() => {
                        camStat.textContent = '❌ Kamera tidak bisa diakses. Izinkan kamera di browser, lalu muat ulang.';
                    });

                btnCap.addEventListener('click', () => {
                    if (!video.videoWidth) return;
                    canvas.width  = video.videoWidth;
                    canvas.height = video.videoHeight;
                    canvas.getContext('2d').drawImage(video, 0, 0);
                    const url = canvas.toDataURL('image/jpeg', 0.7);
                    inpPhoto.value = url;
                    preview.src = url;
                    video.classList.add('hidden');
                    preview.classList.remove('hidden');
                    btnCap.classList.add('hidden');
                    btnRetry.classList.remove('hidden');
                    photoOk = true; refresh();
                });

                btnRetry.addEventListener('click', () => {
                    inpPhoto.value = '';
                    preview.classList.add('hidden');
                    video.classList.remove('hidden');
                    btnRetry.classList.add('hidden');
                    btnCap.classList.remove('hidden');
                    photoOk = false; refresh();
                });

                // Lokasi
                if (!navigator.geolocation) {
                    locStat.textContent = '❌ Browser tidak mendukung geolokasi.';
                } else {
                    navigator.geolocation.getCurrentPosition(
                        (pos) => {
                            inpLat.value = pos.coords.latitude;
                            inpLng.value = pos.coords.longitude;
                            locStat.textContent = '📍 Lokasi didapat (akurasi ±' + Math.round(pos.coords.accuracy) + ' m): '
                                + pos.coords.latitude.toFixed(5) + ', ' + pos.coords.longitude.toFixed(5);
                            locOk = true; refresh();
                        },
                        () => {
                            locStat.textContent = '❌ Akses lokasi ditolak. Izinkan lokasi di browser, lalu muat ulang.';
                        },
                        { enableHighAccuracy: true, timeout: 15000 }
                    );
                }
            })();
        </script>
    @endif

    {{-- Kamera foto ulang — SENGAJA di luar @if($needForm): banner harus jalan
         walaupun form absen harian tidak tampil (mis. sudah clock-out). --}}
    <script>
    document.querySelectorAll('.retake-form').forEach(function (f) {
        var video = f.querySelector('.rt-cam'), prev = f.querySelector('.rt-preview'),
            canvas = f.querySelector('.rt-canvas'), cap = f.querySelector('.rt-capture'),
            retry = f.querySelector('.rt-retry'), submit = f.querySelector('.rt-submit'),
            inp = f.querySelector('.rt-photo');

        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
            .then(function (s) { video.srcObject = s; }).catch(function () {});

        cap.addEventListener('click', function () {
            if (!video.videoWidth) return;
            canvas.width = video.videoWidth; canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            inp.value = canvas.toDataURL('image/jpeg', 0.7);
            video.classList.add('hidden'); prev.src = inp.value; prev.classList.remove('hidden');
            cap.classList.add('hidden'); retry.classList.remove('hidden'); submit.disabled = false;
        });
        retry.addEventListener('click', function () {
            inp.value = ''; prev.classList.add('hidden'); video.classList.remove('hidden');
            retry.classList.add('hidden'); cap.classList.remove('hidden'); submit.disabled = true;
        });
    });
    </script>
@endsection