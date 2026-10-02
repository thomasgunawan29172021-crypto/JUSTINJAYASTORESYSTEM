@extends('layouts.app')
@section('title', 'Tambah Waiting List')
@section('content')
@php
    $formatRupiahInput = function ($value) {
        if ($value === null || $value === '') {
            return '';
        }

        $normalized = str_replace(['Rp', 'rp', ' ', '.'], '', (string) $value);
        $normalized = str_replace(',', '.', $normalized);

        if (! is_numeric($normalized)) {
            return (string) $value;
        }

        return number_format((float) $normalized, 0, ',', '.');
    };
@endphp
<div class="max-w-5xl mx-auto space-y-5">
    <a href="{{ route('crm.waiting-list.index') }}" class="text-emerald-700">← Waiting List / PO List</a>
    <h1 class="text-2xl font-bold">Catat kebutuhan pelanggan</h1>
    <p class="text-sm text-slate-500">Pilih pelanggan lama atau isi nama dan nomor baru. Harga adalah harga per unit yang dicatat saat pemesanan.</p>
    @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 text-rose-700" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form method="GET" class="flex gap-2">
        <input aria-label="Cari pelanggan lama" name="customer_q" value="{{ request('customer_q') }}" placeholder="Cari nama / nomor pelanggan lama" class="border rounded-lg p-2 flex-1">
        <button class="border rounded-lg px-4">Cari pelanggan</button>
    </form>
    <form method="POST" action="{{ route('crm.waiting-list.store') }}" class="space-y-5" id="waiting-list-form" data-draft-key="jjdraft:waiting-list-create:{{ auth()->id() }}">
        @csrf
        <div class="bg-white border rounded-xl p-5 space-y-3">
            <label class="block font-semibold" for="customer_id">Pelanggan</label>
            <select name="customer_id" id="customer_id" class="border rounded-lg p-2 w-full">
                <option value="">Daftarkan pelanggan baru</option>
                @foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id', request('customer_id')) == $customer->id)>{{ $customer->name }} — {{ $customer->primaryContact()?->value }}</option>@endforeach
            </select>
            <p class="text-xs text-slate-500">Maksimal 50 hasil. Gunakan pencarian di atas bila pelanggan belum terlihat.</p>
            <div id="new-customer" class="grid md:grid-cols-3 gap-3">
                <label>Nama<input name="name" maxlength="100" value="{{ old('name') }}" class="block border rounded-lg p-2 w-full"></label>
                <label>Nomor telepon<input name="phone" type="tel" maxlength="30" value="{{ old('phone') }}" class="block border rounded-lg p-2 w-full"></label>
                <label>Cabang pendaftaran<select name="branch_id" class="block border rounded-lg p-2 w-full">
                    @foreach($branches as $branch)
                        @if(auth()->user()->isCeo() || auth()->user()->hasRole(\App\Enums\UserRole::Crm) || auth()->user()->branch_id == $branch->id)
                        <option value="{{ $branch->id }}" @selected(old('branch_id', auth()->user()->branch_id) == $branch->id)>{{ $branch->name }}</option>
                        @endif
                    @endforeach
                </select></label>
            </div>
        </div>
        <div class="bg-white border rounded-xl p-5 space-y-4">
            <h2 class="font-bold">Produk yang dibutuhkan</h2>
            <p class="text-xs text-slate-500">Tulis tipe, kapasitas, warna, dan kondisi bila perlu. Gunakan nama yang konsisten agar rekap tergabung.</p>
            <div id="items" class="space-y-3">
                @foreach(old('items', [['product_name' => '', 'quantity' => 1, 'unit_price' => '']]) as $i => $item)
                <div class="item-row grid md:grid-cols-4 gap-3 border-b pb-3">
                    <label>Nama / tipe produk<input data-field="product_name" name="items[{{ $i }}][product_name]" value="{{ $item['product_name'] ?? '' }}" required maxlength="150" class="block border rounded-lg p-2 w-full"></label>
                    <label>Jumlah unit<input data-field="quantity" name="items[{{ $i }}][quantity]" type="number" min="1" max="9999" value="{{ $item['quantity'] ?? 1 }}" required class="block border rounded-lg p-2 w-full"></label>
                    <label>Harga / unit (Rp)<input data-field="unit_price" data-rupiah-input name="items[{{ $i }}][unit_price]" inputmode="numeric" autocomplete="off" value="{{ $formatRupiahInput($item['unit_price'] ?? '') }}" required class="block border rounded-lg p-2 w-full"></label>
                    <button type="button" class="remove-item text-rose-600 self-end p-2">Hapus produk</button>
                </div>
                @endforeach
            </div>
            <button type="button" id="add-item" class="text-emerald-700 font-semibold">+ Tambah produk</button>
            <label class="block">Catatan<textarea name="notes" maxlength="2000" class="block border rounded-lg p-2 w-full" rows="3">{{ old('notes') }}</textarea></label>
        </div>
        <button class="rounded-lg bg-emerald-600 text-white px-5 py-3 font-bold">Simpan pesanan</button>
    </form>
    <div id="delete-item-modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-slate-900/50 p-4">
        <div class="w-full max-w-sm rounded-xl bg-white p-5 shadow-xl border px-8">
            <h2 class="text-lg font-bold">Hapus produk?</h2>
            <p class="mt-2 text-sm text-slate-600">Produk ini akan dihapus dari form pesanan.</p>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" id="cancel-delete-item" class="rounded-lg border px-4 py-2 text-slate-700">Batal</button>
                <button type="button" id="confirm-delete-item" class="rounded-lg bg-rose-600 px-4 py-2 font-semibold text-white">Hapus produk</button>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
