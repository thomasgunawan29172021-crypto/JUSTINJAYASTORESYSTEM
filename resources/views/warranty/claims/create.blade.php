@extends('layouts.app')

@section('title', 'Klaim Retur Baru')

@section('content')
    <a href="{{ route('warranty.claims.index') }}" class="text-sm text-slate-500 hover:underline">← Klaim Retur</a>
    <h1 class="text-xl font-bold mt-2 mb-5">Terima Barang Retur</h1>

    <form method="POST" action="{{ route('warranty.claims.store') }}" enctype="multipart/form-data"
          data-draft="warranty-create"
          class="bg-white rounded-xl border border-slate-200 p-5 max-w-lg space-y-4">
        @csrf

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Cabang penerima *</label>
            <select name="branch_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm bg-white">
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" @selected(old('branch_id') == $b->id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Nama pelanggan *</label>
                <input type="text" name="customer_name" value="{{ old('customer_name') }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">No. HP pelanggan *</label>
                <input type="tel" name="customer_phone" value="{{ old('customer_phone') }}" required placeholder="08xxxxxxxxxx"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                <p class="text-[11px] text-slate-400 mt-1">Dipakai pelanggan buat lacak status — pastikan benar.</p>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Produk *</label>
            {{-- data-swap: brand produk ini boleh diganti duluan atau tidak. Dibaca
                 skrip di bawah buat memunculkan pilihan tukar-di-tempat. Nilainya
                 tetap divalidasi ulang di server — ini cuma supaya staf tidak
                 ditawari pilihan yang bakal ditolak. --}}
            <select name="product_id" id="inp-product" required data-searchable
                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm bg-white">
                <option value="">— pilih produk —</option>
                @foreach($products as $p)
                    <option value="{{ $p->id }}" @selected(old('product_id') == $p->id)
                            data-swap="{{ $p->brand?->warrantyVendor && ! $p->brand->warrantyVendor->requires_send_first ? '1' : '0' }}"
                            data-vendor="{{ $p->brand?->warrantyVendor?->name }}">
                        {{ $p->name }}
                    </option>
                @endforeach
            </select>
            <p id="flow-hint" class="text-[11px] mt-1 text-slate-400"></p>
        </div>

        {{-- Cuma nongol buat brand yang boleh diganti duluan (Robot/Olike dkk).
             Untuk brand wajib-kirim-dahulu blok ini tetap tersembunyi DAN
             centangnya dikosongkan, biar tidak kebawa submit diam-diam. --}}
        <div id="swap-box" class="hidden rounded-xl border border-emerald-200 bg-emerald-50 p-3">
            <label class="flex items-start gap-2 text-sm cursor-pointer">
                <input type="checkbox" name="swap_on_spot" value="1" id="inp-swap"
                       @checked(old('swap_on_spot')) class="mt-0.5 rounded accent-emerald-500">
                <span>
                    <b>Tukar langsung sekarang</b>
                    <span class="block text-[11px] text-emerald-700 mt-0.5">
                        Centang kalau stok cabang ada dan pelanggan pulang bawa unit pengganti hari ini juga.
                        Klaim langsung ditutup; unit rusaknya masuk urusan klaim ke supplier.
                    </span>
                </span>
            </label>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">IMEI / Serial</label>
                <input type="text" name="imei" value="{{ old('imei') }}" placeholder="ketik / scan"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">No. pesanan / nota</label>
                <input type="text" name="order_number" value="{{ old('order_number') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Tanggal beli</label>
            <input type="date" name="purchased_at" value="{{ old('purchased_at') }}" max="{{ now()->toDateString() }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Kelengkapan yang diserahkan</label>
            <div class="flex flex-wrap gap-2">
                @foreach(\App\Models\WarrantyClaim::COMPLETENESS_ITEMS as $item)
                    <label class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm cursor-pointer">
                        <input type="checkbox" name="completeness[]" value="{{ $item }}"
                               @checked(in_array($item, old('completeness', []))) class="rounded accent-emerald-500">
                        {{ ucwords(str_replace('_', ' ', $item)) }}
                    </label>
                @endforeach
            </div>
            {{-- Kelengkapan di luar checklist (anti gores, simcard, dll) — ketik manual. --}}
            <input type="text" name="completeness_other" value="{{ old('completeness_other') }}"
                   placeholder="Lainnya — mis. anti gores, simcard, memory card"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm mt-2">
            <p class="text-[11px] text-slate-400 mt-1">
                Pisah pakai koma, maksimal {{ \App\Models\WarrantyClaim::COMPLETENESS_OTHER_MAX }} item.
            </p>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Alasan retur / keluhan *</label>
            <textarea name="reason" rows="3" required
                      class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">{{ old('reason') }}</textarea>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Foto barang (segala sisi) *</label>
            {{-- capture=environment → HP langsung buka kamera belakang. Tetap bisa pilih dari galeri. --}}
            <input type="file" name="photos[]" accept="image/*" capture="environment" multiple required data-compress
                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm bg-white">
            <p class="text-[11px] text-slate-400 mt-1">Minimal 1, maksimal 8 — depan, belakang, sisi, dan bagian yang rusak.</p>
        </div>

        <button class="w-full rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white py-3 text-sm font-bold">
            Terima Barang &amp; Buat Klaim
        </button>
    </form>

    <script>
    /* Alur retur ikut brand produk (lihat WarrantyClaim::flowFor). Staf cabang
       tidak perlu hafal distributor mana punya aturan apa — cukup pilih produk,
       sistem yang bilang barangnya harus dikirim dulu atau boleh ditukar. */
    (function () {
        var sel  = document.getElementById('inp-product');
        var box  = document.getElementById('swap-box');
        var swap = document.getElementById('inp-swap');
        var hint = document.getElementById('flow-hint');

        function refresh() {
            var opt = sel.options[sel.selectedIndex];
            var canSwap = opt && opt.dataset.swap === '1';
            var vendor  = opt ? (opt.dataset.vendor || '') : '';

            box.classList.toggle('hidden', !canSwap);
            // Jangan biarkan centang lama ikut terkirim pas produknya diganti
            // ke brand yang wajib kirim dahulu.
            if (!canSwap) swap.checked = false;

            if (!opt || !opt.value) { hint.textContent = ''; return; }

            hint.textContent = canSwap
                ? '↺ ' + (vendor ? vendor + ' — ' : '') + 'boleh diganti duluan, klaim ke supplier belakangan.'
                : '→ ' + (vendor ? vendor + ' — ' : '') + 'wajib dikirim ke supplier dulu, pelanggan menunggu hasilnya.';
            hint.className = 'text-[11px] mt-1 ' + (canSwap ? 'text-emerald-600 font-semibold' : 'text-slate-500');
        }

        sel.addEventListener('change', refresh);
        refresh();
        // Sekali lagi setelah semua skrip layout jalan: draft form memulihkan
        // value <select> BELAKANGAN dan tidak melempar event change, jadi tanpa
        // ini petunjuk alurnya bisa ketinggalan satu produk.
        window.addEventListener('load', refresh);
    })();
    </script>
@endsection
