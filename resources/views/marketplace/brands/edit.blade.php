@extends('layouts.app')

@section('title', 'Edit Brand '.$brand->name)

@section('content')
    <a href="{{ route('marketplace.brands.index') }}" class="text-sm text-slate-500 hover:underline">← Brand</a>
    <h1 class="text-xl font-bold mt-2 mb-5">Edit Brand — {{ $brand->name }}</h1>

    <form method="POST" action="{{ route('marketplace.brands.update', $brand) }}"
          class="bg-white rounded-xl border border-slate-200 p-5 max-w-lg space-y-4">
        @csrf @method('PUT')

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Nama brand *</label>
            <input type="text" name="name" value="{{ old('name', $brand->name) }}" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div>
            <p class="text-xs font-semibold text-slate-600 mb-2">Diposting ke toko:</p>
            <div class="space-y-1.5">
                @foreach($stores as $s)
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="stores[]" value="{{ $s->id }}" class="rounded"
                               @checked($brand->stores->contains($s->id))>
                        {{ $s->label() }}
                    </label>
                @endforeach
            </div>

            <div class="border-t border-slate-100 pt-4">
            <p class="text-xs font-semibold text-slate-600 mb-1">PIC Brand (penanggung jawab posting)</p>
            <p class="text-[11px] text-slate-400 mb-2">
                PIC brand mengerjakan SEMUA tugas produk brand ini di semua toko targetnya.
                Toko baru yang ditambahkan ke target otomatis masuk antrian PIC — tanpa assign ulang.
            </p>
            <div class="flex flex-wrap gap-2">
                    @foreach($users as $usr)
                        <label class="flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-sm cursor-pointer hover:bg-slate-50">
                            <input type="checkbox" name="pics[]" value="{{ $usr->id }}" class="rounded"
                                @checked($brand->pics->contains($usr->id))>
                            {{ $usr->name }}
                        </label>
                    @endforeach
                </div>
            </div>

            @if($stores->isEmpty())
                <p class="text-xs text-amber-600">Belum ada toko aktif — tambahkan toko dulu.</p>
            @endif
        </div>

        <div class="flex items-center gap-3 pt-1">
            <button class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white px-5 py-2 text-sm font-bold">Simpan</button>
            <a href="{{ route('marketplace.brands.index') }}" class="text-sm text-slate-500 hover:underline">Batal</a>
        </div>
    </form>
@endsection
