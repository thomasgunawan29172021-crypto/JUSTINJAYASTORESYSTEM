@extends('layouts.app')
@section('title', 'Pelanggan CRM')
@section('content')
@php
    $canSales = auth()->user()->canManageSales();
    $nameSort = request('sort') === 'name_asc' ? 'name_desc' : 'name_asc';
    $dateSort = request('sort', 'newest') === 'newest' ? 'oldest' : 'newest';
    $sortUrl = fn ($sort) => route('crm.customers.index', array_merge(request()->except(['page', 'sort']), ['sort' => $sort]));
@endphp
<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h1 class="text-xl font-bold">Pelanggan</h1><p class="text-sm text-slate-500">{{ number_format($customers->total(), 0, ',', '.') }} pelanggan ditemukan</p></div>
        <a href="{{ route('crm.customers.create') }}" class="rounded-xl bg-emerald-500 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-400">+ Pelanggan Baru</a>
    </div>

    <form method="GET" class="rounded-xl border border-slate-200 bg-white p-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
            <label class="text-xs font-semibold text-slate-500 sm:col-span-2">Cari nama / nomor HP
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Nama, HP, atau WhatsApp…" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            </label>
            @if($canSales)
                <label class="text-xs font-semibold text-slate-500">Brand
                    <select name="brand" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">Semua brand</option>@foreach($options['brands'] as $brand)<option value="{{ $brand }}" @selected(request('brand')===$brand)>{{ $brand }}</option>@endforeach</select>
                </label>
                <label class="text-xs font-semibold text-slate-500">Metode pembayaran
                    <select name="payment_method" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">Semua metode</option>@foreach(\App\Models\Purchase::PAYMENT_METHODS as $key=>$label)<option value="{{ $key }}" @selected(request('payment_method')===$key)>{{ $label }}</option>@endforeach</select>
                </label>
            @endif
            <label class="text-xs font-semibold text-slate-500">Sumber pelanggan
                <select name="source" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">Semua sumber</option>@foreach($options['sources'] as $source)<option value="{{ $source }}" @selected(request('source')===$source)>{{ ucfirst($source) }}</option>@endforeach</select>
            </label>
            <label class="text-xs font-semibold text-slate-500">Cabang
                <select name="branch_id" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">Semua cabang</option>@foreach($options['branches'] as $branch)<option value="{{ $branch->id }}" @selected(request('branch_id')==$branch->id)>{{ $branch->name }}</option>@endforeach</select>
            </label>
            <label class="text-xs font-semibold text-slate-500">Kota / area
                <input name="city" list="customer-city-options" value="{{ request('city') }}" placeholder="Palembang" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"><datalist id="customer-city-options">@foreach($options['cities'] as $city)<option value="{{ $city }}">@endforeach</datalist>
            </label>
            @if($canSales)
                <label class="text-xs font-semibold text-slate-500">Harga produk minimum
                    <input data-filter-rupiah name="min_price" inputmode="numeric" value="{{ request('min_price') !== null ? number_format((float) request('min_price'), 0, ',', '.') : '' }}" placeholder="5.000.000" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                </label>
                <label class="text-xs font-semibold text-slate-500">Harga produk maksimum
                    <input data-filter-rupiah name="max_price" inputmode="numeric" value="{{ request('max_price') !== null ? number_format((float) request('max_price'), 0, ',', '.') : '' }}" placeholder="8.000.000" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                </label>
            @endif
            <label class="text-xs font-semibold text-slate-500">Urutkan
                <select name="sort" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="newest" @selected(request('sort','newest')==='newest')>Terbaru</option><option value="oldest" @selected(request('sort')==='oldest')>Terlama</option><option value="name_asc" @selected(request('sort')==='name_asc')>Nama A–Z</option><option value="name_desc" @selected(request('sort')==='name_desc')>Nama Z–A</option></select>
            </label>
        </div>
        <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
            <button class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white">Terapkan filter</button>
            <a href="{{ route('crm.customers.index') }}" class="rounded-lg border px-4 py-2.5 text-center text-sm font-semibold text-slate-600">Reset</a>
            @if($canSales)<a href="{{ route('crm.customers.export', request()->except('page')) }}" class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-2.5 text-center text-sm font-semibold text-emerald-700 sm:ml-auto">Export Excel</a>@endif
        </div>
    </form>

    <div class="hidden overflow-x-auto rounded-xl border border-slate-200 bg-white md:block">
        <table class="w-full min-w-[850px] text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr>
                <th class="px-4 py-3"><a href="{{ $sortUrl($nameSort) }}" class="inline-flex items-center gap-1 hover:text-slate-900">Nama <span>⇅</span></a></th>
                <th class="px-4 py-3">Kontak utama</th><th class="px-4 py-3">Kota</th><th class="px-4 py-3">Sumber</th><th class="px-4 py-3">Cabang</th>
                @if($canSales)<th class="px-4 py-3">Pembelian terakhir</th>@endif
                <th class="px-4 py-3"><a href="{{ $sortUrl($dateSort) }}" class="inline-flex items-center gap-1 hover:text-slate-900">Terdaftar <span>⇅</span></a></th><th class="px-4 py-3"></th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">@forelse($customers as $customer) @php($contact=$customer->primaryContact())
                <tr class="hover:bg-slate-50"><td class="px-4 py-3 font-semibold">{{ $customer->name }}</td><td class="px-4 py-3">@if($contact)<span class="block text-[10px] uppercase text-slate-400">{{ $contact->type }}</span>{{ $contact->value }}@else<span class="text-slate-300">—</span>@endif</td><td class="px-4 py-3 text-xs text-slate-500">{{ $customer->city ?: '—' }}</td><td class="px-4 py-3 text-xs text-slate-500">{{ $customer->source ?: '—' }}</td><td class="px-4 py-3 text-xs text-slate-500">{{ $customer->branch?->name ?: '—' }}</td>
                @if($canSales)<td class="max-w-64 px-4 py-3 text-xs">@if($customer->latestPurchase)<p class="truncate font-semibold">{{ $customer->latestPurchase->items->pluck('product_name')->join(', ') }}</p><p class="text-slate-400">{{ $customer->latestPurchase->purchased_at->format('d M Y') }} · {{ \App\Models\Purchase::PAYMENT_METHODS[$customer->latestPurchase->payment_method] ?? $customer->latestPurchase->payment_method }}</p>@else<span class="text-slate-300">Belum ada</span>@endif</td>@endif
                <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-400">{{ $customer->created_at->format('d M Y') }}</td><td class="whitespace-nowrap px-4 py-3 text-right"><a href="{{ route('crm.customers.show',$customer) }}" class="text-xs font-semibold text-sky-600 hover:underline">Detail →</a></td></tr>
            @empty<tr><td colspan="{{ $canSales ? 8 : 7 }}" class="px-4 py-10 text-center text-slate-400">Belum ada pelanggan ditemukan.</td></tr>@endforelse</tbody>
        </table>
    </div>

    <div class="space-y-3 md:hidden">@forelse($customers as $customer) @php($contact=$customer->primaryContact())
        <article class="rounded-xl border border-slate-200 bg-white p-4"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><h2 class="truncate font-bold">{{ $customer->name }}</h2><p class="text-sm text-slate-600">{{ $contact?->value ?: 'Belum ada kontak' }}</p></div><a href="{{ route('crm.customers.show',$customer) }}" class="shrink-0 text-sm font-semibold text-sky-600">Detail →</a></div><div class="mt-3 flex flex-wrap gap-2 text-xs"><span class="rounded-full bg-slate-100 px-2 py-1">{{ $customer->city ?: 'Kota belum diisi' }}</span><span class="rounded-full bg-slate-100 px-2 py-1">{{ $customer->source ?: 'Tanpa sumber' }}</span><span class="rounded-full bg-slate-100 px-2 py-1">{{ $customer->branch?->name }}</span></div>@if($canSales && $customer->latestPurchase)<div class="mt-3 border-t pt-3 text-xs"><p class="font-semibold">{{ $customer->latestPurchase->items->pluck('product_name')->join(', ') }}</p><p class="text-slate-400">Pembelian terakhir {{ $customer->latestPurchase->purchased_at->format('d M Y') }}</p></div>@endif</article>
    @empty<div class="rounded-xl border bg-white p-8 text-center text-sm text-slate-400">Belum ada pelanggan ditemukan.</div>@endforelse</div>

    @if($customers->hasPages())<div>{{ $customers->links() }}</div>@endif
</div>
@endsection
@push('scripts')
<script>document.querySelectorAll('[data-filter-rupiah]').forEach(function(input){input.addEventListener('input',function(){input.value=input.value.replace(/\D/g,'').replace(/\B(?=(\d{3})+(?!\d))/g,'.');});});</script>
@endpush
