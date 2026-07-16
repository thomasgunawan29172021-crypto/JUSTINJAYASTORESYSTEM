@extends('layouts.app')

@section('title', 'Jadwal Kerja')

@section('content')
    <h1 class="text-xl font-bold mb-1">Jadwal Kerja Karyawan</h1>
    <p class="text-sm text-slate-500 mb-5">Jam masuk/pulang per hari — polanya berulang tiap minggu. Telat dihitung dari jam masuk + toleransi 5 menit.</p>

    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-500 uppercase tracking-wide bg-slate-50">
                <tr>
                    <th class="px-4 py-3">Karyawan</th>
                    <th class="px-4 py-3">Jadwal</th>
                    <th class="px-4 py-3">Mulai Dihitung</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($users as $u)
                    <tr>
                        <td class="px-4 py-3">
                            <p class="font-semibold">{{ $u->name }}</p>
                            <p class="text-[11px] text-slate-400">{{ $u->role->label() }} · {{ $u->branch?->code ?? '—' }}</p>
                        </td>
                        <td class="px-4 py-3 text-xs">
                            @if($u->workSchedule && $u->workSchedule->days->isNotEmpty())
                                {{ $u->workSchedule->summary() }}
                            @else
                                <span class="text-amber-600 font-semibold">belum diset</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            {{ $u->workSchedule?->effective_from?->translatedFormat('d M Y') ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" data-sched-open="sched-{{ $u->id }}"
                                    class="text-emerald-700 text-xs font-semibold hover:underline">Atur Jadwal</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Modal per karyawan --}}
    @foreach($users as $u)
        @php
            $byDay     = $u->workSchedule?->days->keyBy('day_of_week') ?? collect();
            $daysOrder = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 0 => 'Minggu'];
        @endphp
        <div id="sched-{{ $u->id }}" data-sched-modal
             class="hidden fixed inset-0 z-50 items-center justify-center p-4" style="background:rgba(15,23,42,.45);">
            <div class="bg-white rounded-2xl border border-slate-200 w-full max-w-lg max-h-[85vh] overflow-y-auto p-5">
                <div class="flex items-start justify-between gap-2 mb-3">
                    <h3 class="font-bold">Jadwal — {{ $u->name }}</h3>
                    <button type="button" data-sched-close class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 shrink-0">×</button>
                </div>

                <form method="POST" action="{{ route('attendance.schedules.upsert', $u) }}" class="space-y-3">
                    @csrf @method('PUT')

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Mulai berlaku <span class="font-normal text-slate-400">(kosongkan = berlaku sejak awal)</span></label>
                        <input type="date" name="effective_from"
                               value="{{ $u->workSchedule?->effective_from?->toDateString() }}"
                               class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>

                    <div class="divide-y divide-slate-100 border border-slate-200 rounded-lg">
                        @foreach($daysOrder as $dow => $label)
                            @php $day = $byDay->get($dow); $isOff = ! $day || ! $day->clock_in_time; @endphp
                            <div class="flex items-center gap-2 px-3 py-2 sched-day-row">
                                <span class="w-16 text-sm font-medium shrink-0">{{ $label }}</span>
                                <label class="flex items-center gap-1 text-xs text-slate-500 shrink-0">
                                    <input type="checkbox" name="days[{{ $dow }}][is_off]" value="1"
                                           class="rounded sched-off-toggle" @checked($isOff)>
                                    Libur
                                </label>
                                <input type="time" name="days[{{ $dow }}][clock_in_time]"
                                       value="{{ $day?->clock_in_time ? substr($day->clock_in_time, 0, 5) : '08:00' }}"
                                       class="sched-time rounded-lg border border-slate-300 px-2 py-1 text-xs flex-1 disabled:bg-slate-50 disabled:text-slate-300"
                                       {{ $isOff ? 'disabled' : '' }}>
                                <span class="text-slate-300 text-xs">–</span>
                                <input type="time" name="days[{{ $dow }}][clock_out_time]"
                                       value="{{ $day?->clock_out_time ? substr($day->clock_out_time, 0, 5) : '17:00' }}"
                                       class="sched-time rounded-lg border border-slate-300 px-2 py-1 text-xs flex-1 disabled:bg-slate-50 disabled:text-slate-300"
                                       {{ $isOff ? 'disabled' : '' }}>
                            </div>
                        @endforeach
                    </div>

                    <button class="w-full rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-bold py-2">Simpan Jadwal</button>
                </form>
            </div>
        </div>
    @endforeach

    <script>
    (function () {
        function close() {
            document.querySelectorAll('[data-sched-modal]').forEach(function (m) {
                m.classList.add('hidden'); m.classList.remove('flex');
            });
        }
        document.querySelectorAll('[data-sched-open]').forEach(function (b) {
            b.addEventListener('click', function () {
                close();
                var m = document.getElementById(b.getAttribute('data-sched-open'));
                if (m) { m.classList.remove('hidden'); m.classList.add('flex'); }
            });
        });
        document.querySelectorAll('[data-sched-close]').forEach(function (b) { b.addEventListener('click', close); });
        document.querySelectorAll('[data-sched-modal]').forEach(function (m) {
            m.addEventListener('click', function (e) { if (e.target === m) close(); });
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

        document.querySelectorAll('.sched-off-toggle').forEach(function (cb) {
            var row = cb.closest('.sched-day-row');
            function apply() { row.querySelectorAll('.sched-time').forEach(function (i) { i.disabled = cb.checked; }); }
            cb.addEventListener('change', apply);
        });
    })();
    </script>
@endsection