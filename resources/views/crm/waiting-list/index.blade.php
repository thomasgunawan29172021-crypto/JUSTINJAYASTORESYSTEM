@extends('layouts.app')
@section('title', 'Waiting List / PO List')
@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap justify-between items-center gap-3">
        <div><h1 class="text-2xl font-bold">Waiting List / PO List</h1><p class="text-sm text-slate-500 mt-1">Cek ketersediaan barang langsung, lalu hubungi pelanggan secara manual.</p></div>
        <a href="{{ route('crm.waiting-list.create') }}" class="bg-emerald-600 text-white rounded-lg px-4 py-3 font-semibold">+ Catat pesanan</a>
    </div>
    @if($errors->any())<div role="alert" class="bg-rose-50 text-rose-700 p-4 rounded-xl">{{ $errors->first() }}</div>@endif
    <section class="bg-white rounded-xl border p-5">
        <h2 class="font-bold mb-1">Rekap kebutuhan aktif</h2>
        <p class="text-xs text-slate-500 mb-4">Menunggu + sudah dikabari, diurutkan berdasarkan jumlah unit. Klik produk untuk melihat penunggunya.</p>
        <div class="overflow-x-auto"><table class="w-full text-sm text-left"><thead><tr class="border-b"><th class="p-2">Produk</th><th class="p-2">Permintaan</th><th class="p-2">Unit</th><th class="p-2">Nilai kebutuhan</th></tr></thead><tbody>
            @forelse($recap as $row)<tr class="border-b"><td class="p-2"><a class="text-emerald-700 underline" href="{{ route('crm.waiting-list.index', ['product' => $row->product_key]) }}">{{ $row->product_name }}</a></td><td class="p-2">{{ $row->requests }}</td><td class="p-2 font-bold">{{ $row->units }}</td><td class="p-2 whitespace-nowrap">Rp {{ number_format($row->total, 0, ',', '.') }}</td></tr>
            @empty<tr><td colspan="4" class="p-4 text-slate-500">Belum ada kebutuhan aktif.</td></tr>@endforelse
        </tbody></table></div>
        <div class="mt-3">{{ $recap->links() }}</div>
    </section>
    <form method="GET" class="flex flex-wrap gap-2">
        <input name="q" aria-label="Cari pesanan" maxlength="150" value="{{ request('q') }}" placeholder="Cari produk, nama, atau nomor" class="border rounded-lg p-2 flex-1 min-w-0">
        @if(request('product'))<input type="hidden" name="product" value="{{ request('product') }}">@endif
        <select name="status" aria-label="Filter status" class="border rounded-lg p-2"><option value="active" @selected($status === 'active')>Semua aktif</option><option value="all" @selected($status === 'all')>Semua status</option>@foreach(\App\Models\WaitingItem::STATUSES as $key => $label)<option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>@endforeach</select>
        <button class="bg-slate-800 text-white rounded-lg px-4 py-2">Tampilkan</button><a href="{{ route('crm.waiting-list.index') }}" class="border rounded-lg px-4 py-2">Reset</a>
    </form>
    @if(request('product'))<p class="text-sm">Penunggu produk: <strong>{{ request('product') }}</strong></p>@endif
    <p class="text-sm text-slate-500">{{ $orders->total() }} pesanan ditemukan. Semua produk dalam pesanan ditampilkan untuk memudahkan pembaruan sekaligus.</p>
    @forelse($orders as $order)
    <article class="bg-white rounded-xl border p-5 space-y-4">
        <div class="flex flex-wrap justify-between gap-3">
            <div><h2 class="font-bold">PO #{{ $order->id }} · {{ $order->customer->name }}</h2>
                <p class="text-sm text-slate-600">@foreach($order->customer->contacts as $contact)<span class="mr-3">{{ $contact->type }}: {{ $contact->value }}</span>@endforeach</p>
                <p class="text-xs text-slate-400 mt-1">{{ $order->created_at->format('d/m/Y H:i') }} · Dicatat {{ $order->creator->name }}</p>
            </div>
            <form method="POST" action="{{ route('crm.waiting-list.status', $order) }}" class="flex flex-wrap items-center gap-2" onsubmit="return confirm('Ubah status seluruh produk pada PO ini?')">
                @csrf @method('PATCH')
                <select name="status" aria-label="Status semua produk PO {{ $order->id }}" class="border rounded-lg p-2 text-sm">@foreach(\App\Models\WaitingItem::STATUSES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
                <button class="border border-emerald-600 text-emerald-700 rounded-lg p-2 text-sm">Terapkan semua produk</button>
            </form>
        </div>
        @if($order->notes)<p class="text-sm text-slate-600 whitespace-pre-line">{{ $order->notes }}</p>@endif
        <div class="overflow-x-auto"><table class="w-full text-sm text-left"><thead><tr class="border-b"><th class="p-2">Produk</th><th class="p-2">Qty</th><th class="p-2">Harga / unit</th><th class="p-2">Subtotal</th><th class="p-2">Status / tindakan</th></tr></thead><tbody>
        @foreach($order->items as $item)<tr class="border-b"><td class="p-2">{{ $item->product_name }}</td><td class="p-2">{{ $item->quantity }}</td><td class="p-2 whitespace-nowrap">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td><td class="p-2 whitespace-nowrap">Rp {{ number_format($item->quantity * $item->unit_price, 0, ',', '.') }}</td><td class="p-2">
            <form method="POST" action="{{ route('crm.waiting-list.status', $order) }}" class="flex gap-2">@csrf @method('PATCH')<input type="hidden" name="item_id" value="{{ $item->id }}"><select name="status" aria-label="Status {{ $item->product_name }}" class="border rounded-lg p-2">@foreach(\App\Models\WaitingItem::STATUSES as $key => $label)<option value="{{ $key }}" @selected($item->status === $key)>{{ $label }}</option>@endforeach</select><button class="text-emerald-700 font-semibold">Simpan</button></form>
            @if($item->notified_at)<p class="text-xs text-slate-500 mt-1">Dikabari {{ $item->notified_at->format('d/m/Y H:i') }}</p>@endif
            @if($item->purchased_at)<p class="text-xs text-slate-500 mt-1">Beli {{ $item->purchased_at->format('d/m/Y H:i') }}</p>@endif
        </td></tr>@endforeach
        </tbody></table></div>
    </article>
    @empty<div class="rounded-xl border bg-white p-8 text-center text-slate-500">Tidak ada pesanan untuk filter ini.</div>@endforelse
    {{ $orders->links() }}
</div>
@endsection
