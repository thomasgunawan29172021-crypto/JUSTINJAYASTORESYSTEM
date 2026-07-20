@extends('layouts.app')

@section('title', 'Klaim Retur Baru')

@section('content')
    <a href="{{ route('warranty.claims.index') }}" class="text-sm text-slate-500 hover:underline">← Klaim Retur</a>
    <h1 class="text-xl font-bold mt-2 mb-5">Terima Barang Retur</h1>

    <form method="POST" action="{{ route('warranty.claims.store') }}" enctype="multipart/form-data"
          data-draft="warranty-create"
          class="bg-white rounded-xl border border-slate-200 p-5 max-w-lg space-y-4">
        @csrf

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Cabang penerima *</label>
            <select name="branch_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm bg-white">
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" @selected(old('branch_id') == $b->id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Nama pelanggan *</label>
                <input type="text" name="customer_name" value="{{ old('customer_name') }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">No. HP pelanggan *</label>
                <input type="tel" name="customer_phone" value="{{ old('customer_phone') }}" required placeholder="08xxxxxxxxxx"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                <p class="text-[11px] text-slate-400 mt-1">Dipakai pelanggan buat lacak status — pastikan benar.</p>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Produk *</label>
            <select name="product_id" required data-searchable class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm bg-white">
                <option value="">— pilih produk —</option>
                @foreach($products as $p)
                    <option value="{{ $p->id }}" @selected(old('product_id') == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">IMEI / Serial</label>
                <input type="text" name="imei" value="{{ old('imei') }}" placeholder="ketik / scan"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">No. pesanan / nota</label>
                <input type="text" name="order_number" value="{{ old('order_number') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Tanggal beli</label>
            <input type="date" name="purchased_at" value="{{ old('purchased_at') }}" max="{{ now()->toDateString() }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Kelengkapan yang diserahkan</label>
            <div class="flex flex-wrap gap-2">
                @foreach(\App\Models\WarrantyClaim::COMPLETENESS_ITEMS as $item)
                    <label class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm cursor-pointer">
                        <input type="checkbox" name="completeness[]" value="{{ $item }}"
                               @checked(in_array($item, old('completeness', []))) class="rounded accent-emerald-500">
                        {{ ucwords(str_replace('_', ' ', $item)) }}
                    </label>
                @endforeach
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Alasan retur / keluhan *</label>
            <textarea name="reason" rows="3" required
                      class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">{{ old('reason') }}</textarea>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Foto barang (segala sisi) *</label>
            {{-- capture=environment → HP langsung buka kamera belakang. Tetap bisa pilih dari galeri. --}}
            <input type="file" name="photos[]" accept="image/*" capture="environment" multiple required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm bg-white">
            <p class="text-[11px] text-slate-400 mt-1">Minimal 1, maksimal 8 — depan, belakang, sisi, dan bagian yang rusak.</p>
        </div>

        <button class="w-full rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white py-3 text-sm font-bold">
            Terima Barang &amp; Buat Klaim
        </button>
    </form>
@endsection
