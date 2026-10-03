@extends('layouts.app')
@section('title', $purchase ? 'Edit Penjualan' : 'Catat Penjualan')
@section('content')
@php
    $editing = (bool) $purchase;
    $savedItems = $purchase
        ? collect($purchase->items)->map(fn($i) => $i->only(['product_name','brand','product_type','quantity','unit_price']))->values()->all()
        : [['product_name'=>'','brand'=>'','product_type'=>'','quantity'=>1,'unit_price'=>'']];
    $initialItems = old('items', $savedItems);
    $initialPhone = old('phone', $customer?->primaryContact()?->value ?? '');
@endphp
<div class="mx-auto max-w-6xl space-y-5">
    <div><a href="{{ $editing ? route('crm.sales.show', $purchase) : route('crm.sales.index') }}" class="text-sm text-emerald-700">← Kembali</a><h1 class="mt-1 text-2xl font-bold">{{ $editing ? 'Edit penjualan #'.$purchase->id : 'Catat penjualan' }}</h1><p class="text-sm text-slate-500">Masukkan nomor HP terlebih dahulu. Sistem akan mencari dan mengisi data pelanggan secara otomatis.</p></div>
    @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 text-sm text-rose-700" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form id="sales-form" method="POST" action="{{ $editing ? route('crm.sales.update', $purchase) : route('crm.sales.store') }}" class="space-y-5" data-lookup-url="{{ route('crm.sales.customer-lookup') }}" data-draft-key="jjdraft:crm-sale-create:{{ auth()->id() }}">
        @csrf @if($editing) @method('PUT') @endif
        <section class="rounded-xl border bg-white p-5 space-y-4">
            <div class="flex items-center justify-between"><h2 class="font-bold">Pelanggan</h2><span id="lookup-status" class="text-xs text-slate-400">Ketik nomor untuk mencari</span></div>
            <div class="grid gap-3 md:grid-cols-2">
                <label>Nomor HP utama<input id="phone" name="phone" type="tel" maxlength="30" required value="{{ $initialPhone }}" placeholder="08xx atau +62xx" class="mt-1 block w-full rounded-lg border p-2"></label>
                <label id="match-picker-wrap" class="hidden">Nomor dipakai beberapa pelanggan<select id="match-picker" class="mt-1 block w-full rounded-lg border p-2"></select></label>
            </div>
            <input type="hidden" name="customer_id" id="customer-id" value="{{ old('customer_id', $customer?->id) }}">
            <div id="customer-found" class="{{ $customer ? '' : 'hidden' }} rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">Pelanggan ditemukan: <strong id="found-name">{{ $customer?->name }}</strong><span id="found-phones" class="ml-1 text-emerald-600">{{ $customer?->contacts?->whereIn('type',['phone','whatsapp'])->pluck('value')->join(', ') }}</span></div>
            <div id="new-customer" class="{{ $customer ? 'hidden' : '' }} space-y-3"><p class="rounded-lg bg-sky-50 p-3 text-sm text-sky-700">Nomor belum terdaftar. Isi data berikut, pelanggan akan otomatis dibuat saat penjualan disimpan.</p><div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                <label>Nama pelanggan<input name="name" maxlength="100" value="{{ old('name') }}" class="mt-1 block w-full rounded-lg border p-2"></label>
                <label>Nomor HP kedua <span class="text-xs text-slate-400">(opsional)</span><input name="secondary_phone" type="tel" maxlength="30" value="{{ old('secondary_phone') }}" class="mt-1 block w-full rounded-lg border p-2"></label>
                <label>Domisili / alamat<input name="address" maxlength="255" value="{{ old('address') }}" class="mt-1 block w-full rounded-lg border p-2"></label>
                <label>Sumber pelanggan<input name="source" maxlength="50" value="{{ old('source') }}" list="source-options" placeholder="TikTok, Facebook, referral…" class="mt-1 block w-full rounded-lg border p-2"><datalist id="source-options"><option value="TikTok"><option value="Facebook"><option value="Instagram"><option value="WhatsApp"><option value="Walk-in"><option value="Referral"></datalist></label>
            </div></div>
        </section>

        <section class="rounded-xl border bg-white p-5 space-y-4">
            <h2 class="font-bold">Informasi transaksi</h2><div class="grid gap-3 md:grid-cols-3">
                <label>Tanggal penjualan<input type="date" name="purchased_at" max="{{ today()->toDateString() }}" required value="{{ old('purchased_at', $purchase?->purchased_at?->toDateString() ?? today()->toDateString()) }}" class="mt-1 block w-full rounded-lg border p-2"></label>
                <label>Cabang<select name="branch_id" required class="mt-1 block w-full rounded-lg border p-2">@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id', $purchase?->branch_id ?? auth()->user()->branch_id) == $branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
                <label>Metode pembayaran<select name="payment_method" required class="mt-1 block w-full rounded-lg border p-2"><option value="">Pilih metode</option>@foreach(\App\Models\Purchase::PAYMENT_METHODS as $key=>$label)<option value="{{ $key }}" @selected(old('payment_method', $purchase?->payment_method) === $key)>{{ $label }}</option>@endforeach</select></label>
            </div>
        </section>

        <section class="rounded-xl border bg-white p-5 space-y-4">
            <div class="flex items-center justify-between"><div><h2 class="font-bold">Produk terjual</h2><p class="text-xs text-slate-500">Nama produk bebas. Brand dan tipe membantu filter laporan CRM.</p></div><p class="text-right text-sm text-slate-500">Total<br><strong id="grand-total" class="text-xl text-emerald-700">Rp 0</strong></p></div>
            <div id="items" class="space-y-3">@foreach($initialItems as $i=>$item)<div class="item-row grid gap-3 border-b pb-4 lg:grid-cols-12">
                <label class="lg:col-span-3">Nama produk<input data-field="product_name" name="items[{{ $i }}][product_name]" required maxlength="150" value="{{ $item['product_name'] ?? '' }}" placeholder="iPhone 15 128GB" class="mt-1 block w-full rounded-lg border p-2"></label>
                <label class="lg:col-span-2">Brand<input data-field="brand" name="items[{{ $i }}][brand]" maxlength="100" value="{{ $item['brand'] ?? '' }}" placeholder="Apple" class="mt-1 block w-full rounded-lg border p-2"></label>
                <label class="lg:col-span-2">Tipe / kategori<input data-field="product_type" name="items[{{ $i }}][product_type]" maxlength="100" value="{{ $item['product_type'] ?? '' }}" placeholder="Smartphone" class="mt-1 block w-full rounded-lg border p-2"></label>
                <label class="lg:col-span-1">Qty<input data-field="quantity" name="items[{{ $i }}][quantity]" type="number" min="1" max="9999" required value="{{ $item['quantity'] ?? 1 }}" class="mt-1 block w-full rounded-lg border p-2"></label>
                <label class="lg:col-span-3">Harga / unit<input data-field="unit_price" data-rupiah name="items[{{ $i }}][unit_price]" inputmode="numeric" required value="{{ ($item['unit_price'] ?? '') !== '' ? number_format((float) $item['unit_price'], 0, ',', '.') : '' }}" placeholder="10.000.000" class="mt-1 block w-full rounded-lg border p-2"></label>
                <button type="button" class="remove-item self-end p-2 text-rose-600 lg:col-span-1">Hapus</button>
            </div>@endforeach</div>
            <button type="button" id="add-item" class="font-semibold text-emerald-700">+ Tambah produk</button>
            <label class="block">Catatan<textarea name="notes" maxlength="2000" rows="3" class="mt-1 block w-full rounded-lg border p-2">{{ old('notes', $purchase?->notes) }}</textarea></label>
        </section>
        <button class="rounded-xl bg-emerald-600 px-6 py-3 font-bold text-white">{{ $editing ? 'Simpan perubahan' : 'Simpan penjualan' }}</button>
    </form>
