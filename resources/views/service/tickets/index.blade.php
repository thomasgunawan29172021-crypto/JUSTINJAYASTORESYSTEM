@extends('layouts.app')

@section('title', 'Semua Tiket')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h1 class="text-xl font-bold">Semua Tiket Servis</h1>
        <a href="{{ route('service.tickets.create') }}"
           class="rounded-lg bg-emerald-500 text-white px-4 py-2 text-sm font-semibold hover:bg-emerald-600">
            + Unit Masuk
        </a>
    </div>

    <form method="GET" class="flex flex-wrap gap-2 mb-4">
        <input type="text" name="q" value="{{ request('q') }}"
               placeholder="Cari no. servis / nama / HP / model..."
               class="flex-1 min-w-[220px] rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
        <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
            <option value="">Semua status</option>
            @foreach($statuses as $s)
                <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
        <button class="rounded-lg bg-slate-900 text-white px-4 py-2 text-sm font-semibold">Cari</button>
    </form>

    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-500 uppercase tracking-wide bg-slate-50">
                <tr>
                    <th class="px-4 py-3">No. Servis</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Unit</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Teknisi</th>
                    <th class="px-4 py-3">Masuk</th>
                    <th class="px-4 py-3">Keluar</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($tickets as $t)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('service.tickets.show', $t) }}"
                               class="font-mono text-xs font-bold text-emerald-700 hover:underline">
                                {{ $t->ticket_number }}
                            </a>
                        </td>
                        <td class="px-4 py-3">{{ $t->customer_name }}</td>
                        <td class="px-4 py-3">{{ $t->device_brand }} {{ $t->device_model }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-medium {{ $t->status->color() }}">
                                {{ $t->status->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ $t->technician?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs">{{ $t->checked_in_at->format('d/m/y H:i') }}</td>
                        <td class="px-4 py-3 text-xs">{{ $t->checked_out_at?->format('d/m/y H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-slate-400">
                            Belum ada tiket.
                            <a href="{{ route('service.tickets.create') }}" class="text-emerald-600 font-semibold hover:underline">Buat tiket pertama →</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $tickets->links() }}</div>
@endsection