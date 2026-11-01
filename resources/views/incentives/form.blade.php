@extends('layouts.app')
@section('title', $incentive->exists ? 'Edit Insentif' : 'Ajukan Insentif')
@section('content')
@php
    $editing = $incentive->exists;
    $savedItems = $editing ? $incentive->items->map->only(['product_name','ram','color','condition','imei','capital_price','sale_price'])->values()->all() : [['product_name'=>'','ram'=>'','color'=>'','condition'=>'new','imei'=>'','capital_price'=>'','sale_price'=>'']];
    $savedParticipants = $editing ? $incentive->participants->map->only(['user_id','incentive_type','cooperation_role'])->values()->all() : [['user_id'=>auth()->id(),'incentive_type'=>'fl','cooperation_role'=>'source']];
    $initialItems = old('items', $savedItems);
    $initialParticipants = old('participants', $savedParticipants);
    $savedSocial = $editing ? $incentive->socialActions->pluck('account_name','platform')->all() : [];
    $socialBeneficiary = old('social_beneficiary_id', $editing ? $incentive->socialActions->first()?->beneficiary_id : auth()->id());
@endphp
<div class="mx-auto max-w-7xl space-y-5">
    <div>
        <a href="{{ $editing ? route('incentives.show', $incentive) : route('incentives.index') }}" class="text-sm text-emerald-700">← Kembali</a>
        <h1 class="mt-1 text-2xl font-bold">{{ $editing ? 'Edit pengajuan '.$incentive->invoice_number : 'Ajukan insentif' }}</h1>
        <p class="text-sm text-slate-500">Satu invoice boleh memuat banyak barang dan promotor. Pastikan datanya sama dengan Accurate.</p>
    </div>
    @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 text-sm text-rose-700" role="alert">@foreach($errors->all() as $error)<p>• {{ $error }}</p>@endforeach</div>@endif

    <form method="POST" action="{{ $editing ? route('incentives.update', $incentive) : route('incentives.store') }}" class="space-y-5" id="incentive-form">
        @csrf @if($editing) @method('PUT') @endif
        <section class="rounded-xl border bg-white p-5 space-y-4">
            <h2 class="font-bold">Invoice</h2>
            <div class="grid gap-3 md:grid-cols-4">
                <label>Nomor invoice<input name="invoice_number" required maxlength="100" value="{{ old('invoice_number', $incentive->invoice_number) }}" class="mt-1 block w-full rounded-lg border p-2" placeholder="INV-2026-001"></label>
                <label>Tanggal transaksi<input type="date" name="transaction_date" required max="{{ today()->toDateString() }}" value="{{ old('transaction_date', $incentive->transaction_date?->toDateString() ?? today()->toDateString()) }}" class="mt-1 block w-full rounded-lg border p-2"></label>
                <label>Cabang<select name="branch_id" required class="mt-1 block w-full rounded-lg border p-2">@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id', $incentive->branch_id ?? auth()->user()->branch_id) == $branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
                <label>Mata uang<select name="currency" required class="mt-1 block w-full rounded-lg border p-2">@foreach(\App\Models\IncentiveSubmission::CURRENCIES as $code=>$label)<option value="{{ $code }}" @selected(old('currency', $incentive->currency ?? 'IDR') === $code)>{{ $label }}</option>@endforeach</select></label>
            </div>
        </section>

        <section class="rounded-xl border bg-white p-5 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2"><div><h2 class="font-bold">Barang</h2><p class="text-xs text-slate-500">Harga modal dan jual diisi per barang.</p></div><div class="text-right text-sm"><span class="text-slate-500">Total untung</span><strong id="profit-total" class="ml-2 text-lg text-emerald-700">0</strong></div></div>
            <div id="items" class="space-y-3">
                @foreach($initialItems as $i=>$item)
                <div class="item-row grid gap-3 rounded-lg border bg-slate-50 p-3 lg:grid-cols-12">
                    <label class="lg:col-span-3">Nama barang<input data-field="product_name" name="items[{{ $i }}][product_name]" required maxlength="150" value="{{ $item['product_name'] ?? '' }}" class="mt-1 block w-full rounded-lg border p-2"></label>
                    <label>RAM<input data-field="ram" name="items[{{ $i }}][ram]" maxlength="50" value="{{ $item['ram'] ?? '' }}" class="mt-1 block w-full rounded-lg border p-2" placeholder="8/256"></label>
                    <label>Warna<input data-field="color" name="items[{{ $i }}][color]" maxlength="80" value="{{ $item['color'] ?? '' }}" class="mt-1 block w-full rounded-lg border p-2"></label>
                    <label class="lg:col-span-2">Kondisi<select data-field="condition" name="items[{{ $i }}][condition]" required class="mt-1 block w-full rounded-lg border p-2">@foreach(\App\Models\IncentiveSubmission::CONDITIONS as $key=>$label)<option value="{{ $key }}" @selected(($item['condition'] ?? 'new') === $key)>{{ $label }}</option>@endforeach</select></label>
                    <label class="lg:col-span-2">IMEI<input data-field="imei" name="items[{{ $i }}][imei]" maxlength="100" value="{{ $item['imei'] ?? '' }}" class="mt-1 block w-full rounded-lg border p-2"></label>
                    <label class="lg:col-span-2">Harga modal<input data-field="capital_price" data-money name="items[{{ $i }}][capital_price]" inputmode="numeric" required value="{{ ($item['capital_price'] ?? '') !== '' ? number_format((float)$item['capital_price'],0,',','.') : '' }}" class="mt-1 block w-full rounded-lg border p-2"></label>
                    <label class="lg:col-span-2 lg:col-start-9">Harga jual<input data-field="sale_price" data-money name="items[{{ $i }}][sale_price]" inputmode="numeric" required value="{{ ($item['sale_price'] ?? '') !== '' ? number_format((float)$item['sale_price'],0,',','.') : '' }}" class="mt-1 block w-full rounded-lg border p-2"></label>
                    <div class="flex items-end lg:col-span-2"><button type="button" data-remove-item class="rounded-lg border border-rose-200 px-3 py-2 text-sm text-rose-600">Hapus barang</button></div>
                </div>
                @endforeach
            </div>
            <button type="button" id="add-item" class="rounded-lg border border-emerald-300 px-3 py-2 text-sm font-semibold text-emerald-700">+ Tambah barang</button>
        </section>

        <section class="rounded-xl border bg-white p-5 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="font-bold">Promotor penerima</h2><p class="text-xs text-slate-500">Jika lebih dari satu, nilai transaksi dibagi rata.</p></div><label class="flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 text-sm"><input id="cooperation" type="checkbox" name="is_cooperation" value="1" @checked(old('is_cooperation', $incentive->is_cooperation))> Kerja sama (50:50)</label></div>
            <div id="participants" class="space-y-3">
                @foreach($initialParticipants as $i=>$participant)
                <div class="participant-row grid gap-3 rounded-lg border p-3 md:grid-cols-4">
                    <label>Nama promotor<select data-field="user_id" name="participants[{{ $i }}][user_id]" required class="mt-1 block w-full rounded-lg border p-2"><option value="">Pilih pegawai</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected(($participant['user_id'] ?? null) == $employee->id)>{{ $employee->name }} — {{ $employee->branch?->name ?? 'Tanpa cabang' }}</option>@endforeach</select></label>
                    <label>Tipe insentif<select data-field="incentive_type" name="participants[{{ $i }}][incentive_type]" required class="mt-1 block w-full rounded-lg border p-2"><option value="fl" @selected(($participant['incentive_type'] ?? 'fl') === 'fl')>Promotor FL</option><option value="brand" @selected(($participant['incentive_type'] ?? '') === 'brand')>Promotor Brand</option></select></label>
                    <label class="cooperation-role">Peran kerja sama<select data-field="cooperation_role" name="participants[{{ $i }}][cooperation_role]" class="mt-1 block w-full rounded-lg border p-2"><option value="source" @selected(($participant['cooperation_role'] ?? '') === 'source')>Pencari customer</option><option value="closing" @selected(($participant['cooperation_role'] ?? '') === 'closing')>Penyelesai transaksi</option></select></label>
                    <div class="flex items-end"><button type="button" data-remove-participant class="rounded-lg border border-rose-200 px-3 py-2 text-sm text-rose-600">Hapus promotor</button></div>
                </div>
                @endforeach
            </div>
            <button type="button" id="add-participant" class="rounded-lg border border-emerald-300 px-3 py-2 text-sm font-semibold text-emerald-700">+ Tambah promotor</button>
        </section>

        <section class="rounded-xl border bg-white p-5 space-y-4">
            <div><h2 class="font-bold">Rating / follow</h2><p class="text-xs text-slate-500">Opsional. Centang yang dilakukan lalu wajib isi nama akun. Banyak platform dalam satu invoice tetap dihitung satu orang.</p></div>
            <label class="block max-w-md">Penerima insentif<select name="social_beneficiary_id" class="mt-1 block w-full rounded-lg border p-2"><option value="">Pilih pegawai</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected($socialBeneficiary == $employee->id)>{{ $employee->name }}</option>@endforeach</select></label>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach(\App\Models\IncentiveSubmission::PLATFORMS as $key=>$label)
                @php $value = old('social.'.$key, $savedSocial[$key] ?? ''); @endphp
                <div class="rounded-lg border p-3"><label class="flex items-center gap-2 font-medium"><input type="checkbox" data-social-check @checked(filled($value))> {{ $label }}</label><input data-social-input name="social[{{ $key }}]" value="{{ $value }}" maxlength="150" placeholder="Nama akun" class="mt-2 block w-full rounded-lg border p-2 {{ filled($value) ? '' : 'hidden' }}"></div>
                @endforeach
            </div>
        </section>

        <section class="rounded-xl border bg-white p-5"><label>Catatan<textarea name="notes" rows="3" maxlength="2000" class="mt-1 block w-full rounded-lg border p-2">{{ old('notes', $incentive->notes) }}</textarea></label></section>
        <div class="flex justify-end"><button class="rounded-lg bg-emerald-700 px-5 py-2.5 font-semibold text-white">{{ $editing ? 'Simpan perubahan' : 'Simpan & ajukan' }}</button></div>
    </form>
