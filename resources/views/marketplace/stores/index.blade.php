@extends('layouts.app')

@section('title', 'Toko Marketplace')

@section('content')
    @if($trashView ?? false)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('marketplace.stores.index') }}"
                   class="rounded-lg bg-white border border-slate-300 px-3 py-1.5 text-xs font-semibold hover:border-slate-400">
                    ← Kembali ke Toko
                </a>
                <p class="text-sm text-rose-700">🗑 Mode Sampah — item terhapus permanen otomatis setelah <b>7 hari</b>.</p>
            </div>
            <form method="POST" action="{{ route('marketplace.stores.trash.clear') }}"
                  onsubmit="return confirm('Hapus PERMANEN semua isi sampah? Tidak bisa dibatalkan.')">
                @csrf @method('DELETE')
                <button class="rounded-lg bg-rose-600 text-white text-xs font-bold px-3 py-1.5">Kosongkan Sampah</button>
            </form>
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-2 mb-5">
        <h1 class="text-xl font-bold">Toko Marketplace @if($trashView ?? false)<span class="text-slate-400 font-normal">— Sampah</span>@endif</h1>
        @unless($trashView ?? false)
        <a href="{{ route('marketplace.stores.trash') }}"
           class="rounded-lg bg-white border border-slate-300 px-4 py-2 text-sm font-semibold hover:border-rose-300 text-slate-600">🗑 Sampah</a>
        @endunless
    </div>

    @unless($trashView ?? false)
    <form method="POST" action="{{ route('marketplace.stores.store') }}"
          class="bg-white rounded-xl border border-slate-200 p-5 flex flex-wrap gap-3 items-end mb-5">
        @csrf
        <div class="flex-1 min-w-40">
            <label class="block text-xs font-semibold text-slate-600 mb-1">Nama toko</label>
            <input type="text" name="name" required placeholder="JJ Official"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div class="flex-1 min-w-32">
            <label class="block text-xs font-semibold text-slate-600 mb-1">Marketplace</label>
            <input type="text" name="marketplace" required placeholder="shopee / tiktok" list="mp-list"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <datalist id="mp-list">
                @foreach($stores->pluck('marketplace')->unique() as $mp)
                    <option value="{{ $mp }}">
                @endforeach
            </datalist>
        </div>
        <label class="flex items-center gap-1.5 text-sm text-slate-600 pb-2.5">
            <input type="checkbox" name="is_mall" value="1" class="rounded"> Toko Mall
        </label>
        <button class="rounded-lg bg-emerald-500 text-white text-sm font-semibold px-4 py-2">+ Tambah</button>
    </form>
    @endunless

    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-500 uppercase tracking-wide bg-slate-50">
                <tr>
                    <th class="px-4 py-3">Toko</th>
                    <th class="px-4 py-3">Marketplace</th>
                    <th class="px-4 py-3">PIC</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($stores as $s)
                    <tr class="hover:bg-slate-50 {{ ! $s->is_active ? 'opacity-50' : '' }}">
                        <td class="px-4 py-3 font-semibold">
                            {{ $s->name }}
                            @if($s->is_mall)
                                <span class="ml-1 px-1.5 py-0.5 rounded bg-violet-100 text-violet-700 text-[10px]">Mall</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ ucfirst($s->marketplace) }}</td>
                        <td class="px-4 py-3 text-xs text-slate-600">
                            {{ $s->pics->pluck('name')->join(', ') ?: '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-medium {{ $s->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">
                                {{ $s->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if($trashView ?? false)
                                <form method="POST" action="{{ route('marketplace.stores.restore', $s->id) }}" class="inline">
                                    @csrf @method('PATCH')
                                    <button class="text-sky-600 text-xs font-semibold hover:underline">Pulihkan</button>
                                </form>
                            @else
                                <a href="{{ route('marketplace.stores.edit', $s) }}" class="text-emerald-700 text-xs font-semibold hover:underline">Edit</a>
                                <form method="POST" action="{{ route('marketplace.stores.destroy', $s) }}" class="inline ml-2"
                                      onsubmit="return confirm('Pindahkan {{ $s->name }} ke sampah? Terhapus permanen setelah 7 hari.')">
                                    @csrf @method('DELETE')
                                    <button class="text-rose-500 text-xs font-semibold hover:underline">Hapus</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Belum ada toko.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @unless($trashView ?? false)
        <p class="text-[11px] text-slate-400 mt-2">Toko yang dihapus masuk sampah 7 hari sebelum terhapus permanen.</p>
    @endunless
@endsection
