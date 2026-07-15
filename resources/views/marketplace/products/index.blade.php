@extends('layouts.app')

@section('title', 'Produk')

@php $rp = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.'); @endphp

@section('content')
    @if($trashView ?? false)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('marketplace.products.index') }}"
                   class="rounded-lg bg-white border border-slate-300 px-3 py-1.5 text-xs font-semibold hover:border-slate-400">
                    ← Kembali ke Produk
                </a>
                <p class="text-sm text-rose-700">🗑 Mode Sampah — item terhapus permanen otomatis setelah <b>7 hari</b>.</p>
            </div>
            <form method="POST" action="{{ route('marketplace.products.trash.clear') }}"
                  onsubmit="return confirm('Hapus PERMANEN semua isi sampah? Tidak bisa dibatalkan.')">
                @csrf @method('DELETE')
                <button class="rounded-lg bg-rose-600 text-white text-xs font-bold px-3 py-1.5">Kosongkan Sampah</button>
            </form>
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
        <h1 class="text-xl font-bold">Produk @if($trashView ?? false)<span class="text-slate-400 font-normal">— Sampah</span>@endif</h1>
        @unless($trashView ?? false)
        <div class="flex gap-2">
            <a href="{{ route('marketplace.products.import.form') }}"
               class="rounded-lg bg-white border border-slate-300 px-4 py-2 text-sm font-semibold hover:border-slate-400">📥 Import CSV</a>
            <a href="{{ route('marketplace.products.export') }}"
               class="rounded-lg bg-white border border-slate-300 px-4 py-2 text-sm font-semibold hover:border-slate-400">📤 Export CSV</a>
            <a href="{{ route('marketplace.products.trash') }}"
               class="rounded-lg bg-white border border-slate-300 px-4 py-2 text-sm font-semibold hover:border-rose-300 text-slate-600">🗑 Sampah</a>
            <a href="{{ route('marketplace.products.create') }}"
               class="rounded-lg bg-emerald-500 text-white px-4 py-2 text-sm font-semibold hover:bg-emerald-400">+ Produk</a>
        </div>
        @endunless
    </div>

    @unless($trashView ?? false)
    <form method="GET" class="flex flex-wrap gap-2 mb-4">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama / barcode / SKU..."
               class="flex-1 min-w-48 rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
        <select name="brand_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
            <option value="">Semua brand</option>
            @foreach($brands as $b)
                <option value="{{ $b->id }}" @selected(request('brand_id') == $b->id)>{{ $b->name }}</option>
            @endforeach
        </select>
        <select name="sort" class="rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white" onchange="this.form.submit()">
            <option value="name_asc"   @selected(request('sort','name_asc')==='name_asc')>Nama A–Z</option>
            <option value="name_desc"  @selected(request('sort')==='name_desc')>Nama Z–A</option>
            <option value="date_newest" @selected(request('sort')==='date_newest')>Terbaru dulu</option>
            <option value="date_oldest" @selected(request('sort')==='date_oldest')>Terlama dulu</option>
        </select>
        <label class="flex items-center gap-1.5 text-sm text-slate-600 px-2">
            <input type="checkbox" name="archived" value="1" @checked(request('archived')) onchange="this.form.submit()" class="rounded">
            Tampilkan arsip
        </label>
        <button class="rounded-lg bg-slate-900 text-white px-5 py-2 text-sm font-semibold">Cari</button>
    </form>
    @endunless

    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-500 uppercase tracking-wide bg-slate-50">
                <tr>
                    <th class="px-4 py-3">Produk</th>
                    <th class="px-3 py-3">Brand</th>
                    <th class="px-3 py-3" title="Terposting / target toko brand">Posted</th>
                    <th class="px-3 py-3" title="Rahasia — hanya terlihat di halaman CEO ini">Beli</th>
                    <th class="px-3 py-3">Offline</th>
                    <th class="px-3 py-3">Grosir</th>
                    @foreach($marketplaces as $mp)
                        <th class="px-3 py-3">{{ ucfirst($mp) }} (mall/biasa)</th>
                    @endforeach
                    <th class="px-3 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($products as $p)
                    <tr class="hover:bg-slate-50 {{ $p->isArchived() ? 'opacity-50' : '' }}">
                        <td class="px-4 py-2.5 font-semibold">
                            {{ $p->name }}
                            @if($p->isArchived())
                                <span class="ml-1 px-1.5 py-0.5 rounded bg-slate-200 text-slate-600 text-[10px]">arsip</span>
                                @if($p->replacement)
                                    <p class="text-[11px] text-slate-400 font-normal">pengganti: {{ $p->replacement->name }}</p>
                                @endif
                            @endif
                            @if($p->barcode || $p->sku)
                                <p class="text-[11px] text-slate-400 font-normal">
                                    @if($p->sku)SKU: {{ $p->sku }}@endif
                                    @if($p->barcode)<span class="@if($p->sku)ml-2 @endif">🔖 {{ $p->barcode }}</span>@endif
                                </p>
                            @endif
                        </td>
                        <td class="px-3 py-2.5">{{ $p->brand->name }}</td>
                        @php
                            $posted = ($postedCounts ?? collect())[$p->id] ?? 0;
                            $target = ($targetPerBrand ?? collect())[$p->brand_id] ?? 0;
                        @endphp
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            <button type="button" data-posted-open="pm-{{ $p->id }}"
                                    class="font-semibold hover:underline {{ $target > 0 && $posted >= $target ? 'text-emerald-700' : ($posted === 0 ? 'text-rose-600' : 'text-amber-600') }}">
                                {{ $posted }}<span class="text-slate-400 text-xs font-normal">/{{ $target }}</span>
                            </button>
                        </td>
                        <td class="px-3 py-2.5 text-slate-500">{{ $rp($p->cost_price) }}</td>
                        <td class="px-3 py-2.5">{{ $rp($p->price_offline) }}</td>
                        <td class="px-3 py-2.5">{{ $rp($p->price_grosir) }}</td>
                        @foreach($marketplaces as $mp)
                            @php $row = $p->prices->firstWhere('marketplace', $mp); @endphp
                            <td class="px-3 py-2.5 text-xs">
                                {{ $row?->price_mall ? $rp($row->price_mall) : '—' }} / {{ $row?->price_regular ? $rp($row->price_regular) : '—' }}
                            </td>
                        @endforeach
                        <td class="px-3 py-2.5 text-right whitespace-nowrap">
                            @if($trashView ?? false)
                                <form method="POST" action="{{ route('marketplace.products.restore', $p->id) }}" class="inline">
                                    @csrf @method('PATCH')
                                    <button class="text-sky-600 text-xs font-semibold hover:underline">Pulihkan</button>
                                </form>
                            @else
                                <a href="{{ route('marketplace.products.edit', $p) }}" class="text-emerald-700 text-xs font-semibold hover:underline">Edit</a>
                                <form method="POST" action="{{ route('marketplace.products.archive', $p) }}" class="inline ml-2">
                                    @csrf @method('PATCH')
                                    @if($p->isArchived())
                                        <button class="text-sky-600 text-xs font-semibold hover:underline">Aktifkan</button>
                                    @else
                                        <button onclick="return confirm('Arsipkan {{ $p->name }}?')"
                                                class="text-slate-400 text-xs font-semibold hover:underline">Arsip</button>
                                    @endif
                                </form>
                                <form method="POST" action="{{ route('marketplace.products.destroy', $p) }}" class="inline ml-2"
                                      onsubmit="return confirm('Pindahkan {{ $p->name }} ke sampah? Terhapus permanen setelah 7 hari.')">
                                    @csrf @method('DELETE')
                                    <button class="text-rose-500 text-xs font-semibold hover:underline">Hapus</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 6 + $marketplaces->count() }}" class="px-4 py-8 text-center text-slate-400">Belum ada produk.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @unless($trashView ?? false)
        <div class="mt-4">{{ $products->links() }}</div>
    @endunless
    {{-- Modal detail posting per produk (read-only; edit di halaman Edit Produk) --}}
    @foreach($products as $p)
        <div id="pm-{{ $p->id }}" data-posted-modal class="hidden fixed inset-0 z-50 items-center justify-center p-4" style="background:rgba(15,23,42,.45);">
            <div class="bg-white rounded-2xl border border-slate-200 w-full max-w-lg max-h-[80vh] overflow-y-auto p-5">
                <div class="flex items-start justify-between gap-2 mb-3">
                    <div>
                        <h3 class="font-bold">{{ $p->name }}</h3>
                        <p class="text-xs text-slate-400">{{ $p->brand->name }} · sudah posting di mana saja</p>
                    </div>
                    <button type="button" data-posted-close class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 shrink-0">×</button>
                </div>

                @php $pmap = $p->postings->keyBy('store_id'); $tstores = $p->brand->stores; @endphp
                @if($tstores->isEmpty())
                    <p class="text-sm text-amber-600">Brand ini belum dipetakan ke toko manapun.</p>
                @else
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs text-slate-400">
                            <tr><th class="py-1.5">Toko</th><th>Status</th><th>Oleh</th><th>Tanggal</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($tstores as $s)
                                @php $post = $pmap[$s->id] ?? null; @endphp
                                <tr>
                                    <td class="py-2">{{ $s->label() }}</td>
                                    <td class="py-2">
                                        @if($post)
                                            <span class="px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 text-[10px] font-semibold">sudah</span>
                                        @else
                                            <span class="px-1.5 py-0.5 rounded bg-rose-100 text-rose-700 text-[10px] font-semibold">belum</span>
                                        @endif
                                    </td>
                                    <td class="py-2 text-xs">{{ $post ? ($post->poster?->name ?? $post->corrector?->name ?? '—') : '—' }}</td>
                                    <td class="py-2 text-xs text-slate-400">{{ $post?->posted_at?->format('d/m/Y') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                <a href="{{ route('marketplace.products.edit', $p) }}"
                   class="inline-block mt-4 text-xs font-semibold text-emerald-700 hover:underline">✎ Ubah status posting di halaman Edit →</a>
            </div>
        </div>
    @endforeach

    <script>
    (function () {
        function close() {
            document.querySelectorAll('[data-posted-modal]').forEach(function (m) {
                m.classList.add('hidden'); m.classList.remove('flex');
            });
        }
        document.querySelectorAll('[data-posted-open]').forEach(function (b) {
            b.addEventListener('click', function () {
                close();
                var m = document.getElementById(b.getAttribute('data-posted-open'));
                if (m) { m.classList.remove('hidden'); m.classList.add('flex'); }
            });
        });
        document.querySelectorAll('[data-posted-close]').forEach(function (b) { b.addEventListener('click', close); });
        document.querySelectorAll('[data-posted-modal]').forEach(function (m) {
            m.addEventListener('click', function (e) { if (e.target === m) close(); });
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    })();
    </script>
@endsection
