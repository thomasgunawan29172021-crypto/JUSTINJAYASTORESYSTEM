@extends('layouts.app')

@section('title', 'Pengaturan Cabang')

@section('content')
    <h1 class="text-xl font-bold mb-1">Pengaturan Cabang</h1>
    <p class="text-sm text-slate-500 mb-5">
        Koordinat dipakai geofence absensi. Ambil dari Google Maps: klik kanan titik toko → klik angka koordinat untuk copy.
    </p>

    <div class="grid lg:grid-cols-2 gap-4">
        @foreach($branches as $b)
            <form method="POST" action="{{ route('branches.update', $b) }}"
                  class="bg-white rounded-xl border border-slate-200 p-5 space-y-3">
                @csrf @method('PUT')
                <p class="font-bold">{{ $b->name }} <span class="text-xs text-slate-400 font-mono">({{ $b->code }})</span></p>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Alamat</label>
                    <input type="text" name="address" value="{{ old('address', $b->address) }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Telp/WA cabang</label>
                    <input type="text" name="phone" value="{{ old('phone', $b->phone) }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Latitude</label>
                        <input type="text" name="latitude" value="{{ old('latitude', $b->latitude) }}" placeholder="-2.9761"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Longitude</label>
                        <input type="text" name="longitude" value="{{ old('longitude', $b->longitude) }}" placeholder="104.7754"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Radius (m)</label>
                        <input type="number" name="geofence_radius_m" value="{{ old('geofence_radius_m', $b->geofence_radius_m) }}"
                               min="20" max="1000" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                </div>
                <button class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-semibold px-4 py-2">Simpan</button>
            </form>
        @endforeach
    </div>
@endsection