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

    <div class="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100">
        @foreach($days as $d)
            <div class="px-4 py-2.5 flex flex-wrap items-center justify-between gap-2 text-sm">
                <span class="w-32 text-slate-600">{{ $d['date']->translatedFormat('D, d M') }}</span>
                <span class="flex-1">
                    @if($d['attendance'])
                        {{ $d['attendance']->clock_in_at->format('H:i') }}
                        – {{ $d['attendance']->clock_out_at?->format('H:i') ?? '…' }}
                        @if($d['attendance']->auto_closed)
                            <span class="px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 text-[10px]">auto-close</span>
                        @endif
                        @unless($self)
                            @foreach([['in', 'clock_in_photo', 'retake_in_requested', 'masuk'], ['out', 'clock_out_photo', 'retake_out_requested', 'pulang']] as [$rtType, $rtField, $rtFlag, $rtLabel])
                                @if($d['attendance']->{$rtField})
                                    <a href="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.default'))->url($d['attendance']->{$rtField}) }}"
                                       target="_blank" class="text-[11px] text-emerald-700 hover:underline ml-1">📷 {{ $rtLabel }}</a>
                                    @if(str_contains($d['attendance']->{$rtField}, '-retake-'))
                                        <span class="px-1 rounded bg-amber-100 text-amber-700 text-[10px]">foto ulang</span>
                                    @endif
                                    <form method="POST" action="{{ route('attendance.corrections.retake', $d['attendance']) }}" class="inline"
                                          onsubmit="return confirm('Hapus foto {{ $rtLabel }} & minta selfie ulang?')">
                                        @csrf
                                        <input type="hidden" name="type" value="{{ $rtType }}">
                                        <input type="text" name="reason" required maxlength="300" placeholder="alasan…"
                                               class="rounded border border-slate-300 px-1.5 py-0.5 text-[11px] w-28">
                                        <button class="text-[11px] text-rose-500 hover:underline">🔄 minta ulang</button>
                                        |
                                    </form>
                                @endif
                            @endforeach

                            @if($d['attendance']->retake_in_requested || $d['attendance']->retake_out_requested)
                                <span class="px-1.5 py-0.5 rounded bg-rose-100 text-rose-700 text-[10px]">menunggu foto ulang</span>
                            @endif
                        @endunless
                    @else
                        <span class="text-slate-300">—</span>
                    @endif
                </span>
                @if($d['status'])
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-medium
                        {{ R::isDeducted($d['status']) ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-600' }}">
                        {{ R::label($d['status']) }}
                    </span>
                @endif
                @unless($self)
                    @if($d['attendance'])
                        @if($d['attendance']->corrections->isNotEmpty())
                            <span class="px-1.5 py-0.5 rounded bg-violet-100 text-violet-700 text-[10px]">dikoreksi</span>
                        @endif
                        <a href="{{ route('attendance.corrections.edit', $d['attendance']) }}"
                           class="text-xs text-emerald-700 hover:underline">Koreksi</a>
                    @elseif(! $d['date']->isToday())
                        <a href="{{ route('attendance.corrections.create', ['user' => $user, 'date' => $d['date']->toDateString()]) }}"
                           class="text-xs text-slate-400 hover:underline">+ Manual</a>
                    @endif
                @endunless
            </div>
        @endforeach
    </div>
@endsection