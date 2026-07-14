@php $p = $product ?? null; $rp = fn ($n) => $n > 0 ? number_format($n, 0, ',', '.') : ''; @endphp

<div>
    <label class="block text-xs font-semibold text-slate-600 mb-1">Nama produk *</label>
    <input type="text" name="name" value="{{ old('name', $p?->name) }}" required
           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
</div>

<div class="grid sm:grid-cols-2 gap-3">
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Barcode</label>
        <input type="text" name="barcode" value="{{ old('barcode', $p?->barcode) }}" maxlength="100"
               placeholder="mis. 8991234567890"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Kode SKU</label>
        <input type="text" name="sku" value="{{ old('sku', $p?->sku) }}" maxlength="100"
               placeholder="mis. SRS-HC9-BLK"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
</div>

<div>
    <label class="block text-xs font-semibold text-slate-600 mb-1">Brand *</label>
    <select name="brand_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
        <option value="">— pilih —</option>
        @foreach($brands as $b)
            <option value="{{ $b->id }}" @selected(old('brand_id', $p?->brand_id) == $b->id)>{{ $b->name }}</option>
        @endforeach
    </select>
</div>

<div class="grid sm:grid-cols-3 gap-3">
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Harga beli <span class="text-rose-500">(rahasia)</span></label>
        <input type="text" inputmode="numeric" name="cost_price" value="{{ $rp(old('cost_price', $p?->cost_price ?? 0)) }}"
               placeholder="0" class="money-input w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Harga offline</label>
        <input type="text" inputmode="numeric" name="price_offline" value="{{ $rp(old('price_offline', $p?->price_offline ?? 0)) }}"
               placeholder="0" class="money-input w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Harga grosir</label>
        <input type="text" inputmode="numeric" name="price_grosir" value="{{ $rp(old('price_grosir', $p?->price_grosir ?? 0)) }}"
               placeholder="0" class="money-input w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
</div>

<div>
    <p class="text-xs font-semibold text-slate-600 mb-2">Harga per marketplace</p>

    @if($marketplaces->isNotEmpty())
        <div class="grid grid-cols-3 gap-3 mb-1">
            <span></span>
            <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">Harga Mall</span>
            <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">Harga Non-Mall</span>
        </div>
    @endif

    @forelse($marketplaces as $mp)
        @php $row = $p?->prices->firstWhere('marketplace', $mp); @endphp
        <div class="grid grid-cols-3 gap-3 items-center mb-2">
            <span class="text-sm font-medium">{{ ucfirst($mp) }}</span>
            <input type="text" inputmode="numeric" name="mp[{{ $mp }}][mall]"
                   value="{{ $rp($row?->price_mall ?? 0) }}" placeholder="0"
                   class="money-input rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input type="text" inputmode="numeric" name="mp[{{ $mp }}][regular]"
                   value="{{ $rp($row?->price_regular ?? 0) }}" placeholder="0"
                   class="money-input rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
    @empty
        <p class="text-xs text-amber-600">Belum ada toko aktif — marketplace terdeteksi dari daftar toko.</p>
    @endforelse
</div>
