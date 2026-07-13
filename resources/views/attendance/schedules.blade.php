@extends('layouts.app')

@section('title', 'Jadwal Kerja')

@section('content')
    <h1 class="text-xl font-bold mb-1">Jadwal Kerja Karyawan</h1>
    <p class="text-sm text-slate-500 mb-5">Jam masuk/pulang & hari off per orang. Telat dihitung dari jam masuk + toleransi 5 menit.</p>

    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-500 uppercase tracking-wide bg-slate-50">
                <tr>
                    <th class="px-4 py-3">Karyawan</th>
                    <th class="px-4 py-3">Masuk</th>
                    <th class="px-4 py-3">Pulang</th>
                    <th class="px-4 py-3">Hari Off</th>
                    <th class="px-4 py-3">Mulai Dihitung</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($users as $u)
                    <tr>
                        <td class="px-4 py-3">
                            <p class="font-semibold">{{ $u->name }}</p>
                            <p class="text-[11px] text-slate-400">{{ $u->role->label() }} · {{ $u->branch?->code ?? '—' }}</p>
                        </td>
                        <form method="POST" action="{{ route('attendance.schedules.upsert', $u) }}">
                            @csrf @method('PUT')
                            <td class="px-4 py-3">
                                <input type="time" name="clock_in_time"
                                       value="{{ $u->workSchedule ? substr($u->workSchedule->clock_in_time, 0, 5) : '08:00' }}"
                                       class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm" required>
                            </td>
                            <td class="px-4 py-3">
                                <input type="time" name="clock_out_time"
                                       value="{{ $u->workSchedule ? substr($u->workSchedule->clock_out_time, 0, 5) : '17:00' }}"
                                       class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm" required>
                            </td>
                            <td class="px-4 py-3">
                                <select name="off_day" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm bg-white" required>
                                    @foreach(\App\Models\WorkSchedule::DAYS as $num => $day)
                                        <option value="{{ $num }}" @selected($u->workSchedule?->off_day === $num)>{{ $day }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-3">
                                <input type="date" name="effective_from"
                                       value="{{ $u->workSchedule?->effective_from?->toDateString() }}"
                                       class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                            </td>
                            <td class="px-4 py-3">
                                <button class="rounded-lg bg-slate-900 text-white text-xs font-semibold px-3 py-1.5">Simpan</button>
                                @unless($u->workSchedule)
                                    <span class="ml-1 text-[10px] text-amber-600 font-semibold">belum diset</span>
                                @endunless
                            </td>
                        </form>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection