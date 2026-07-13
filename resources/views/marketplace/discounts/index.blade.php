@extends('layouts.app')

@section('title', 'Diskon Produk')

@section('content')
    <h1 class="text-xl font-bold mb-1">Pengingat Diskon Produk</h1>
    <p class="text-sm text-slate-500 mb-5">
        Catatan masa berlaku diskon — TIDAK mengubah harga. Alert muncul di dashboard 30 hari sebelum berakhir.
        Menormalkan harga tetap lewat Edit Produk (otomatis membuat tugas update harga).
    </p>

    <form method="POST" action="{{ route('marketplace.discounts.store') }}"
          class="bg-white rounded-xl border border-slate-200 p-5 grid sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end mb-5">
        @csrf
        <div class="lg:col-span-2">
            <label class="block text-xs font-semibold text-slate-600 mb-1">Produk *</label>
            <select name="product_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
                <option value="">— pilih —</option>
                @foreach($products as $p)
                    <option value="{{ $p->id }}" @selected(old('product_id') == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Nama diskon *</label>
            <input type="text" name="name" value="{{ old('name') }}" required placeholder="Promo 7.7"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Mulai *</label>
            <input type="date" name="starts_at" value="{{ old('starts_at') }}" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Berakhir *</label>
            <input type="date" name="ends_at" value="{{ old('ends_at') }}" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <button class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-semibold px-4 py-2 lg:col-span-5 sm:col-span-2 w-fit">
            + Catat Diskon
        </button>
    </form>

    <div class="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100">
        @forelse($discounts as $d)
            <div class="px-4 py-3 flex flex-wrap items-center justify-between gap-2 text-sm
                        {{ $d->hasEnded() ? 'bg-rose-50' : ($d->endsSoon() ? 'bg-amber-50' : '') }}">
                <div>
                    <p class="font-semibold">
                        {{ $d->product->name }}
                        <span class="text-slate-400 font-normal">— {{ $d->name }}</span>
                    </p>
                    <p class="text-xs text-slate-500">
                        {{ $d->starts_at->translatedFormat('d M Y') }} – {{ $d->ends_at->translatedFormat('d M Y') }}
                        @if($d->hasEnded())
                            <span class="ml-1 px-1.5 py-0.5 rounded bg-rose-100 text-rose-700 text-[10px] font-semibold">SUDAH BERAKHIR — cabut di marketplace!</span>
                        @elseif($d->endsSoon())
                            <span class="ml-1 px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 text-[10px] font-semibold">berakhir {{ $d->ends_at->diffForHumans() }}</span>
                        @endif
                        @if($d->note) · {{ $d->note }} @endif
                    </p>
                </div>
                <form method="POST" action="{{ route('marketplace.discounts.destroy', $d) }}"
                      onsubmit="return confirm('Hapus pengingat ini? (Pastikan diskonnya sudah dicabut di marketplace)')">
                    @csrf @method('DELETE')
                    <button class="text-rose-500 text-xs font-semibold hover:underline">Selesai / Hapus</button>
                </form>
            </div>
        @empty
            <p class="px-4 py-8 text-center text-sm text-slate-400">Belum ada pengingat diskon.</p>
        @endforelse
    </div>
@endsection
