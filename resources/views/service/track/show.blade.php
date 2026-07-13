@extends('layouts.public')

@section('title', 'Status '.$ticket->ticket_number)

@php
    $rp = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.');
@endphp

@section('content')
    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
        <p class="font-mono text-xs text-slate-400">{{ $ticket->ticket_number }}</p>
        <h1 class="text-lg font-bold mt-0.5">{{ $ticket->device_brand }} {{ $ticket->device_model }}</h1>
        <p class="text-sm text-slate-500 mt-0.5">{{ $ticket->complaint }}</p>

        {{-- Kartu status + keterangan kontekstual per tahap --}}
        <div class="mt-3 rounded-xl px-4 py-3 text-sm font-semibold {{ $ticket->status->color() }}">
            {{ $ticket->status->publicLabel() }}

            @if($ticket->status === \App\Enums\TicketStatus::MenungguKonfirmasi && $ticket->estimated_cost !== null)
                <span class="block text-xs font-normal mt-1">
                    Estimasi biaya {{ $rp($ticket->estimated_cost) }} — balas WA kami untuk konfirmasi.
                </span>

            @elseif(in_array($ticket->status, [\App\Enums\TicketStatus::Diterima, \App\Enums\TicketStatus::Diagnosa]) && $ticket->estimated_done_at)
                <span class="block text-xs font-normal mt-1">
                    Estimasi selesai: {{ $ticket->estimated_done_at->translatedFormat('d M Y') }}
                </span>

            @elseif($ticket->status === \App\Enums\TicketStatus::SiapDiambil)
                <span class="block text-xs font-normal mt-1">
                    Silakan diambil di {{ $ticket->branch->name }}{{ $ticket->branch->address ? ' — '.$ticket->branch->address : '' }}.
                    @if($ticket->approved_cost !== null) Total: {{ $rp($ticket->approved_cost) }}. @endif
                </span>

            @elseif($ticket->status === \App\Enums\TicketStatus::Selesai && $ticket->warranty_until)
                <span class="block text-xs font-normal mt-1">
                    Garansi servis berlaku s/d {{ $ticket->warranty_until->translatedFormat('d M Y') }}.
                </span>

            @elseif($ticket->status === \App\Enums\TicketStatus::Dibatalkan && $ticket->cancel_reason)
                <span class="block text-xs font-normal mt-1">
                    Alasan: {{ $ticket->cancel_reason }}
                </span>
            @endif
        </div>

        <dl class="text-sm mt-4 space-y-2">
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Cabang</dt><dd class="font-semibold text-right">{{ $ticket->branch->name }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Tanggal masuk</dt><dd>{{ $ticket->checked_in_at->translatedFormat('d M Y') }}</dd></div>
        </dl>

        {{-- Timeline: terbaru di atas. Titik status aktif berdenyut HANYA kalau tiket masih berjalan --}}
        <div class="mt-5 pt-4 border-t border-slate-100">
            <p class="text-xs font-semibold tracking-widest text-slate-500 uppercase mb-4">Riwayat</p>
            <ol>
                @foreach($ticket->histories->sortByDesc('created_at') as $h)
                    @php $isCurrent = $loop->first && $ticket->isOpen(); @endphp
                    <li class="flex gap-3">
                        <div class="flex flex-col items-center">
                            @if($isCurrent)
                                <span class="relative flex h-3.5 w-3.5 shrink-0">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-emerald-500"></span>
                                </span>
                            @else
                                <span class="block w-3.5 h-3.5 rounded-full bg-slate-300 shrink-0"></span>
                            @endif

                            @unless($loop->last)
                                <span class="w-0.5 flex-1 bg-slate-200 my-1"></span>
                            @endunless
                        </div>

                        <div class="pb-5">
                            <p class="text-sm {{ $isCurrent ? 'font-bold text-emerald-700' : 'font-medium text-slate-600' }}">
                                {{ $h->to_status->publicLabel() }}
                            </p>
                            <p class="text-xs text-slate-400">{{ $h->created_at->translatedFormat('d M Y, H:i') }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>

        @if($ticket->branch->phone)
            <p class="text-xs text-slate-400 mt-5 pt-4 border-t border-slate-100">
                Ada pertanyaan? Hubungi {{ $ticket->branch->name }}: <b>{{ $ticket->branch->phone }}</b>
            </p>
        @endif
    </div>
@endsection