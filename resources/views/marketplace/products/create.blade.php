@extends('layouts.app')
@section('title', 'Produk Baru')
@section('content')
    <a href="{{ route('marketplace.products.index') }}" class="text-sm text-slate-500 hover:underline">← Produk</a>
    <h1 class="text-xl font-bold mt-2 mb-5">Produk Baru</h1>
    <form method="POST" action="{{ route('marketplace.products.store') }}"
          data-draft="product-create"
          class="bg-white rounded-xl border border-slate-200 p-5 max-w-2xl space-y-4">
        @csrf
        @include('marketplace.products._fields')

        <div class="border-t border-slate-100 pt-4">
            <p class="text-xs font-semibold text-slate-600 mb-1">Sudah terposting di toko (input mundur)</p>
            <p class="text-[11px] text-slate-400 mb-2">
                Centang toko yang SUDAH ada postingan produk ini — tidak dibuatkan tugas.
                Hanya toko yang terpetakan ke brand produk yang dihitung; centangan di toko lain diabaikan.
            </p>
            <div class="flex flex-wrap gap-2">
                @foreach($stores as $s)
                    <label class="flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-sm cursor-pointer hover:bg-slate-50">
                        <input type="checkbox" name="posted_stores[]" value="{{ $s->id }}" class="rounded">
                        {{ $s->label() }}
                    </label>
                @endforeach
            </div>
        </div>

        <button class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white px-5 py-2 text-sm font-bold">Simpan</button>
    </form>
@endsection
