@extends('layouts.app')

@section('title', 'Dashboard Servis')
@section('autorefresh', true)

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h1 class="text-xl font-bold">Antrian Kerja — {{ $role->label() }}</h1>
            <p class="text-xs text-slate-400 mt-0.5">Auto-refresh tiap 60 detik</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('service.tickets.index') }}"
               class="rounded-lg bg-white border border-slate-300 px-4 py-2 text-sm font-semibold hover:border-slate-400">
                📋 Semua Tiket
            </a>
            <a href="{{ route('service.kpi') }}"
               class="rounded-lg bg-white border border-slate-300 px-4 py-2 text-sm font-semibold hover:border-slate-400">
                📊 KPI
            </a>
            <a href="{{ route('service.tickets.create') }}"
               class="rounded-lg bg-emerald-500 text-white px-4 py-2 text-sm font-semibold hover:bg-emerald-600">
                + Unit Masuk
            </a>
        </div>
    </div>

    @if($alerts->isNotEmpty())
        <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3">
            <p class="text-sm font-bold text-rose-800 mb-2">⚠️ Perlu Perhatian</p>
            <ul class="space-y-1">
                @foreach($alerts as $a)
                    <li class="flex items-start gap-2 text-sm {{ $a['level'] === 'red' ? 'text-rose-700' : 'text-amber-700' }}">
                        <span>{{ $a['level'] === 'red' ? '🔴' : '🟡' }}</span>
                        <span>{{ $a['msg'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 font-semibold">
            ✅ Tidak ada masalah menonjol di servis.
        </div>
    @endif

    {{-- Ringkasan tiket terbuka per status --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-8 gap-2 mb-6">
        @foreach(\App\Enums\TicketStatus::openStatuses() as $status)
            <div class="bg-white rounded-xl border border-slate-200 px-3 py-2">
                <p class="text-[11px] text-slate-500 truncate">{{ $status->label() }}</p>
                <p class="text-lg font-bold">{{ $counts[$status->value] ?? 0 }}</p>
            </div>
        @endforeach
    </div>

    @foreach($queues as $label => $tickets)
        <section class="mb-6">
            <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-2">
                {{ $label }} <span class="text-slate-400 font-normal">({{ $tickets->count() }})</span>
            </h2>

            @if($tickets->isEmpty())
                <div class="bg-white rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-400">
                    Tidak ada tiket di antrian ini. 👍
                </div>
            @else
                <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($tickets as $t)
                        <a href="{{ route('service.tickets.show', $t) }}"
                           class="bg-white rounded-xl border border-slate-200 p-3 hover:border-emerald-400 hover:shadow-sm transition">
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <span class="font-mono text-xs font-semibold text-slate-500">{{ $t->ticket_number }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-medium {{ $t->status->color() }}">
                                    {{ $t->status->label() }}
                                </span>
                            </div>
                            <p class="font-semibold text-sm">{{ $t->device_brand }} {{ $t->device_model }}</p>
                            <p class="text-xs text-slate-500 truncate">{{ $t->customer_name }} · {{ $t->complaint }}</p>
                            <p class="text-[11px] text-slate-400 mt-1">
                                Masuk {{ $t->checked_in_at->translatedFormat('d M, H:i') }}
                                · {{ $t->ageDays() }} hari
                                @if($t->technician) · 🔧 {{ $t->technician->name }} @endif
                            </p>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach
@endsection