</div>
<div id="delete-modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-slate-900/50 p-4"><div class="w-full max-w-sm rounded-xl bg-white p-5 shadow-xl"><h2 class="text-lg font-bold">Hapus produk?</h2><p class="mt-2 text-sm text-slate-600">Produk ini akan dikeluarkan dari transaksi.</p><div class="mt-5 flex justify-end gap-2"><button type="button" id="cancel-delete" class="rounded-lg border px-4 py-2">Batal</button><button type="button" id="confirm-delete" class="rounded-lg bg-rose-600 px-4 py-2 font-semibold text-white">Hapus</button></div></div></div>
@endsection
@push('scripts')
<script>
(() => {
    const form = document.getElementById('sales-form'), rows = document.getElementById('items'), phone = document.getElementById('phone');
    const customerId = document.getElementById('customer-id'), found = document.getElementById('customer-found'), newCustomer = document.getElementById('new-customer');
    const status = document.getElementById('lookup-status'), pickerWrap = document.getElementById('match-picker-wrap'), picker = document.getElementById('match-picker');
    const draftKey = form.dataset.draftKey, editing = {{ $editing ? 'true' : 'false' }};
    let timer, matches = [], pendingDelete = null, restoring = false;
    const rowTemplate = rows.firstElementChild.cloneNode(true);
    const digits = value => String(value || '').replace(/\D/g, '');
    const rupiah = value => digits(value).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    const choose = customer => {
        customerId.value = customer?.id || '';
        found.classList.toggle('hidden', !customer); newCustomer.classList.toggle('hidden', !!customer);
        document.getElementById('found-name').textContent = customer?.name || '';
        document.getElementById('found-phones').textContent = customer ? `(${customer.phones.join(', ')})` : '';
        status.textContent = customer ? 'Pelanggan ditemukan' : 'Pelanggan baru';
        saveDraft();
    };
    const lookup = async () => {
        customerId.value = ''; pickerWrap.classList.add('hidden');
        if (digits(phone.value).length < 9) { status.textContent = 'Nomor belum lengkap'; return; }
        status.textContent = 'Mencari…';
        try {
            const response = await fetch(`${form.dataset.lookupUrl}?phone=${encodeURIComponent(phone.value)}`, {headers:{'Accept':'application/json'}});
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || 'Pencarian gagal');
            matches = data.customers;
            if (matches.length === 1) choose(matches[0]);
            else if (matches.length > 1) { picker.innerHTML = '<option value="">Pilih pelanggan</option>' + matches.map(c => `<option value="${c.id}">${c.name} — ${c.phones.join(', ')}</option>`).join(''); pickerWrap.classList.remove('hidden'); choose(null); status.textContent = `${matches.length} pelanggan ditemukan`; }
            else choose(null);
        } catch (error) { status.textContent = error.message; status.classList.add('text-rose-600'); }
    };
    picker.addEventListener('change', () => choose(matches.find(c => String(c.id) === picker.value)));
    phone.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(lookup, 450); saveDraft(); });
    const recalc = () => { let total = 0; rows.querySelectorAll('.item-row').forEach(row => total += Number(digits(row.querySelector('[data-field="unit_price"]').value) || 0) * Number(row.querySelector('[data-field="quantity"]').value || 0)); document.getElementById('grand-total').textContent = `Rp ${new Intl.NumberFormat('id-ID').format(total)}`; };
    rows.querySelectorAll('[data-rupiah]').forEach(input => input.value = rupiah(input.value)); recalc();
    let nextIndex = Math.max(0, ...Array.from(rows.querySelectorAll('[data-field]'), i => Number(i.name.match(/\[(\d+)\]/)?.[1] || 0))) + 1;
    const addRow = (item={}) => { const row = rowTemplate.cloneNode(true); row.querySelectorAll('input').forEach(input => { input.name = `items[${nextIndex}][${input.dataset.field}]`; input.value = item[input.dataset.field] ?? (input.dataset.field === 'quantity' ? 1 : ''); if (input.matches('[data-rupiah]')) input.value = rupiah(input.value); }); nextIndex++; rows.appendChild(row); return row; };
    const draftData = () => ({phone:phone.value,customer_id:customerId.value,name:form.elements.name.value,secondary_phone:form.elements.secondary_phone.value,address:form.elements.address.value,source:form.elements.source.value,branch_id:form.elements.branch_id.value,purchased_at:form.elements.purchased_at.value,payment_method:form.elements.payment_method.value,notes:form.elements.notes.value,items:Array.from(rows.querySelectorAll('.item-row')).map(row => Object.fromEntries(Array.from(row.querySelectorAll('[data-field]'), i => [i.dataset.field,i.value])))});
    const saveDraft = () => { if (editing || restoring) return; try { localStorage.setItem(draftKey, JSON.stringify(draftData())); } catch(e) {} };
    const restoreDraft = () => { if (editing || {{ $errors->any() ? 'true' : 'false' }}) return; let data; try { data=JSON.parse(localStorage.getItem(draftKey)||'null'); } catch(e){} if(!data)return; restoring=true; ['phone','customer_id','name','secondary_phone','address','source','branch_id','purchased_at','payment_method','notes'].forEach(k=>{if(data[k]!==undefined && form.elements[k]) form.elements[k].value=data[k];}); if(data.items?.length){ rows.innerHTML=''; data.items.forEach(addRow); } restoring=false; recalc(); if(digits(phone.value).length>=9) lookup(); };
    rows.addEventListener('input', e => { if(e.target.matches('[data-rupiah]')) e.target.value=rupiah(e.target.value); recalc(); saveDraft(); }); form.addEventListener('change', saveDraft);
    document.getElementById('add-item').addEventListener('click',()=>{addRow().querySelector('input').focus();saveDraft();});
    const modal=document.getElementById('delete-modal'), close=()=>{modal.classList.add('hidden');modal.classList.remove('flex');pendingDelete=null;};
    rows.addEventListener('click',e=>{if(e.target.classList.contains('remove-item')&&rows.children.length>1){pendingDelete=e.target.closest('.item-row');modal.classList.remove('hidden');modal.classList.add('flex');}});
    document.getElementById('cancel-delete').addEventListener('click',close); document.getElementById('confirm-delete').addEventListener('click',()=>{pendingDelete?.remove();recalc();saveDraft();close();}); modal.addEventListener('click',e=>{if(e.target===modal)close();});
    form.addEventListener('submit',()=>{if(!editing)localStorage.removeItem(draftKey);}); restoreDraft();
    if (!editing && phone.value && !customerId.value) lookup();
})();
</script>
@endpush
