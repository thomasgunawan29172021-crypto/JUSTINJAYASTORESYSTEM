@extends('layouts.app')

@section('title', 'Kalender')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
        <h1 class="text-xl font-bold">📅 Kalender — {{ $month->translatedFormat('F Y') }}</h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('calendar.index', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}"
               class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold hover:border-slate-400">‹</a>
            <a href="{{ route('calendar.index') }}"
               class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold hover:border-slate-400">Hari ini</a>
            <a href="{{ route('calendar.index', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}"
               class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold hover:border-slate-400">›</a>
        </div>
    </div>
    <p class="text-sm text-slate-500 mb-4">Deadline manual + otomatis (cuti, video due, diskon berakhir, tugas nginap) — klik tanggal untuk detail.</p>

    {{-- Form tambah (CEO) --}}
    @if($isCeo)
        <form method="POST" action="{{ route('calendar.store') }}"
              class="mb-4 bg-white rounded-xl border border-slate-200 p-3 flex flex-wrap items-end gap-2">
            @csrf
            <div class="flex-1 min-w-40">
                <label class="block text-[11px] font-semibold text-slate-500 mb-0.5">Judul *</label>
                <input type="text" name="title" required maxlength="150" placeholder="Deadline: Payroll…"
                       class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-0.5">Tanggal *</label>
                <input type="date" name="date" required class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-0.5">Sampai <span class="font-normal">(opsional)</span></label>
                <input type="date" name="date_end" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-0.5">Warna</label>
                <select name="color" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm bg-white">
                    @foreach(array_keys(\App\Models\CalendarEvent::COLORS) as $c)
                        <option value="{{ $c }}" @selected($c === 'rose')>{{ ucfirst($c) }}</option>
                    @endforeach
                </select>
            </div>
            <button class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-bold px-4 py-2">+ Tambah</button>
        </form>
    @endif

    {{-- Grid kalender --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <div class="min-w-[900px]">
            <div class="grid grid-cols-7 bg-slate-50 text-[11px] font-semibold text-slate-500 uppercase tracking-wide">
                @foreach(['Sen','Sel','Rab','Kam','Jum','Sab','Min'] as $d)
                    <div class="px-2 py-2 border-b border-slate-200">{{ $d }}</div>
                @endforeach
            </div>
            @foreach($weeks as $week)
                <div class="grid grid-cols-7 divide-x divide-slate-100 border-b border-slate-100">
                    @foreach($week as $day)
                        @php
                            $inMonth   = $day->month === $month->month;
                            $isToday   = $day->isToday();
                            $dayEvents = $events[$day->toDateString()] ?? collect();
                        @endphp
                        <div class="min-h-24 p-1.5 {{ $inMonth ? '' : 'bg-slate-50/70' }} {{ $isToday ? 'ring-2 ring-inset ring-emerald-400' : '' }}">
                            <div class="flex items-center justify-between mb-1">
                                @if($dayEvents->isNotEmpty())
                                    <button type="button" data-cal-open="cal-{{ $day->toDateString() }}"
                                            title="Lihat detail hari ini"
                                            class="text-xs font-semibold {{ $inMonth ? 'text-slate-700' : 'text-slate-300' }} hover:text-emerald-600 hover:underline">
                                        {{ $day->day }}
                                    </button>
                                    <span class="text-[9px] text-slate-300">{{ $dayEvents->count() }}</span>
                                @else
                                    <p class="text-xs font-semibold {{ $inMonth ? 'text-slate-700' : 'text-slate-300' }}">{{ $day->day }}</p>
                                @endif
                            </div>

                            @foreach($dayEvents as $ev)
                                @if($isCeo && $ev['url'])
                                    <a href="{{ $ev['url'] }}" title="{{ $ev['label'] }} — klik untuk buka halamannya"
                                       class="flex items-start gap-1 {{ $ev['color'] }} text-white text-[10px] leading-tight rounded px-1.5 py-0.5 mb-0.5 hover:opacity-90">
                                        <span class="flex-1 truncate">{{ $ev['label'] }}</span>
                                    </a>
                                @else
                                    <div class="group flex items-start gap-1 {{ $ev['color'] }} text-white text-[10px] leading-tight rounded px-1.5 py-0.5 mb-0.5">
                                        <span class="flex-1 truncate" title="{{ $ev['label'] }}">{{ $ev['label'] }}</span>
                                        @if($isCeo && $ev['manual'])
                                            <form method="POST" action="{{ route('calendar.destroy', $ev['id']) }}" class="hidden group-hover:block shrink-0"
                                                  onsubmit="return confirm('Hapus event ini?')">
                                                @csrf @method('DELETE')
                                                <button class="opacity-80 hover:opacity-100">✕</button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>

    {{-- Modal detail per tanggal — hanya dirender untuk hari yang ada agendanya --}}
    @foreach($weeks as $week)
        @foreach($week as $day)
            @php $dayEvents = $events[$day->toDateString()] ?? collect(); @endphp
            @if($dayEvents->isNotEmpty())
                <div id="cal-{{ $day->toDateString() }}" data-cal-modal
                     class="hidden fixed inset-0 z-50 items-center justify-center p-4" style="background:rgba(15,23,42,.45);">
                    <div class="bg-white rounded-2xl border border-slate-200 w-full max-w-md max-h-[80vh] overflow-y-auto p-5">
                        <div class="flex items-start justify-between gap-2 mb-3">
                            <div>
                                <h3 class="font-bold">{{ $day->translatedFormat('l, d F Y') }}</h3>
                                <p class="text-xs text-slate-400">{{ $dayEvents->count() }} agenda</p>
                            </div>
                            <button type="button" data-cal-close class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 shrink-0">×</button>
                        </div>

                        <div class="space-y-2">
                            @foreach($dayEvents as $ev)
                                <div class="border border-slate-200 rounded-lg p-2.5 flex items-start gap-2">
                                    <span class="w-2 h-2 rounded-full {{ $ev['color'] }} mt-1.5 shrink-0"></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold">{{ $ev['label'] }}</p>
                                        @if($ev['time'])
                                            <p class="text-xs text-slate-500">🕐 pukul {{ $ev['time'] }}</p>
                                        @endif
                                        @if($ev['note'])
                                            <p class="text-[11px] text-slate-400 mt-0.5">{{ $ev['note'] }}</p>
                                        @endif
                                        @if($isCeo && $ev['url'])
                                            <a href="{{ $ev['url'] }}" class="inline-block mt-1 text-[11px] font-semibold text-emerald-700 hover:underline">Buka halaman →</a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    @endforeach

    <script>
    (function () {
        function close() {
            document.querySelectorAll('[data-cal-modal]').forEach(function (m) {
                m.classList.add('hidden'); m.classList.remove('flex');
            });
        }
        document.querySelectorAll('[data-cal-open]').forEach(function (b) {
            b.addEventListener('click', function () {
                close();
                var m = document.getElementById(b.getAttribute('data-cal-open'));
                if (m) { m.classList.remove('hidden'); m.classList.add('flex'); }
            });
        });
        document.querySelectorAll('[data-cal-close]').forEach(function (b) { b.addEventListener('click', close); });
        document.querySelectorAll('[data-cal-modal]').forEach(function (m) {
            m.addEventListener('click', function (e) { if (e.target === m) close(); });
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    })();
    </script>

    <p class="text-[11px] text-slate-400 mt-2">
        Klik <b>angka tanggal</b> untuk lihat detail agenda hari itu (jam, catatan, link).
        🏖 cuti · ⏰ video due · 🏷 diskon · 📌 tugas nginap muncul otomatis dari data — selesaikan di modulnya masing-masing.
        @if($isCeo) Event berwarna bisa diklik langsung ke halamannya; event manual punya ✕ saat hover. @endif
    </p>
@endsection