@extends('layouts.app')

@section('title', 'Edit '.$store->name)

@section('content')
    <a href="{{ route('marketplace.stores.index') }}" class="text-sm text-slate-500 hover:underline">← Toko Marketplace</a>
    <h1 class="text-xl font-bold mt-2 mb-5">Edit Toko — {{ $store->name }}</h1>

    <form method="POST" action="{{ route('marketplace.stores.update', $store) }}"
          class="bg-white rounded-xl border border-slate-200 p-5 max-w-lg space-y-4">
        @csrf @method('PUT')

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Nama toko *</label>
            <input type="text" name="name" value="{{ old('name', $store->name) }}" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Marketplace *</label>
            <input type="text" name="marketplace" value="{{ old('marketplace', $store->marketplace) }}" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="is_mall" value="1" @checked(old('is_mall', $store->is_mall)) class="rounded">
            Toko Mall <span class="text-slate-400">(menentukan harga mall/non-mall yang dipakai)</span>
        </label>

        <div>
            <p class="text-xs font-semibold text-slate-600 mb-2">PIC toko ini (boleh lebih dari satu)</p>
            <div class="flex flex-wrap gap-2">
                @foreach($users as $u)
                    <label class="flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-sm cursor-pointer hover:bg-slate-50">
                        <input type="checkbox" name="pics[]" value="{{ $u->id }}" class="rounded"
                               @checked($store->pics->contains($u->id))>
                        {{ $u->name }} <span class="text-slate-400 text-xs">({{ $u->role->label() }})</span>
                    </label>
                @endforeach
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $store->is_active)) class="rounded">
            Toko aktif
        </label>

        <div class="flex items-center gap-3 pt-1">
            <button class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white px-5 py-2 text-sm font-bold">Simpan</button>
            <a href="{{ route('marketplace.stores.index') }}" class="text-sm text-slate-500 hover:underline">Batal</a>
        </div>
    </form>
@endsection
