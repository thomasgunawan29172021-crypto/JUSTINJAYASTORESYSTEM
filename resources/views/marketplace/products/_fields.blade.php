@php
    $p = $product ?? null;
    $rp = fn ($n) => $n > 0 ? number_format($n, 0, ',', '.') : '';
    // 10.00 → "10", 10.50 → "10,5", null → "" (BUKAN "0" — null artinya ikut brand)
    $pct = fn ($v) => $v === null || $v === ''
        ? ''
        : rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
    $isBundle = (bool) old('is_bundle', $p?->is_bundle);
    $oldComponents = old('components') ?? ($p?->bundleItems?->map(fn ($i) => [
        'id' => $i->component_id, 'qty' => $i->qty,
    ])->all() ?? []);
@endphp

<div>
    <label class="block text-xs font-semibold text-slate-600 mb-1">Nama produk *</label>
    <input type="text" name="name" value="{{ old('name', $p?->name) }}" required
           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
</div>

<div class="grid sm:grid-cols-2 gap-3">
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Barcode</label>
        <input type="text" name="barcode" value="{{ old('barcode', $p?->barcode) }}" maxlength="100"
               placeholder="mis. 8991234567890"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Kode SKU</label>
        <input type="text" name="sku" value="{{ old('sku', $p?->sku) }}" maxlength="100"
               placeholder="mis. SRS-HC9-BLK"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
</div>

<div>
    <label class="block text-xs font-semibold text-slate-600 mb-1">Brand *</label>
    <select name="brand_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
        <option value="">— pilih —</option>
        @foreach($brands as $b)
            <option value="{{ $b->id }}" @selected(old('brand_id', $p?->brand_id) == $b->id)>{{ $b->name }}</option>
        @endforeach
    </select>
</div>

@if($p === null)
    <label class="flex items-center gap-2 text-sm text-slate-600 rounded-lg bg-slate-50 border border-slate-200 px-3 py-2.5">
        <input type="checkbox" name="is_bundle" value="1" id="isBundle" @checked($isBundle) class="rounded">
        <span><b>Produk bundle</b> — gabungan beberapa produk. Modalnya dihitung otomatis dari isinya.</span>
    </label>
@elseif($p->is_bundle)
    <div class="rounded-lg bg-violet-50 border border-violet-200 px-3 py-2.5 text-sm text-violet-800">
        📦 <b>Produk bundle</b> — modal dihitung otomatis dari komponen di bawah.
        <span class="text-violet-600 text-xs">Status bundle gak bisa diubah; kalau salah, hapus lalu bikin ulang.</span>
    </div>
    <input type="hidden" name="is_bundle" value="1">
@endif

<div id="bundleBox" class="{{ $isBundle ? '' : 'hidden' }} border-t border-slate-100 pt-4">
    <p class="text-xs font-semibold text-slate-600 mb-1">📦 Isi Bundle</p>
    <p class="text-[11px] text-slate-400 mb-2">
        Modal bundle = total modal komponen <b>setelah program brand masing-masing</b>.
        Program brand bundle ini sendiri gak dipotong lagi — biar gak dobel.
    </p>

    <div id="bundleRows" class="space-y-2"></div>

    <button type="button" id="bundleAdd"
            class="mt-2 rounded-lg bg-white border border-slate-300 px-3 py-1.5 text-xs font-semibold hover:border-emerald-400">
        + Tambah komponen
    </button>

    <div id="bundleModal" class="hidden mt-3 rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 text-sm">
        <span class="text-slate-500 text-xs">Modal bundle:</span>
        <b id="bundleModalAfter" class="text-slate-800">—</b>
        <span id="bundleModalNote" class="text-[11px] text-slate-400"></span>
    </div>

    @if($components->isEmpty())
        <p class="text-xs text-amber-600 mt-2">Belum ada produk biasa yang bisa dijadiin komponen.</p>
    @endif
</div>

