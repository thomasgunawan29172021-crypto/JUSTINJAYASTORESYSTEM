@extends('layouts.app')
@section('title', 'Penjualan CRM')
@section('content')
<div class="space-y-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><h1 class="text-2xl font-bold">Penjualan CRM</h1><p class="text-sm text-slate-500">Riwayat pembelian untuk segmentasi dan follow-up pelanggan.</p></div>
        <a href="{{ route('crm.sales.create') }}" class="rounded-xl bg-emerald-600 px-4 py-3 font-semibold text-white">+ Catat penjualan</a>
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-xl border bg-white p-4"><p class="text-xs uppercase text-slate-400">Transaksi</p><p class="text-2xl font-bold">{{ number_format($summary['transactions'], 0, ',', '.') }}</p></div>
        <div class="rounded-xl border bg-white p-4"><p class="text-xs uppercase text-slate-400">Unit terjual</p><p class="text-2xl font-bold">{{ number_format($summary['units'], 0, ',', '.') }}</p></div>
        <div class="rounded-xl border bg-white p-4"><p class="text-xs uppercase text-slate-400">Pelanggan</p><p class="text-2xl font-bold">{{ number_format($summary['customers'], 0, ',', '.') }}</p></div>
        <div class="rounded-xl border bg-white p-4"><p class="text-xs uppercase text-slate-400">Total penjualan</p><p class="text-xl font-bold text-emerald-700">Rp {{ number_format($summary['revenue'], 0, ',', '.') }}</p></div>
    </div>

    <form method="GET" class="rounded-xl border bg-white p-4 space-y-3">
        <div class="grid gap-3 md:grid-cols-3 lg:grid-cols-5">
            <label class="text-xs text-slate-500 lg:col-span-2">Cari pelanggan, nomor, produk<input name="q" value="{{ request('q') }}" class="mt-1 block w-full rounded-lg border p-2 text-sm"></label>
            <label class="text-xs text-slate-500">Dari tanggal<input type="date" name="date_from" value="{{ request('date_from') }}" class="mt-1 block w-full rounded-lg border p-2 text-sm"></label>
            <label class="text-xs text-slate-500">Sampai tanggal<input type="date" name="date_to" value="{{ request('date_to') }}" class="mt-1 block w-full rounded-lg border p-2 text-sm"></label>
            <label class="text-xs text-slate-500">Metode pembayaran<select name="payment_method" class="mt-1 block w-full rounded-lg border p-2 text-sm"><option value="">Semua</option>@foreach(\App\Models\Purchase::PAYMENT_METHODS as $key => $label)<option value="{{ $key }}" @selected(request('payment_method') === $key)>{{ $label }}</option>@endforeach</select></label>
            <label class="text-xs text-slate-500">Brand<select name="brand" class="mt-1 block w-full rounded-lg border p-2 text-sm"><option value="">Semua</option>@foreach($options['brands'] as $value)<option @selected(request('brand') === $value)>{{ $value }}</option>@endforeach</select></label>
            <label class="text-xs text-slate-500">Tipe produk<select name="product_type" class="mt-1 block w-full rounded-lg border p-2 text-sm"><option value="">Semua</option>@foreach($options['types'] as $value)<option @selected(request('product_type') === $value)>{{ $value }}</option>@endforeach</select></label>
            <label class="text-xs text-slate-500">Sumber pelanggan<select name="source" class="mt-1 block w-full rounded-lg border p-2 text-sm"><option value="">Semua</option>@foreach($options['sources'] as $value)<option @selected(request('source') === $value)>{{ $value }}</option>@endforeach</select></label>
            <label class="text-xs text-slate-500">Domisili / alamat<input name="domicile" value="{{ request('domicile') }}" class="mt-1 block w-full rounded-lg border p-2 text-sm"></label>
            <label class="text-xs text-slate-500">Cabang<select name="branch_id" class="mt-1 block w-full rounded-lg border p-2 text-sm"><option value="">Semua</option>@foreach($options['branches'] as $branch)<option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
            <label class="text-xs text-slate-500">Total minimum<input data-rupiah-filter inputmode="numeric" name="min_total" value="{{ request('min_total') !== null ? number_format((float) request('min_total'), 0, ',', '.') : '' }}" class="mt-1 block w-full rounded-lg border p-2 text-sm"></label>
            <label class="text-xs text-slate-500">Total maksimum<input data-rupiah-filter inputmode="numeric" name="max_total" value="{{ request('max_total') !== null ? number_format((float) request('max_total'), 0, ',', '.') : '' }}" class="mt-1 block w-full rounded-lg border p-2 text-sm"></label>
        </div>
        <div class="flex gap-2"><button class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Terapkan filter</button><a href="{{ route('crm.sales.index') }}" class="rounded-lg border px-4 py-2 text-sm">Reset</a></div>
    </form>

    <div class="overflow-hidden rounded-xl border bg-white">
        <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="p-3 text-left">Tanggal</th><th class="p-3 text-left">Pelanggan</th><th class="p-3 text-left">Produk</th><th class="p-3 text-left">Pembayaran</th><th class="p-3 text-right">Total</th><th class="p-3"></th></tr></thead>
        <tbody class="divide-y">@forelse($purchases as $purchase)<tr class="hover:bg-slate-50"><td class="p-3 whitespace-nowrap">{{ $purchase->purchased_at->format('d M Y') }}<div class="text-xs text-slate-400">{{ $purchase->branch->name }}</div></td><td class="p-3"><a class="font-semibold text-emerald-700" href="{{ route('crm.customers.show', $purchase->customer) }}">{{ $purchase->customer->name }}</a><div class="text-xs text-slate-400">{{ $purchase->customer->primaryContact()?->value }}</div></td><td class="p-3">{{ $purchase->items->map(fn($i) => $i->product_name.' ×'.$i->quantity)->join(', ') }}</td><td class="p-3">{{ \App\Models\Purchase::PAYMENT_METHODS[$purchase->payment_method] ?? $purchase->payment_method }}</td><td class="p-3 text-right font-semibold">Rp {{ number_format($purchase->total_amount, 0, ',', '.') }}</td><td class="p-3 text-right"><a href="{{ route('crm.sales.show', $purchase) }}" class="font-semibold text-sky-600">Detail</a></td></tr>@empty<tr><td colspan="6" class="p-10 text-center text-slate-400">Belum ada penjualan yang cocok.</td></tr>@endforelse</tbody></table></div>
        @if($purchases->hasPages())<div class="border-t p-4">{{ $purchases->links() }}</div>@endif
    </div>
</div>
@endsection
@push('scripts')
<script>document.querySelectorAll('[data-rupiah-filter]').forEach(input => input.addEventListener('input', () => { input.value = input.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }));</script>
@endpush
