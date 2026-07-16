@extends('layouts.app')

@section('title', 'Pengaturan Cabang')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
        <h1 class="text-xl font-bold">Pengaturan Cabang</h1>
        <a href="{{ route('branches.create') }}"
           class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-semibold px-4 py-2">+ Cabang Baru</a>
    </div>
    <p class="text-sm text-slate-500 mb-5">
        Koordinat dipakai geofence absensi. Ambil dari Google Maps: klik kanan titik toko → klik angka koordinat untuk copy.
    </p>

    <div class="grid lg:grid-cols-2 gap-4">
        @foreach($branches as $b)
            <div>
                <form method="POST" action="{{ route('branches.update', $b) }}"
                      class="bg-white rounded-xl border border-slate-200 p-5 space-y-3">
                    @csrf @method('PUT')
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-bold">{{ $b->name }} <span class="text-xs text-slate-400 font-mono">({{ $b->code }})</span></p>
                            <p class="text-[11px] {{ $b->users_count > 0 ? 'text-slate-400' : 'text-emerald-600' }}">
                                {{ $b->users_count > 0 ? '🔒 terikat '.$b->users_count.' karyawan — tidak bisa dihapus' : '✓ tidak ada karyawan terikat' }}
                            </p>
                        </div>
                    </div>

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
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="has_service" value="1" @checked(old('has_service', $b->has_service)) class="rounded">
                        Punya layanan servis
                    </label>
                    <button class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-semibold px-4 py-2">Simpan</button>
                </form>
                <form method="POST" action="{{ route('branches.destroy', $b) }}" class="mt-2"
                      onsubmit="return confirm('Hapus cabang {{ $b->name }}? Ditolak otomatis kalau masih ada data terikat.')">
                    @csrf @method('DELETE')
                    <button class="text-xs font-semibold text-rose-500 hover:underline">🗑 Hapus cabang ini</button>
                </form>
            </div>
        @endforeach
    </div>
@endsection