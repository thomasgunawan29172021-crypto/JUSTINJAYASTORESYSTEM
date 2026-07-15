@extends('layouts.app')
@section('title', 'Edit '.$product->name)
@section('content')
    <a href="{{ route('marketplace.products.index') }}" class="text-sm text-slate-500 hover:underline">← Produk</a>
    <h1 class="text-xl font-bold mt-2 mb-5">Edit — {{ $product->name }}</h1>
    <form method="POST" action="{{ route('marketplace.products.update', $product) }}"
          class="bg-white rounded-xl border border-slate-200 p-5 max-w-2xl space-y-4">
        @csrf @method('PUT')
        @include('marketplace.products._fields')
        <button class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white px-5 py-2 text-sm font-bold">Simpan</button>
    </form>
    @if($targetStores->isNotEmpty())
        <div class="bg-white rounded-xl border border-slate-200 p-5 max-w-2xl mt-4">
            <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-1">📍 Status Posting per Toko</h2>
            <p class="text-xs text-slate-500 mb-3">
                Centang toko yang produknya <b>sudah tayang</b>. Perubahan di sini <b>tidak dikreditkan ke PIC manapun</b> —
                untuk koreksi / input mundur, bukan pengganti tugas. Mencentang toko juga membuang tugas posting-nya dari antrian PIC.
            </p>

            <form method="POST" action="{{ route('marketplace.products.postings.update', $product) }}"
                  onsubmit="return confirm('Simpan status posting? Toko yang di-uncheck akan kehilangan catatan posting-nya (termasuk siapa yang mengerjakan).')">
                @csrf @method('PUT')
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-slate-400">
                        <tr><th class="py-1.5 w-8"></th><th>Toko</th><th>Diposting oleh</th><th>Tanggal</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($targetStores as $s)
                            @php $post = $postingMap[$s->id] ?? null; @endphp
                            <tr>
                                <td class="py-2">
                                    <input type="checkbox" name="posted_stores[]" value="{{ $s->id }}"
                                           class="rounded accent-emerald-500" @checked($post)>
                                </td>
                                <td class="py-2 font-medium">{{ $s->label() }}</td>
                                <td class="py-2 text-xs">
                                    @if($post)
                                        {{ $post->poster?->name ?? $post->corrector?->name ?? '—' }}
                                        @unless($post->poster)<span class="text-[10px] text-slate-400">(koreksi)</span>@endunless
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="py-2 text-xs text-slate-400">{{ $post?->posted_at?->format('d/m/Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <button class="mt-3 rounded-lg bg-slate-900 hover:bg-slate-800 text-white px-4 py-2 text-sm font-semibold">
                    Simpan Status Posting
                </button>
            </form>
        </div>
    @else
        <p class="text-xs text-amber-600 mt-4">Brand produk ini belum dipetakan ke toko manapun — atur di menu Brand dulu.</p>
    @endif
@endsection
