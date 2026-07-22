@extends('layouts.app')

@section('title', 'Rekap '.$user->name)

@php use App\Services\AttendanceStatusResolver as R; @endphp

@section('content')
    @unless($self)
        <a href="{{ route('attendance.recap', ['month' => $month->format('Y-m')]) }}"
           class="text-sm text-slate-500 hover:underline">← Rekap semua karyawan</a>
    @endunless

    <div class="flex flex-wrap items-center justify-between gap-2 mt-1 mb-4">
        <h1 class="text-xl font-bold">
            {{ $self ? 'Rekap Saya' : 'Rekap — '.$user->name }} · {{ $month->translatedFormat('F Y') }}
        </h1>
        <form method="GET">
            <input type="month" name="month" value="{{ $month->format('Y-m') }}"
                   onchange="this.form.submit()"
                   class="rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
        </form>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-5">
        @foreach([
            ['Hadir + Telat', ($recap['counts'][R::HADIR] ?? 0) + ($recap['counts'][R::TELAT] ?? 0)],
            ['Alpha', $recap['counts'][R::ALPHA] ?? 0],
            ['Hari dipotong', $recap['deducted_days']],
            ['Total jam kerja', $recap['worked_hours'].' j'],
        ] as [$label, $value])
            <div class="bg-white rounded-xl border border-slate-200 px-4 py-3">
                <p class="text-xs text-slate-500">{{ $label }}</p>
                <p class="text-lg font-bold">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    {{-- Kartu per-hari. Sebelumnya satu baris flex-wrap yang berantakan di HP —
         sekarang tiap hari punya struktur tetap: tanggal + status di atas, jam kerja
         di tengah, aksi (foto/minta ulang/koreksi) di bar bawah yang rapi. --}}
    <div class="space-y-2">
        @foreach($days as $d)
            @php
                $att       = $d['attendance'];
                $status    = $d['status'];
                $isToday   = $d['date']->isToday();
                $isWeekend = $d['date']->isWeekend();
            @endphp
            <div class="bg-white rounded-xl border {{ $isToday ? 'border-emerald-300 ring-1 ring-emerald-100' : 'border-slate-200' }} px-4 py-3">
                {{-- Baris 1: tanggal + badge status --}}
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="text-sm font-semibold {{ $isWeekend ? 'text-slate-400' : 'text-slate-700' }}">
                            {{ $d['date']->translatedFormat('D, d M') }}
                        </span>
                        @if($isToday)
                            <span class="px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 text-[10px] font-medium">hari ini</span>
                        @endif
                    </div>
                    @if($status)
                        <span class="shrink-0 px-2.5 py-0.5 rounded-full text-[11px] font-semibold
                            @if($status === R::HADIR) bg-emerald-100 text-emerald-700
                            @elseif($status === R::TELAT) bg-amber-100 text-amber-700
                            @elseif(R::isDeducted($status)) bg-rose-100 text-rose-700
                            @else bg-slate-100 text-slate-600 @endif">
                            {{ R::label($status) }}
                        </span>
                    @endif
                </div>

                {{-- Baris 2: jam kerja --}}
                <div class="mt-1.5 text-sm">
                    @if($att)
                        <span class="inline-flex items-center gap-1.5 text-slate-700">
                            <span class="text-slate-400">🕐</span>
                            <span class="font-semibold tabular-nums">{{ $att->clock_in_at->format('H:i') }}</span>
                            <span class="text-slate-300">–</span>
                            <span class="font-semibold tabular-nums">{{ $att->clock_out_at?->format('H:i') ?? '…' }}</span>
                        </span>
                        @if($att->auto_closed)
                            <span class="ml-1 px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 text-[10px]">auto-close</span>
                        @endif
                    @else
                        <span class="text-slate-300 text-sm">— tidak ada absen</span>
                    @endif
                </div>

                {{-- Baris 3 (khusus CEO/Kepala Toko): foto, minta ulang, koreksi --}}
                @unless($self)
                    @if($att)
                        <div class="mt-2.5 pt-2.5 border-t border-slate-100 flex flex-wrap items-center gap-2 text-[11px]">
                            @foreach([['in', 'clock_in_photo', 'retake_in_requested', 'masuk'], ['out', 'clock_out_photo', 'retake_out_requested', 'pulang']] as [$rtType, $rtField, $rtFlag, $rtLabel])
                                @if($att->{$rtField})
                                    {{-- Foto + form minta ulang jadi satu chip, gak lagi dipisah tanda | --}}
                                    <div class="inline-flex flex-wrap items-center gap-1.5 rounded-lg bg-slate-50 border border-slate-200 px-2 py-1">
                                        <a href="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.default'))->url($att->{$rtField}) }}"
                                           target="_blank" class="inline-flex items-center gap-1 text-emerald-700 font-medium hover:underline">📷 {{ $rtLabel }}</a>
                                        @if(str_contains($att->{$rtField}, '-retake-'))
                                            <span class="px-1 rounded bg-amber-100 text-amber-700 text-[10px]">foto ulang</span>
                                        @endif
                                        <form method="POST" action="{{ route('attendance.corrections.retake', $att) }}" class="inline-flex items-center gap-1"
                                              onsubmit="return confirm('Hapus foto {{ $rtLabel }} & minta selfie ulang?')">
                                            @csrf
                                            <input type="hidden" name="type" value="{{ $rtType }}">
                                            <input type="text" name="reason" required maxlength="300" placeholder="alasan…"
                                                   class="rounded border border-slate-300 px-1.5 py-0.5 text-[11px] w-24">
                                            <button class="text-rose-500 hover:underline whitespace-nowrap">🔄 minta ulang</button>
                                        </form>
                                    </div>
                                @endif
                            @endforeach

                            @if($att->retake_in_requested || $att->retake_out_requested)
                                <span class="px-1.5 py-0.5 rounded bg-rose-100 text-rose-700 text-[10px]">menunggu foto ulang</span>
                            @endif

                            <div class="ml-auto inline-flex items-center gap-2">
                                @if($att->corrections->isNotEmpty())
                                    <span class="px-1.5 py-0.5 rounded bg-violet-100 text-violet-700 text-[10px]">dikoreksi</span>
                                @endif
                                <a href="{{ route('attendance.corrections.edit', $att) }}"
                                   class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 text-emerald-700 font-semibold px-2.5 py-1 hover:bg-emerald-100">✏️ Koreksi</a>
                            </div>
                        </div>
                    @elseif(! $isToday)
                        <div class="mt-2.5 pt-2.5 border-t border-slate-100 text-[11px]">
                            <a href="{{ route('attendance.corrections.create', ['user' => $user, 'date' => $d['date']->toDateString()]) }}"
                               class="inline-flex items-center gap-1 text-slate-500 hover:text-emerald-700 hover:underline">+ Input manual</a>
                        </div>
                    @endif
                @endunless
            </div>
        @endforeach
    </div>
@endsection