@extends('layouts.app')

@section('title', 'Brand')

@section('content')
    @if($trashView ?? false)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('marketplace.brands.index') }}"
                   class="rounded-lg bg-white border border-slate-300 px-3 py-1.5 text-xs font-semibold hover:border-slate-400">
                    ← Kembali ke Brand
                </a>
                <p class="text-sm text-rose-700">🗑 Mode Sampah — item terhapus permanen otomatis setelah <b>7 hari</b>.</p>
            </div>
            <form method="POST" action="{{ route('marketplace.brands.trash.clear') }}"
                  onsubmit="return confirm('Hapus PERMANEN semua isi sampah? Tidak bisa dibatalkan.')">
                @csrf @method('DELETE')
                <button class="rounded-lg bg-rose-600 text-white text-xs font-bold px-3 py-1.5">Kosongkan Sampah</button>
            </form>
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
        <h1 class="text-xl font-bold">Brand @if($trashView ?? false)<span class="text-slate-400 font-normal">— Sampah</span>@endif</h1>
        @unless($trashView ?? false)
        <a href="{{ route('marketplace.brands.trash') }}"
           class="rounded-lg bg-white border border-slate-300 px-4 py-2 text-sm font-semibold hover:border-rose-300 text-slate-600">🗑 Sampah</a>
        @endunless
    </div>

    @unless($trashView ?? false)
    <p class="text-sm text-slate-500 mb-5">Pemetaan brand → toko menentukan ke mana produk brand itu diposting otomatis (M2).</p>

    <form method="POST" action="{{ route('marketplace.brands.store') }}"
          class="bg-white rounded-xl border border-slate-200 p-5 flex gap-3 items-end mb-5 max-w-lg">
        @csrf
        <div class="flex-1">
            <label class="block text-xs font-semibold text-slate-600 mb-1">Nama brand</label>
            <input type="text" name="name" required placeholder="Oppo"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <button class="rounded-lg bg-emerald-500 text-white text-sm font-semibold px-4 py-2">+ Tambah</button>
    </form>
    @endunless

    <div class="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100">
        @forelse($brands as $b)
            <div class="px-4 py-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                <div>
                    <p class="font-semibold">{{ $b->name }}</p>
                    <p class="text-xs text-slate-500">
                        Diposting ke:
                        {{ $b->stores->map(fn ($s) => $s->label())->join(', ') ?: '— belum dipetakan ke toko manapun' }}
                    </p>
                    <p class="text-xs text-slate-400">PIC: {{ $b->pics->pluck('name')->join(', ') ?: '— belum ada' }}</p>
                </div>
                <div class="whitespace-nowrap">
                    @if($trashView ?? false)
                        <form method="POST" action="{{ route('marketplace.brands.restore', $b->id) }}" class="inline">
                            @csrf @method('PATCH')
                            <button class="text-sky-600 text-xs font-semibold hover:underline">Pulihkan</button>
                        </form>
                    @else
                        <a href="{{ route('marketplace.brands.edit', $b) }}" class="text-emerald-700 text-xs font-semibold hover:underline">Edit</a>
                        <form method="POST" action="{{ route('marketplace.brands.destroy', $b) }}" class="inline ml-2"
                              onsubmit="return confirm('Pindahkan {{ $b->name }} ke sampah? Terhapus permanen setelah 7 hari.')">
                            @csrf @method('DELETE')
                            <button class="text-rose-500 text-xs font-semibold hover:underline">Hapus</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <p class="px-4 py-8 text-center text-sm text-slate-400">Belum ada brand.</p>
        @endforelse
    </div>
@endsection
