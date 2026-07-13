@extends('layouts.app')

@section('title', 'Terima Unit Servis')

@php
    $kondisiOptions   = ['Layar retak', 'Body lecet', 'Frame penyok', 'Backdoor pecah', 'Bekas air', 'Mulus'];
    $aksesorisOptions = ['SIM card', 'Memory card', 'Case', 'Charger', 'Dus'];
    $oldKondisi   = old('physical_condition', []);
    $oldAksesoris = old('accessories', []);
@endphp

@section('content')
    <h1 class="text-xl font-bold">Terima Unit Servis</h1>
    <p class="text-sm text-slate-500 mt-1 mb-5">
        Lengkapi data di bawah, cetak nota, serahkan ke customer. Foto kondisi fisik <b>wajib</b> — pelindung kita dari komplain.
    </p>

    <form method="POST" action="{{ route('service.tickets.store') }}" enctype="multipart/form-data" class="space-y-5 w-full">
        @csrf

        {{-- CUSTOMER --}}
        <section class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-xs font-semibold tracking-widest text-slate-500 uppercase mb-4">Customer</h2>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Nama *</label>
                    <input type="text" name="customer_name" value="{{ old('customer_name') }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">No. HP / WhatsApp * <span class="font-normal text-slate-400">(dipakai untuk lacak servis)</span></label>
                    <input type="text" name="customer_phone" value="{{ old('customer_phone') }}" required placeholder="08xxxxxxxxxx"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">No. HP alternatif</label>
                    <input type="text" name="customer_phone_alt" value="{{ old('customer_phone_alt') }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Cabang *</label>
                    <select name="branch_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" @selected(old('branch_id', auth()->user()->branch_id) == $b->id)>
                                {{ $b->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        {{-- UNIT --}}
        <section class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-xs font-semibold tracking-widest text-slate-500 uppercase mb-4">Unit</h2>
            <div class="grid sm:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Brand *</label>
                    <input type="text" name="device_brand" value="{{ old('device_brand') }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Model *</label>
                    <input type="text" name="device_model" value="{{ old('device_model') }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">IMEI</label>
                    <input type="text" name="imei" value="{{ old('imei') }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Keluhan customer *</label>
                <textarea name="complaint" rows="3" required
                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('complaint') }}</textarea>
            </div>

            <div class="mb-4">
                <label class="block text-xs font-semibold text-slate-600 mb-1">
                    Passcode / pola unit <span class="font-normal text-slate-400">(untuk testing — tersimpan terenkripsi)</span>
                </label>
                <input type="text" name="device_passcode" value="{{ old('device_passcode') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <p class="text-xs font-semibold text-slate-600 mb-2">Kondisi fisik saat masuk</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($kondisiOptions as $k)
                            <label class="flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-sm cursor-pointer has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                                <input type="checkbox" name="physical_condition[]" value="{{ $k }}" @checked(in_array($k, $oldKondisi))>
                                {{ $k }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-600 mb-2">Kelengkapan yang ikut</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($aksesorisOptions as $a)
                            <label class="flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-sm cursor-pointer has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                                <input type="checkbox" name="accessories[]" value="{{ $a }}" @checked(in_array($a, $oldAksesoris))>
                                {{ $a }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- PENUGASAN & LAINNYA --}}
        <section class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-xs font-semibold tracking-widest text-slate-500 uppercase mb-4">Penugasan & Lainnya</h2>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Estimasi selesai</label>
                    <input type="date" name="estimated_done_at" value="{{ old('estimated_done_at') }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Teknisi</label>
                    <select name="technician_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
                        <option value="">— Belum ditentukan —</option>
                        @foreach($technicians as $u)
                            <option value="{{ $u->id }}" @selected(old('technician_id') == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Admin chat</label>
                    <select name="admin_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
                        <option value="">— Belum ditentukan —</option>
                        @foreach($admins as $u)
                            <option value="{{ $u->id }}" @selected(old('admin_id') == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Garansi (hari)</label>
                    <input type="number" name="warranty_days" value="{{ old('warranty_days', 30) }}" min="0" max="365"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Catatan internal</label>
                <textarea name="notes" rows="2"
                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('notes') }}</textarea>
            </div>
        </section>

        {{-- FOTO --}}
        <section class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-xs font-semibold tracking-widest text-slate-500 uppercase mb-2">Foto Kondisi Fisik</h2>
            <p class="text-xs text-slate-400 mb-3">Maksimal 6 foto, per foto maks 4 MB.</p>
            <input type="file" name="photos[]" multiple accept="image/*" class="text-sm">
        </section>

        <div class="flex gap-2">
            <button class="rounded-lg bg-emerald-500 text-white px-6 py-2.5 text-sm font-semibold hover:bg-emerald-600">
                Simpan & Buat Nota
            </button>
            <a href="{{ route('service.tickets.index') }}"
               class="rounded-lg bg-white border border-slate-300 px-6 py-2.5 text-sm font-semibold hover:border-slate-400">
                Batal
            </a>
        </div>
    </form>
@endsection