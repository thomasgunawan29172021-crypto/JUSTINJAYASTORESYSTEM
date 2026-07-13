@extends('layouts.app')

@section('title', 'Koreksi Absen')

@section('content')
    <a href="{{ route('attendance.recap.show', ['user' => $user->id, 'month' => $date->format('Y-m')]) }}"
       class="text-sm text-slate-500 hover:underline">← Rekap {{ $user->name }}</a>

    <h1 class="text-xl font-bold mt-2 mb-1">
        {{ $mode === 'edit' ? 'Koreksi Absen' : 'Input Absen Manual' }} — {{ $user->name }}
    </h1>
    <p class="text-sm text-slate-500 mb-5">
        {{ $date->translatedFormat('l, d M Y') }} · Setiap perubahan tercatat permanen di audit trail (siapa, kapan, alasan).
    </p>

    <div class="grid lg:grid-cols-2 gap-4">
        <form method="POST"
              action="{{ $mode === 'edit'
                    ? route('attendance.corrections.update', $attendance)
                    : route('attendance.corrections.store', $user) }}"
              class="bg-white rounded-xl border border-slate-200 p-5 space-y-4 self-start">
            @csrf
            @if($mode === 'edit') @method('PUT') @endif

            @if($mode === 'create')
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Tanggal *</label>
                    <input type="date" name="work_date" value="{{ old('work_date', $date->toDateString()) }}" required
                           max="{{ today()->toDateString() }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            @endif

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Jam masuk *</label>
                    <input type="time" name="clock_in" required
                           value="{{ old('clock_in', $attendance?->clock_in_at?->format('H:i')) }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Jam pulang</label>
                    <input type="time" name="clock_out"
                           value="{{ old('clock_out', $attendance?->clock_out_at?->format('H:i')) }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Alasan koreksi *</label>
                <textarea name="reason" rows="2" required
                          placeholder="Contoh: karyawan lupa clock-out, konfirmasi via kepala toko"
                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('reason') }}</textarea>
            </div>

            <button class="w-full rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-2.5 text-sm">
                Simpan & Catat di Audit
            </button>
        </form>

        @if($mode === 'edit' && $attendance->corrections->isNotEmpty())
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-3">Riwayat Koreksi</h2>
                <div class="space-y-3">
                    @foreach($attendance->corrections as $c)
                        <div class="rounded-lg border border-slate-200 px-4 py-3 text-sm">
                            <p class="font-semibold">
                                {{ $c->corrector?->name ?? '—' }}
                                <span class="text-slate-400 font-normal">· {{ $c->created_at->format('d/m/Y H:i') }}</span>
                            </p>
                            <p class="text-xs text-slate-600 mt-0.5">“{{ $c->reason }}”</p>
                            <p class="text-[11px] text-slate-400 mt-1 font-mono">
                                @if($c->before === null)
                                    dibuat manual
                                @else
                                    masuk: {{ \Illuminate\Support\Carbon::parse($c->before['clock_in_at'])->format('H:i') }}
                                    → {{ \Illuminate\Support\Carbon::parse($c->after['clock_in_at'])->format('H:i') }}
                                    · pulang: {{ $c->before['clock_out_at'] ? \Illuminate\Support\Carbon::parse($c->before['clock_out_at'])->format('H:i') : '—' }}
                                    → {{ $c->after['clock_out_at'] ? \Illuminate\Support\Carbon::parse($c->after['clock_out_at'])->format('H:i') : '—' }}
                                @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endsection
