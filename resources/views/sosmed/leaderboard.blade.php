@extends('layouts.app')

@section('title', 'Leaderboard Sosmed')

@php $n = fn ($x) => number_format((int) $x, 0, ',', '.'); @endphp

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
        <h1 class="text-xl font-bold">🏆 Leaderboard Sosmed — {{ $month->translatedFormat('F Y') }}</h1>
        <form method="GET" class="flex items-end gap-2">
            <input type="month" name="month" value="{{ $month->format('Y-m') }}"
                   class="rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
            <button class="rounded-lg bg-slate-900 text-white px-4 py-2 text-sm font-semibold">Terapkan</button>
        </form>
    </div>

    @if($boards['videos']['best']->isEmpty())
        <div class="bg-white rounded-xl border border-dashed border-slate-300 p-6 text-sm text-slate-400">
            Belum ada video tercatat bulan ini. Jadilah yang pertama! 🎬
        </div>
    @else
        <div class="flex items-center gap-2 mb-3">
            <div class="inline-flex rounded-lg border border-slate-300 overflow-hidden text-xs font-semibold">
                <button type="button" class="lb-mode px-3 py-1.5 bg-slate-900 text-white" data-mode="best">🏆 Terbaik</button>
                <button type="button" class="lb-mode px-3 py-1.5 bg-white text-slate-600" data-mode="worst">📉 Terburuk</button>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-4">
            @foreach($boards as $key => $board)
                <section class="bg-white rounded-xl border border-slate-200 p-4">
                    <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-1">{{ $board['title'] }}</h2>
                    @if($key === 'met')
                        <p class="text-[11px] text-slate-400 mb-2">% hari masuk yang setorannya mencapai target. Hari off/cuti/libur tidak dihitung. Video colab hanya dihitung untuk PIC-nya.</p>
                    @else
                        <p class="text-[11px] text-slate-400 mb-2">{{ $key === 'videos' ? 'Jumlah video yang disetor (sebagai PIC) bulan ini.' : 'Total views gabungan semua platform, pencatatan terakhir.' }}</p>
                    @endif

                    @foreach(['best', 'worst'] as $mode)
                        <ol class="divide-y divide-slate-100 lb-list" data-mode="{{ $mode }}" @if($mode === 'worst') style="display:none" @endif>
                            @forelse($board[$mode]->take(10) as $i => $r)
                                <li class="flex items-center justify-between gap-2 py-2.5 {{ $r['user']->id === $me ? 'bg-emerald-50 -mx-2 px-2 rounded-lg' : '' }}">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <span class="w-7 text-center shrink-0 {{ $mode === 'best' && $i < 3 ? 'text-base' : 'text-xs text-slate-400 font-semibold' }}">
                                            {{ $mode === 'best' ? (['🥇','🥈','🥉'][$i] ?? ($i+1).'.') : ($i+1).'.' }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold truncate">{{ $r['user']->name }}{{ $r['user']->id === $me ? ' (saya)' : '' }}</p>
                                            <p class="text-[11px] text-slate-400 truncate">
                                                {{ $r['user']->branch?->code ?? '—' }} ·
                                                @if($key === 'met') {{ $r['days_met'] }}/{{ $r['days_present'] }} hari capai target
                                                @elseif($key === 'videos') {{ $n($r['views']) }} views
                                                @else {{ $r['videos'] }} video @endif
                                            </p>
                                        </div>
                                    </div>
                                    <span class="text-sm font-bold whitespace-nowrap {{ $mode === 'worst' ? 'text-rose-600' : '' }}">
                                        @if($key === 'met') {{ $r['met_pct'] }}%
                                        @elseif($key === 'videos') {{ $r['videos'] }} video
                                        @else {{ $n($r['views']) }} @endif
                                    </span>
                                </li>
                            @empty
                                <li class="py-3 text-sm text-slate-400">Belum ada data.</li>
                            @endforelse
                        </ol>
                    @endforeach
                </section>
            @endforeach
        </div>

        <script>
        (function () {
            var btns = document.querySelectorAll('.lb-mode');
            btns.forEach(function (b) {
                b.addEventListener('click', function () {
                    var mode = b.getAttribute('data-mode');
                    btns.forEach(function (x) {
                        var on = x === b;
                        x.classList.toggle('bg-slate-900', on); x.classList.toggle('text-white', on);
                        x.classList.toggle('bg-white', !on);    x.classList.toggle('text-slate-600', !on);
                    });
                    document.querySelectorAll('.lb-list').forEach(function (l) {
                        l.style.display = l.getAttribute('data-mode') === mode ? '' : 'none';
                    });
                });
            });
        })();
        </script>

        <p class="text-[11px] text-slate-400 mt-3">
            Views = gabungan semua platform, pencatatan metrik terakhir per platform.
            Mode <b>Terburuk</b> hanya menampilkan pegawai yang punya hari masuk di periode ini — yang tidak pernah absen tidak dinilai.
            Pegawai 0 video ikut tampil di mode Terburuk.
        </p>
    @endif
@endsection
