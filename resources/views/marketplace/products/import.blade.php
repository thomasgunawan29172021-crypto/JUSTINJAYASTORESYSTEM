@extends('layouts.app')

@section('title', 'Import Produk')

@section('content')
    <a href="{{ route('marketplace.products.index') }}" class="text-sm text-slate-500 hover:underline">← Produk</a>
    <h1 class="text-xl font-bold mt-2 mb-5">Import Produk (CSV)</h1>

    {{-- Halaman ini sudah CEO-only (middleware 'ceo'), jadi peringatan ini selalu tampil ke yang bisa membukanya. --}}
    <div class="mb-4 max-w-4xl rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <b>⚠️ Matriks posting aktif — mode CEO.</b>
        Kolom <code class="bg-amber-100 px-1 rounded">post_[nama toko]</code> berisi <b>v</b> (sudah posting) / <b>x</b> (belum)
        akan <b>langsung menetapkan status posting</b>, tanpa membuat tugas dan tanpa dikreditkan ke PIC mana pun.
        <ul class="list-disc list-inside mt-1.5 space-y-0.5 text-[13px]">
            <li><b>v</b> → ditandai sudah posting. Tugas posting yang masih pending untuk toko itu ikut dihapus.</li>
            <li><b>x</b> → posting dihapus bila ada. <b>Termasuk kredit PIC yang benar-benar mengerjakannya</b> — hati-hati.</li>
            <li><b>Kosong</b> → status toko itu <b>tidak diubah</b> (aman).</li>
        </ul>
        Gunakan untuk migrasi data awal, bukan operasi harian. Cara paling aman: <b>Export dulu</b>, edit kolomnya, lalu import balik.
    </div>

    <div class="grid lg:grid-cols-2 gap-4 max-w-4xl">
        <form method="POST" action="{{ route('marketplace.products.import') }}" enctype="multipart/form-data"
              class="bg-white rounded-xl border border-slate-200 p-5 space-y-4 self-start">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">File CSV (maks 10 MB)</label>
                <input type="file" name="file" accept=".csv,.txt" required
                       class="w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-900 file:text-white file:px-4 file:py-2 file:text-sm">
            </div>
            <button class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white px-5 py-2 text-sm font-bold">Import</button>
            <p class="text-[11px] text-slate-400">
                Upload ulang file yang sama aman: produk dengan nama sama akan DI-UPDATE, bukan digandakan.
            </p>
        </form>

        <div class="bg-white rounded-xl border border-slate-200 p-5 text-sm">
            <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-2">Format kolom (baris pertama)</h2>
            <p class="mb-2">Wajib: <code class="bg-slate-100 px-1 rounded">nama</code>, <code class="bg-slate-100 px-1 rounded">brand</code></p>
            <p class="mb-2">
                Identitas produk (opsional):
                <code class="bg-slate-100 px-1 rounded">sku</code>,
                <code class="bg-slate-100 px-1 rounded">barcode</code>
            </p>
            <p class="mb-2">
                Harga (opsional):
                <code class="bg-slate-100 px-1 rounded">harga_beli</code>,
                <code class="bg-slate-100 px-1 rounded">harga_offline</code>,
                <code class="bg-slate-100 px-1 rounded">harga_grosir</code>
            </p>
            <p class="mb-2">Harga per marketplace (opsional):</p>
            <ul class="list-disc list-inside text-xs text-slate-600 space-y-0.5">
                @foreach($marketplaces as $mp)
                    <li><code class="bg-slate-100 px-1 rounded">{{ $mp }}_mall</code>, <code class="bg-slate-100 px-1 rounded">{{ $mp }}_biasa</code></li>
                @endforeach
            </ul>
            <p class="mt-3 mb-2">Matriks posting — isi <code class="bg-slate-100 px-1 rounded">v</code> / <code class="bg-slate-100 px-1 rounded">x</code> / kosong:</p>
            <ul class="list-disc list-inside text-xs text-slate-600 space-y-0.5">
                @foreach($postingColumns->keys() as $key)
                    <li><code class="bg-slate-100 px-1 rounded">{{ $key }}</code></li>
                @endforeach
            </ul>

            <p class="text-[11px] text-slate-500 mt-3">
                SKU dan barcode dibaca sebagai teks. Sel kosong tidak mengubah nilai lama. Saat mengedit di Excel, set kolom barcode ke format <b>Text</b> agar angka nol di depan tidak hilang.
            </p>

            <p class="text-[11px] text-amber-600 mt-3">
                ⚠️ Brand yang belum ada akan DIBUAT otomatis — pastikan ejaan konsisten ("Oppo" ≠ "OPPO" ≠ "Opo"),
                dan setelah import cek menu Brand untuk memetakan brand baru ke toko.
            </p>
        </div>
    </div>
@endsection