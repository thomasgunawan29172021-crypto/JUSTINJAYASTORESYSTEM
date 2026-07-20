@extends('layouts.app')

@section('title', 'Klaim Retur')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-2 mb-5">
        <h1 class="text-xl font-bold">Klaim Retur / Garansi</h1>
        <a href="{{ route('warranty.claims.create') }}"
           class="rounded-xl bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-bold px-4 py-2">+ Klaim Baru</a>
    </div>

    <form method="GET" class="mb-5 flex flex-wrap items-center gap-2">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari no. retur / nama / HP / IMEI…"
               class="flex-1 min-w-52 max-w-md rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
        <select name="status" onchange="this.form.submit()"
                class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
            <option value="">Semua status</option>
            @foreach($statuses as $s)
                <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
        <button class="rounded-xl bg-slate-900 text-white text-sm font-semibold px-4 py-2.5">Cari</button>
        @if(request()->hasAny(['q','status']))
            <a href="{{ route('warranty.claims.index') }}" class="text-xs font-semibold text-rose-500 hover:underline">✕ reset</a>
        @endif
    </form>

    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-500 uppercase tracking-wide bg-slate-50">
                <tr>
                    <th class="px-4 py-3">No. Retur</th>
                    <th class="px-4 py-3">Pelanggan</th>
                    <th class="px-4 py-3">Produk</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Vendor</th>
                    <th class="px-4 py-3">Macet</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($claims as $c)
                    @php $sla = $c->slaLevel(); @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-mono text-xs font-semibold whitespace-nowrap">{{ $c->claim_number }}</td>
                        <td class="px-4 py-3">
                            {{ $c->customer_name }}
                            <span class="block text-[11px] text-slate-400">{{ $c->customer_phone }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $c->product->name }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-medium whitespace-nowrap
                                @if($c->status->value === 'selesai') bg-emerald-100 text-emerald-800
                                @elseif($c->status->value === 'batal') bg-slate-200 text-slate-600
                                @elseif($c->outcome === 'ditolak') bg-rose-100 text-rose-800
                                @else bg-sky-100 text-sky-800 @endif">
                                {{ $c->status->label() }}
                            </span>
                            @if($c->outcome)
                                <span class="block mt-0.5 text-[10px] font-bold {{ $c->outcome === 'diterima' ? 'text-emerald-600' : 'text-rose-600' }}">
                                    {{ strtoupper($c->outcome) }} vendor
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ $c->vendor?->name ?? '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if($c->status->isFinal())
                                <span class="text-slate-300 text-xs">—</span>
                            @else
                                {{-- RAG SLA (keputusan Thomas): ≥7 hari kuning, ≥14 merah.
                                     Follow-up me-reset hitungan — makanya "macet", bukan "umur". --}}
                                <span class="inline-flex items-center gap-1 text-xs font-semibold
                                    {{ $sla === 'critical' ? 'text-rose-600' : ($sla === 'warning' ? 'text-amber-600' : 'text-slate-400') }}">
                                    <span class="w-2 h-2 rounded-full {{ $sla === 'critical' ? 'bg-rose-500' : ($sla === 'warning' ? 'bg-amber-400' : 'bg-emerald-400') }}"></span>
                                    {{ $c->idleDays() }} hr
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('warranty.claims.show', $c) }}" class="text-emerald-700 text-xs font-semibold hover:underline">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">Belum ada klaim.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($claims->hasPages())
        <div class="mt-3">{{ $claims->links() }}</div>
    @endif
@endsection