<div class="grid sm:grid-cols-3 gap-3">
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Kategori</label>
        <select name="category_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
            <option value="">— belum dipilih —</option>
            @foreach($categories as $c)
                <option value="{{ $c->id }}" @selected(old('category_id', $p?->category_id) == $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
        <p class="text-[11px] text-slate-400 mt-1">
            Nentuin biaya admin &amp; program marketplace buat harga rekomendasi.
            @if($categories->isEmpty())
                <span class="text-amber-600">Belum ada kategori — bikin dulu di Pengaturan Harga.</span>
            @endif
        </p>
    </div>
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Tambahan diskon (%)</label>
        <input type="text" inputmode="decimal" name="program_extra_percent"
               value="{{ $pct(old('program_extra_percent', $p?->program_extra_percent)) }}"
               placeholder="—"
               class="percent-input w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <p class="text-[11px] text-slate-400 mt-1">Numpuk di atas program brand.</p>
    </div>
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Tambahan diskon (Rp)</label>
        <input type="text" inputmode="numeric" name="program_extra_amount"
               value="{{ $rp(old('program_extra_amount', $p?->program_extra_amount) ?? 0) }}"
               placeholder="—"
               class="rp-input w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <p class="text-[11px] text-slate-400 mt-1">Dipotong paling akhir, setelah semua persen.</p>
    </div>
</div>

<div class="grid sm:grid-cols-3 gap-3" id="costBox">
    <div id="costPriceCell">
        <label class="block text-xs font-semibold text-slate-600 mb-1">Harga beli <span class="text-rose-500">(rahasia)</span></label>
        <input type="text" inputmode="numeric" name="cost_price" value="{{ $rp(old('cost_price', $p?->cost_price ?? 0)) }}"
               placeholder="0" class="money-input w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Harga offline</label>
        <input type="text" inputmode="numeric" name="price_offline" value="{{ $rp(old('price_offline', $p?->price_offline ?? 0)) }}"
               placeholder="0" class="money-input w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Harga grosir</label>
        <input type="text" inputmode="numeric" name="price_grosir" value="{{ $rp(old('price_grosir', $p?->price_grosir ?? 0)) }}"
               placeholder="0" class="money-input w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
</div>

<div class="border-t border-slate-100 pt-4" id="reco-panel"
     data-reco-url="{{ route('marketplace.products.recommendation') }}">
    <div class="flex items-center justify-between gap-2 mb-2">
        <p class="text-xs font-semibold text-slate-600">🧮 Harga Jual Rekomendasi</p>
        <button type="button" id="reco-apply-all"
                class="hidden rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold px-3 py-1.5">
            Pakai semua
        </button>
    </div>
    <div id="reco-body" class="text-xs text-slate-400">Memuat…</div>
    <p class="text-[11px] text-slate-400 mt-2">
        Cuma saran — harga di bawah tetap bisa kamu isi sendiri. Tombol <b>pakai</b> mengisi kolomnya, belum menyimpan.
    </p>
</div>

<div>
    <p class="text-xs font-semibold text-slate-600 mb-2">Harga per marketplace</p>

    @if($marketplaces->isNotEmpty())
        <div class="grid grid-cols-3 gap-3 mb-1">
            <span></span>
            <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">Harga Mall</span>
            <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">Harga Non-Mall</span>
        </div>
    @endif

    @forelse($marketplaces as $mp)
        @php $row = $p?->prices->firstWhere('marketplace', $mp); @endphp
        <div class="grid grid-cols-3 gap-3 items-center mb-2">
            <span class="text-sm font-medium">{{ ucfirst($mp) }}</span>
            <input type="text" inputmode="numeric" name="mp[{{ $mp }}][mall]"
                   value="{{ $rp($row?->price_mall ?? 0) }}" placeholder="0"
                   class="money-input rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input type="text" inputmode="numeric" name="mp[{{ $mp }}][regular]"
                   value="{{ $rp($row?->price_regular ?? 0) }}" placeholder="0"
                   class="money-input rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
    @empty
        <p class="text-xs text-amber-600">Belum ada toko aktif — marketplace terdeteksi dari daftar toko.</p>
    @endforelse
</div>
<script>
(function () {
    var box   = document.getElementById('bundleBox');
    if (!box) return;

    var rows  = document.getElementById('bundleRows');
    var add   = document.getElementById('bundleAdd');
    var toggle= document.getElementById('isBundle');
    var cell  = document.getElementById('costPriceCell');

    var OPTS = @json($components->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]));
    var INIT = @json(array_values($oldComponents));

    function addRow(id, qty) {
        var wrap = document.createElement('div');
        wrap.className = 'flex gap-2 items-center';
        wrap.innerHTML =
            '<select class="bundle-id flex-1 min-w-0 rounded-lg border border-slate-300 px-2 py-1.5 text-sm bg-white">' +
                '<option value="">— pilih produk —</option>' +
                OPTS.map(function (o) {
                    return '<option value="' + o.id + '"' + (String(o.id) === String(id) ? ' selected' : '') + '>' +
                           o.name.replace(/</g, '&lt;') + '</option>';
                }).join('') +
            '</select>' +
            '<input type="number" min="1" value="' + (qty || 1) + '" class="bundle-qty w-20 rounded-lg border border-slate-300 px-2 py-1.5 text-sm" title="Jumlah">' +
            '<button type="button" class="bundle-del w-8 h-8 rounded-lg bg-slate-100 text-slate-400 hover:text-rose-500 shrink-0">×</button>';
        rows.appendChild(wrap);
        renumber();
    }

    /* Nama input di-generate ulang tiap kali baris berubah — kalau nomornya bolong
       (misal baris tengah dihapus), PHP nerimanya tetap array rapat. */
    function renumber() {
        Array.prototype.forEach.call(rows.children, function (r, i) {
            r.querySelector('.bundle-id').name  = 'components[' + i + '][id]';
            r.querySelector('.bundle-qty').name = 'components[' + i + '][qty]';
        });
    }

    function applyMode() {
        /* Deteksi mode SAMA kayak refresh(): checkbox (create) ATAU hidden input
           (edit bundle). Kalau gak ada input is_bundle sama sekali (= edit produk
           BIASA), mode OFF — bukan default true. Kalau nebak true, form edit produk
           biasa bakal nyembunyiin "Harga beli" & munculin box bundle yang salah. */
        var el = box.closest('form').querySelector('[name="is_bundle"]');
        var on = el ? (el.type === 'checkbox' ? el.checked : true) : false;
        box.classList.toggle('hidden', !on);
        /* Modal bundle itu turunan — kolomnya disembunyiin biar gak keliatan
           kayak dua sumber kebenaran. Controller juga maksa 0. */
        if (cell) cell.classList.toggle('hidden', on);
    }

    INIT.forEach(function (c) { addRow(c.id, c.qty); });
    if (!INIT.length) addRow('', 1);
    applyMode();

    add.addEventListener('click', function () { addRow('', 1); });

    rows.addEventListener('click', function (e) {
        if (e.target.closest('.bundle-del')) {
            e.target.closest('.flex').remove();
            renumber();
            if (window.__recoRefresh) window.__recoRefresh();
        }
    });

    rows.addEventListener('input', function () {
        if (window.__recoRefresh) window.__recoRefresh();
    });

    toggle && toggle.addEventListener('change', function () {
        applyMode();
        if (window.__recoRefresh) window.__recoRefresh();
    });
})();
</script>
<script>
(function () {
    var panel = document.getElementById('reco-panel');
    if (!panel) return;

    var form     = panel.closest('form');
    var body     = document.getElementById('reco-body');
    var applyAll = document.getElementById('reco-apply-all');
    var url      = panel.getAttribute('data-reco-url');
    var timer    = null;
    var rows     = [];

    function q(n)      { return form.querySelector('[name="' + n + '"]'); }
    function val(n)    { var e = q(n); return e ? e.value : ''; }
    function digits(v) { return (v || '').replace(/\D/g, ''); }
    function group(n)  { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
    function esc(s)    { return String(s == null ? '' : s)
                            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

    /* Isi 1 kolom harga. Kolomnya .money-input yang value-nya bertitik —
       makanya diformat di sini; submit handler-nya yang buang titiknya lagi. */
    function fill(r) {
        if (!r || !r.slot || r.price == null) return false;
        var input = form.querySelector('[name="mp[' + r.marketplace + '][' + r.slot + ']"]');
        if (!input) return false;
        input.value = group(r.price);
        return true;
    }

    function render(data) {
        rows = data.rows || [];

        /* Modal bundle — read-only, angkanya dari server. */
        var mBox = document.getElementById('bundleModal');
        if (mBox) {
            var isB = document.getElementById('bundleBox');
            var show = isB && !isB.classList.contains('hidden') && data.modal && data.modal.raw > 0;
            mBox.classList.toggle('hidden', !show);

            if (show) {
                document.getElementById('bundleModalAfter').textContent = 'Rp ' + group(data.modal.after);
                document.getElementById('bundleModalNote').textContent =
                    data.modal.after !== data.modal.raw
                        ? '(mentah Rp ' + group(data.modal.raw) + ', setelah program komponen & tambahan)'
                        : '(belum ada potongan program)';
            }
        }

        if (data.blockers && data.blockers.length) {
            applyAll.classList.add('hidden');
            body.innerHTML = '<ul class="list-disc list-inside space-y-0.5 text-amber-700">' +
                data.blockers.map(function (b) { return '<li>' + esc(b) + '</li>'; }).join('') + '</ul>';
            return;
        }

        applyAll.classList.toggle('hidden',
            !rows.some(function (r) { return r.price != null && r.slot; }));

        body.innerHTML = rows.map(function (r, i) {
            var label = '<span class="font-medium text-slate-600">' +
                        esc(r.marketplace) + ' · ' + esc(r.tier || '?') + '</span>';
            var out;

            if (r.price == null) {
                out = '<div class="flex items-start justify-between gap-3">' +
                      label + '<span class="text-amber-700 text-right">' + esc(r.error) + '</span></div>';
            } else {
                var right = r.slot
                    ? '<button type="button" data-reco-i="' + i + '" class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white text-[11px] font-bold px-2.5 py-1">pakai</button>'
                    : '<span class="text-[11px] text-slate-400" title="tier ini belum punya kolom harga">belum ada slot</span>';

                out = '<div class="flex items-center justify-between gap-3">' + label +
                      '<span class="flex items-center gap-2"><b class="text-slate-800">Rp ' + group(r.price) + '</b>' + right + '</span>' +
                      '</div>';
            }

            /* Fase 3: untung/rugi dari harga yang DIKETIK — angka jadi, tanpa rincian biaya. */
            if (r.evaluation) {
                var e     = r.evaluation;
                var rugi  = e.profit < 0;
                var mar   = String(e.margin_percent).replace('.', ',');
                out += '<div class="text-[11px] mt-0.5 ' + (rugi ? 'text-rose-600 font-semibold' : 'text-emerald-700') + '">' +
                       'Kamu isi Rp ' + group(e.price) + ' → ' +
                       (rugi ? '⚠ RUGI Rp ' + group(Math.abs(e.profit)) : 'untung Rp ' + group(e.profit)) +
                       ' (' + mar + '%)</div>';
            }

            return '<div class="py-1.5 border-b border-slate-100">' + out + '</div>';
        }).join('');
    }

    function typedPrices() {
        var out = {};
        form.querySelectorAll('[name^="mp["]').forEach(function (el) {
            var m = el.getAttribute('name').match(/^mp\[(.+?)\]\[(.+?)\]$/);
            if (m) out[m[1] + '|' + m[2]] = digits(el.value);
        });
        return out;
    }

    function bundleComponents() {
        var out = [];
        form.querySelectorAll('#bundleRows > div').forEach(function (r) {
            var id  = r.querySelector('.bundle-id');
            var qty = r.querySelector('.bundle-qty');
            if (id && id.value) out.push({ id: id.value, qty: qty.value || 1 });
        });
        return out;
    }

    function refresh() {
        var bundleEl = form.querySelector('[name="is_bundle"]');
        var isBundle = bundleEl ? (bundleEl.type === 'checkbox' ? bundleEl.checked : true) : false;

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                _token:      val('_token'),
                brand_id:    val('brand_id'),
                category_id: val('category_id'),
                cost_price:  digits(val('cost_price')),
                is_bundle:   isBundle,
                components:  isBundle ? bundleComponents() : [],
                program_extra_percent: val('program_extra_percent').replace(',', '.'),
                program_extra_amount:  digits(val('program_extra_amount')),
                prices:      typedPrices()
            })
        })
        .then(function (r) { return r.json(); })
        .then(render)
        .catch(function () { body.textContent = 'Gagal ambil rekomendasi — coba ubah salah satu isian.'; });
    }

    /* Debounce: Thomas ngetik "1500000" = 7 keystroke. Tanpa ini, 7 request. */
    function schedule() { clearTimeout(timer); timer = setTimeout(refresh, 400); }

    /* Dipanggil dari script bundle di atas — dua IIFE gak bisa saling akses. */
    window.__recoRefresh = schedule;

    ['brand_id', 'category_id', 'cost_price', 'program_extra_percent', 'program_extra_amount'].forEach(function (n) {
        var el = q(n);
        if (!el) return;
        el.addEventListener('input', schedule);
        el.addEventListener('change', schedule);
    });

    /* Ketik harga sendiri → untung/rugi ikut jalan. */
    form.querySelectorAll('[name^="mp["]').forEach(function (el) {
        el.addEventListener('input', schedule);
    });

    body.addEventListener('click', function (e) {
        var b = e.target.closest('[data-reco-i]');
        if (b) { fill(rows[+b.getAttribute('data-reco-i')]); schedule(); }
    });

    applyAll.addEventListener('click', function () {
        rows.forEach(fill);
        schedule();
    });

    refresh();
})();
</script>
