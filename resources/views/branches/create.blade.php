@extends('layouts.app')

@section('title', 'Cabang Baru')

@section('content')
    <a href="{{ route('branches.index') }}" class="text-sm text-slate-500 hover:underline">← Pengaturan Cabang</a>
    <h1 class="text-xl font-bold mt-2 mb-5">Cabang Baru</h1>

    <form method="POST" action="{{ route('branches.store') }}"
          class="bg-white rounded-xl border border-slate-200 p-5 max-w-lg space-y-4">
        @csrf

        <div class="grid sm:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Kode * <span class="font-normal text-slate-400">(unik)</span></label>
                <input type="text" name="code" value="{{ old('code') }}" required maxlength="10" placeholder="KM5"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase">
            </div>
            <div class="sm:col-span-3">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Nama cabang *</label>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="100" placeholder="Cabang KM 5"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Alamat</label>
            <input type="text" name="address" value="{{ old('address') }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Telp/WA cabang</label>
            <input type="text" name="phone" value="{{ old('phone') }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div class="grid grid-cols-3 gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Latitude</label>
                <input type="text" name="latitude" value="{{ old('latitude') }}" placeholder="-2.9761"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Longitude</label>
                <input type="text" name="longitude" value="{{ old('longitude') }}" placeholder="104.7754"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Radius (m) *</label>
                <input type="number" name="geofence_radius_m" value="{{ old('geofence_radius_m', 100) }}" min="20" max="1000" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>
        <p class="text-[11px] text-slate-400 -mt-2">Ambil dari Google Maps: klik kanan titik toko → klik angka koordinat untuk copy.</p>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="has_service" value="1" @checked(old('has_service', true)) class="rounded">
            Punya layanan servis <span class="text-slate-400 text-xs">(muncul di modul Servis & KPI)</span>
        </label>

        <div class="flex items-center gap-3 pt-1">
            <button class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white px-5 py-2 text-sm font-bold">Simpan</button>
            <a href="{{ route('branches.index') }}" class="text-sm text-slate-500 hover:underline">Batal</a>
        </div>
    </form>
@endsection