</div>

<template id="item-template"><div class="item-row grid gap-3 rounded-lg border bg-slate-50 p-3 lg:grid-cols-12"><label class="lg:col-span-3">Nama barang<input data-field="product_name" required maxlength="150" class="mt-1 block w-full rounded-lg border p-2"></label><label>RAM<input data-field="ram" maxlength="50" class="mt-1 block w-full rounded-lg border p-2"></label><label>Warna<input data-field="color" maxlength="80" class="mt-1 block w-full rounded-lg border p-2"></label><label class="lg:col-span-2">Kondisi<select data-field="condition" required class="mt-1 block w-full rounded-lg border p-2"><option value="new">Baru</option><option value="second">Second</option><option value="service">Servis</option></select></label><label class="lg:col-span-2">IMEI<input data-field="imei" maxlength="100" class="mt-1 block w-full rounded-lg border p-2"></label><label class="lg:col-span-2">Harga modal<input data-field="capital_price" data-money inputmode="numeric" required class="mt-1 block w-full rounded-lg border p-2"></label><label class="lg:col-span-2 lg:col-start-9">Harga jual<input data-field="sale_price" data-money inputmode="numeric" required class="mt-1 block w-full rounded-lg border p-2"></label><div class="flex items-end lg:col-span-2"><button type="button" data-remove-item class="rounded-lg border border-rose-200 px-3 py-2 text-sm text-rose-600">Hapus barang</button></div></div></template>
<template id="participant-template"><div class="participant-row grid gap-3 rounded-lg border p-3 md:grid-cols-4"><label>Nama promotor<select data-field="user_id" required class="mt-1 block w-full rounded-lg border p-2"><option value="">Pilih pegawai</option>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }} — {{ $employee->branch?->name ?? 'Tanpa cabang' }}</option>@endforeach</select></label><label>Tipe insentif<select data-field="incentive_type" required class="mt-1 block w-full rounded-lg border p-2"><option value="fl">Promotor FL</option><option value="brand">Promotor Brand</option></select></label><label class="cooperation-role">Peran kerja sama<select data-field="cooperation_role" class="mt-1 block w-full rounded-lg border p-2"><option value="source">Pencari customer</option><option value="closing">Penyelesai transaksi</option></select></label><div class="flex items-end"><button type="button" data-remove-participant class="rounded-lg border border-rose-200 px-3 py-2 text-sm text-rose-600">Hapus promotor</button></div></div></template>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const reindex = (container, rowClass, prefix) => document.querySelectorAll(`#${container} .${rowClass}`).forEach((row, i) => row.querySelectorAll('[data-field]').forEach(el => el.name = `${prefix}[${i}][${el.dataset.field}]`));
    const number = value => Number(String(value || '').replace(/\./g, '').replace(',', '.')) || 0;
    const formatMoney = input => { const digits = input.value.replace(/\D/g, ''); input.value = digits ? Number(digits).toLocaleString('id-ID') : ''; };
    const total = () => { let profit = 0; document.querySelectorAll('.item-row').forEach(row => profit += number(row.querySelector('[data-field="sale_price"]').value) - number(row.querySelector('[data-field="capital_price"]').value)); document.getElementById('profit-total').textContent = profit.toLocaleString('id-ID'); };
    const cooperation = () => { const on = document.getElementById('cooperation').checked; document.querySelectorAll('.cooperation-role').forEach(el => el.classList.toggle('hidden', !on)); document.getElementById('add-participant').classList.toggle('hidden', on && document.querySelectorAll('.participant-row').length >= 2); };
    document.querySelectorAll('[data-money]').forEach(formatMoney);
    document.addEventListener('input', e => { if (e.target.matches('[data-money]')) { formatMoney(e.target); total(); } });
    document.getElementById('add-item').onclick = () => { document.getElementById('items').append(document.getElementById('item-template').content.cloneNode(true)); reindex('items','item-row','items'); };
    document.getElementById('add-participant').onclick = () => { document.getElementById('participants').append(document.getElementById('participant-template').content.cloneNode(true)); reindex('participants','participant-row','participants'); const rows = document.querySelectorAll('.participant-row'); if (document.getElementById('cooperation').checked && rows.length === 2) rows[1].querySelector('[data-field="cooperation_role"]').value = 'closing'; cooperation(); };
    document.addEventListener('click', e => { if (e.target.matches('[data-remove-item]') && document.querySelectorAll('.item-row').length > 1) { e.target.closest('.item-row').remove(); reindex('items','item-row','items'); total(); } if (e.target.matches('[data-remove-participant]') && document.querySelectorAll('.participant-row').length > 1) { e.target.closest('.participant-row').remove(); reindex('participants','participant-row','participants'); cooperation(); } });
    document.getElementById('cooperation').onchange = cooperation;
    document.querySelectorAll('[data-social-check]').forEach(check => check.onchange = () => { const input = check.closest('div').querySelector('[data-social-input]'); input.classList.toggle('hidden', !check.checked); input.required = check.checked; if (!check.checked) input.value = ''; });
    document.getElementById('incentive-form').addEventListener('submit', () => document.querySelectorAll('[data-money]').forEach(input => input.value = input.value.replace(/\./g,'')));
    cooperation(); total();
});
</script>
@endsection
