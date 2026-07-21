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

        <div class="border-t border-slate-100 pt-4" id="postedBox"
             data-brand-stores='@json($brandStores)'>
            <div class="flex items-center justify-between gap-2 mb-1">
                <p class="text-sm font-semibold">Sudah terposting di toko (input mundur)</p>
                <label class="inline-flex items-center gap-1.5 text-xs text-slate-600 cursor-pointer">
                    <input type="checkbox" id="postedAll" class="rounded accent-emerald-500">
                    Pilih semua
                </label>
            </div>
            <p class="text-xs text-slate-500 mb-3">
                Centang toko yang <b>SUDAH</b> ada postingan produk ini — tidak dibuatkan tugas.
                Toko lain yang tidak dicentang otomatis dibuatkan tugas posting.
            </p>

            <p id="postedNoBrand" class="text-xs text-amber-600 mb-2">
                Pilih brand dulu — daftar toko mengikuti pemetaan brand (menu Brand → Edit).
            </p>

            <div class="flex flex-wrap gap-2">
                @foreach($stores as $s)
                    <label class="posted-store inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm cursor-pointer"
                           data-store-id="{{ $s->id }}" style="display:none">
                        <input type="checkbox" name="posted_stores[]" value="{{ $s->id }}"
                               @checked(in_array($s->id, old('posted_stores', []))) class="rounded accent-emerald-500">
                        {{ $s->label() }}
                    </label>
                @endforeach
            </div>
        </div>

        <script>
        (function () {
            var box = document.getElementById('postedBox');
            if (!box) return;

            var MAP     = JSON.parse(box.getAttribute('data-brand-stores') || '{}');
            var all     = document.getElementById('postedAll');
            var noBrand = document.getElementById('postedNoBrand');
            var labels  = box.querySelectorAll('.posted-store');

            function brandSel() { return document.querySelector('[name="brand_id"]'); }
            function visible()  {
                return Array.prototype.filter.call(labels, function (l) { return l.style.display !== 'none'; })
                    .map(function (l) { return l.querySelector('input'); });
            }

            /* Tampilkan HANYA toko milik brand terpilih. Server memang cuma menghitung
               toko brand (array_intersect di ProductController::store) — daftar penuh
               yang lama bikin centangan di toko lain diabaikan diam-diam.
               Visibilitas pakai style.display, BUKAN class 'hidden': label ini juga
               punya 'inline-flex', dan di CSS build 'inline-flex' menang atas 'hidden'
               (spesifisitas sama → urutan menang), jadi 'hidden' gak ngefek. Inline
               style selalu ngalahin class — ini yang bikin filter beneran jalan. */
            function applyBrand() {
                var sel = brandSel();
                var ids = (sel && sel.value ? MAP[sel.value] : null) || [];
                var shown = 0;

                labels.forEach(function (l) {
                    var ok = ids.indexOf(parseInt(l.getAttribute('data-store-id'), 10)) !== -1;
                    l.style.display = ok ? '' : 'none';
                    if (!ok) l.querySelector('input').checked = false; // yang disembunyiin jangan ikut ke-submit
                    if (ok) shown++;
                });

                noBrand.classList.toggle('hidden', shown > 0);
                if (sel && sel.value && shown === 0) {
                    noBrand.classList.remove('hidden');
                    noBrand.textContent = 'Brand ini belum dipetakan ke toko mana pun — atur dulu di menu Brand → Edit.';
                }

                syncAll();
            }

            function syncAll() {
                var v = visible();
                var n = 0;
                v.forEach(function (c) { if (c.checked) n++; });
                all.checked = v.length > 0 && n === v.length;
            }

            all.addEventListener('change', function () {
                visible().forEach(function (c) { c.checked = all.checked; });
            });

            box.addEventListener('change', function (e) {
                if (e.target.name === 'posted_stores[]') syncAll();
            });

            var sel = brandSel();
            if (sel) {
                sel.addEventListener('change', applyBrand);
                sel.addEventListener('input', applyBrand);
            }

            /* Draft form & searchable init jalan di AKHIR body (sesudah script ini).
               Draft me-restore value brand TANPA men-dispatch event — jadi apply ulang
               setelah DOM siap, biar toko ikut brand yang dipulihkan draft. */
            applyBrand();
            document.addEventListener('DOMContentLoaded', applyBrand);
        })();
        </script>

        <button class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white px-5 py-2 text-sm font-bold">Simpan</button>
    </form>
@endsection