(() => {
    const customer = document.getElementById('customer_id');
    const form = document.getElementById('waiting-list-form');
    const draftKey = form.dataset.draftKey;
    let restoringDraft = false;
    const syncCustomer = () => {
        document.getElementById('new-customer').hidden = !!customer.value;
        document.querySelectorAll('#new-customer input, #new-customer select').forEach(input => { input.disabled = !!customer.value; input.required = !customer.value; });
    };
    customer.addEventListener('change', syncCustomer); syncCustomer();
    const rows = document.getElementById('items');
    const formatRupiah = value => value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    const syncRupiahInput = input => { input.value = formatRupiah(input.value); };
    rows.querySelectorAll('[data-rupiah-input]').forEach(syncRupiahInput);
    rows.addEventListener('input', event => {
        if (event.target.matches('[data-rupiah-input]')) syncRupiahInput(event.target);
    });
    const deleteModal = document.getElementById('delete-item-modal');
    const cancelDelete = document.getElementById('cancel-delete-item');
    const confirmDelete = document.getElementById('confirm-delete-item');
    let pendingDeleteRow = null;
    const openDeleteModal = row => {
        pendingDeleteRow = row;
        deleteModal.classList.remove('hidden');
        deleteModal.classList.add('flex');
        confirmDelete.focus();
    };
    const closeDeleteModal = () => {
        pendingDeleteRow = null;
        deleteModal.classList.add('hidden');
        deleteModal.classList.remove('flex');
    };
    cancelDelete.addEventListener('click', closeDeleteModal);
    deleteModal.addEventListener('click', event => {
        if (event.target === deleteModal) closeDeleteModal();
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !deleteModal.classList.contains('hidden')) closeDeleteModal();
    });
    confirmDelete.addEventListener('click', () => {
        if (pendingDeleteRow && rows.children.length > 1) pendingDeleteRow.remove();
        saveDraft();
        closeDeleteModal();
    });
    let nextIndex = Math.max(...Array.from(rows.querySelectorAll('[data-field]'), input => Number(input.name.match(/\[(\d+)\]/)[1]))) + 1;
    const addItemRow = (item = {}) => {
        if (rows.children.length >= 100) return;
        const row = rows.firstElementChild.cloneNode(true);
        row.querySelectorAll('input').forEach(input => {
            input.name = `items[${nextIndex}][${input.dataset.field}]`;
            input.value = item[input.dataset.field] ?? (input.dataset.field === 'quantity' ? '1' : '');
            if (input.matches('[data-rupiah-input]')) syncRupiahInput(input);
        });
        nextIndex++;
        rows.appendChild(row);
        return row;
    };
    const itemRows = () => Array.from(rows.querySelectorAll('.item-row'));
    const saveDraft = () => {
        if (restoringDraft) return;
        const draft = {
            customer_id: form.elements.customer_id.value,
            name: form.elements.name.value,
            phone: form.elements.phone.value,
            branch_id: form.elements.branch_id.value,
            notes: form.elements.notes.value,
            items: itemRows().map(row => ({
                product_name: row.querySelector('[data-field="product_name"]').value,
                quantity: row.querySelector('[data-field="quantity"]').value,
                unit_price: row.querySelector('[data-field="unit_price"]').value,
            })),
        };
        try { localStorage.setItem(draftKey, JSON.stringify(draft)); } catch (e) {}
    };
    const restoreDraft = () => {
        let draft = null;
        try { draft = JSON.parse(localStorage.getItem(draftKey) || 'null'); } catch (e) {}
        if (!draft || Object.keys(draft).length === 0) return;

        restoringDraft = true;
        ['customer_id', 'name', 'phone', 'branch_id', 'notes'].forEach(name => {
            if (draft[name] !== undefined && form.elements[name] && form.elements[name].value === '') form.elements[name].value = draft[name];
        });
        if (Array.isArray(draft.items) && draft.items.length > 0 && itemRows().length === 1) {
            const [first, ...rest] = draft.items;
            if (first) {
                itemRows()[0].querySelectorAll('input').forEach(input => {
                    input.value = first[input.dataset.field] ?? (input.dataset.field === 'quantity' ? '1' : '');
                    if (input.matches('[data-rupiah-input]')) syncRupiahInput(input);
                });
            }
            rest.forEach(addItemRow);
        }
        restoringDraft = false;
        syncCustomer();
    };
    restoreDraft();
    form.addEventListener('input', saveDraft);
    form.addEventListener('change', saveDraft);
    form.addEventListener('submit', () => localStorage.removeItem(draftKey));
    document.getElementById('add-item').addEventListener('click', () => {
        const row = addItemRow();
        saveDraft();
        row?.querySelector('input').focus();
    });
    rows.addEventListener('click', event => {
        if (event.target.classList.contains('remove-item') && rows.children.length > 1) openDeleteModal(event.target.closest('.item-row'));
    });
})();
</script>
@endpush
