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
    <p class="text-sm text-slate-500 mb-4">Deadline manual + otomatis (cuti disetujui, video due, diskon berakhir) — semua di sini.</p>

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
                            $inMonth = $day->month === $month->month;
                            $isToday = $day->isToday();
                            $dayEvents = $events[$day->toDateString()] ?? collect();
                        @endphp
                        <div class="min-h-24 p-1.5 {{ $inMonth ? '' : 'bg-slate-50/70' }} {{ $isToday ? 'ring-2 ring-inset ring-emerald-400' : '' }}">
                            <p class="text-xs font-semibold {{ $inMonth ? 'text-slate-700' : 'text-slate-300' }} mb-1">{{ $day->day }}</p>
                            @foreach($dayEvents as $ev)
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
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>

    <p class="text-[11px] text-slate-400 mt-2">
        🏖 cuti/izin dan ⏰ video due dan 🏷 diskon muncul otomatis dari data — tidak bisa dihapus dari sini (selesaikan di modulnya).
        Event manual (✕ saat hover) hanya bisa dikelola CEO.
    </p>
@endsection