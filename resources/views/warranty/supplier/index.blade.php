@extends('layouts.app')

@section('title', 'Klaim Supplier')

@section('content')
    <h1 class="text-xl font-bold">Klaim ke Supplier</h1>
    <p class="text-sm text-slate-500 mt-1 mb-5">
        Jalur internal — <b>tidak pernah muncul di halaman lacak pelanggan</b>.
        Ini tempat mengejar penggantian dari distributor atas barang yang sudah kita talangi.
    </p>

    {{-- ===== ANTREAN: unit siap diklaim tapi belum masuk pengiriman ===== --}}
    <div class="bg-white rounded-xl border border-slate-200 p-4 mb-5">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <p class="text-xs font-semibold text-slate-500 uppercase">
                Belum diklaim ke supplier
                <span class="ml-1 px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ $queue->count() }}</span>
            </p>
            <p class="text-[11px] text-slate-400">Selama unit masih di sini, duitnya belum dikejar.</p>
        </div>

        @if($queue->isEmpty())
            <p class="py-6 text-center text-sm text-slate-400">Bersih — tidak ada unit yang menunggu diklaim.</p>
        @else
            <form method="POST" action="{{ route('warranty.supplier.store') }}">
                @csrf
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs text-slate-500 uppercase bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 w-8"></th>
                                <th class="px-3 py-2">No. Retur</th>
                                <th class="px-3 py-2">Produk</th>
                                <th class="px-3 py-2">Vendor brand</th>
                                <th class="px-3 py-2">Cabang</th>
                                <th class="px-3 py-2">Menunggu</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($queue as $c)
                                @php
                                    // Umur antrean pakai takaran SLA yang sama dengan klaim retur:
                                    // ≥7 hari kuning, ≥14 merah. Unit yang lama nganggur di sini
                                    // itu klaim yang pelan-pelan hangus.
                                    $days = (int) $c->supplier_queued_at->diffInDays(now());
                                @endphp
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 py-2">
                                        <input type="checkbox" name="claim_ids[]" value="{{ $c->id }}" class="rounded accent-emerald-500">
                                    </td>
                                    <td class="px-3 py-2 font-mono text-xs whitespace-nowrap">
                                        <a href="{{ route('warranty.claims.show', $c) }}" class="text-emerald-700 font-semibold hover:underline">{{ $c->claim_number }}</a>
                                    </td>
                                    <td class="px-3 py-2">{{ $c->product->name }}</td>
                                    <td class="px-3 py-2 text-xs text-slate-500">{{ $c->product->brand?->warrantyVendor?->name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs text-slate-500">{{ $c->branch->name }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1 text-xs font-semibold
                                            {{ $days >= 14 ? 'text-rose-600' : ($days >= 7 ? 'text-amber-600' : 'text-slate-400') }}">
                                            <span class="w-2 h-2 rounded-full {{ $days >= 14 ? 'bg-rose-500' : ($days >= 7 ? 'bg-amber-400' : 'bg-emerald-400') }}"></span>
                                            {{ $days }} hr
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 pt-3 border-t border-slate-100 flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-44">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Kirim ke vendor *</label>
                        <select name="vendor_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
                            <option value="">— pilih vendor —</option>
                            @foreach($vendors as $v)
                                <option value="{{ $v->id }}">{{ $v->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1 min-w-44">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Catatan (opsional)</label>
                        <input type="text" name="note" maxlength="500"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <button class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-bold px-4 py-2">
                        Buat Pengiriman
                    </button>
                </div>
                <p class="text-[11px] text-slate-400 mt-1">
                    Boleh campur brand asal vendornya sama. Surat tanda terima dicetak setelah pengiriman dibuat.
                </p>
            </form>
        @endif
    </div>

    {{-- ===== DAFTAR PENGIRIMAN ===== --}}
    <form method="GET" class="mb-3 flex flex-wrap items-center gap-2">
        <select name="status" onchange="this.form.submit()"
                class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
            <option value="">Semua status</option>
            @foreach($statuses as $s)
                <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
        @if(request()->filled('status'))
            <a href="{{ route('warranty.supplier.index') }}" class="text-xs font-semibold text-rose-500 hover:underline">✕ reset</a>
        @endif
    </form>

    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-500 uppercase bg-slate-50">
                <tr>
                    <th class="px-4 py-3">No. Kiriman</th>
                    <th class="px-4 py-3">Vendor</th>
                    <th class="px-4 py-3">Isi</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Nilai ganti uang</th>
                    <th class="px-4 py-3">Macet</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($shipments as $s)
                    @php $sla = $s->slaLevel(); @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-mono text-xs font-semibold whitespace-nowrap">{{ $s->shipment_number }}</td>
                        <td class="px-4 py-3">{{ $s->vendor->name }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ $s->items_count }} unit</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-medium whitespace-nowrap
                                @if($s->status === \App\Enums\SupplierShipmentStatus::Selesai) bg-emerald-100 text-emerald-800
                                @elseif($s->status === \App\Enums\SupplierShipmentStatus::Draft) bg-slate-200 text-slate-600
                                @else bg-sky-100 text-sky-800 @endif">
                                {{ $s->status->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs">
                            @if($s->refundTotal() > 0)
                                <b class="text-emerald-700">Rp {{ number_format($s->refundTotal(), 0, ',', '.') }}</b>
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if($s->status === \App\Enums\SupplierShipmentStatus::Selesai)
                                <span class="text-slate-300 text-xs">—</span>
                            @else
                                <span class="inline-flex items-center gap-1 text-xs font-semibold
                                    {{ $sla === 'critical' ? 'text-rose-600' : ($sla === 'warning' ? 'text-amber-600' : 'text-slate-400') }}">
                                    <span class="w-2 h-2 rounded-full {{ $sla === 'critical' ? 'bg-rose-500' : ($sla === 'warning' ? 'bg-amber-400' : 'bg-emerald-400') }}"></span>
                                    {{ $s->idleDays() }} hr
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('warranty.supplier.show', $s) }}" class="text-emerald-700 text-xs font-semibold hover:underline">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">Belum ada pengiriman ke supplier.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($shipments->hasPages())
        <div class="mt-3">{{ $shipments->links() }}</div>
    @endif
@endsection
