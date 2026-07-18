@extends('layouts.app')

@section('title', 'Tugas Marketplace')

@php $rp = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.'); @endphp

@section('content')
    <h1 class="text-xl font-bold mb-1">Tugas Marketplace</h1>
    <p class="text-sm text-slate-500 mb-5">
        {{ $isCeo ? 'Semua tugas di semua toko (mode CEO).' : 'Tugas untuk toko yang Anda pegang.' }}
    </p>

    @if($pending->isNotEmpty())
        <div class="mb-4">
            <button type="button" id="bulkToggle"
                    class="rounded-xl bg-white border border-slate-300 px-4 py-2 text-sm font-semibold hover:border-emerald-400">
                ☑️ Pilih Tugas
            </button>
        </div>
    @endif

    {{-- SATU kotak cari untuk dua daftar. Ketik = antrian tersaring instan (JS).
         Enter/Cari = server ikut menyaring riwayat Selesai dengan kata yang sama. --}}
    <form method="GET" class="mb-5 flex flex-wrap items-center gap-2">
        <div class="relative max-w-md flex-1 min-w-52">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">🔍</span>
            <input type="text" id="taskSearch" name="q" value="{{ $q }}" autocomplete="off"
                   placeholder="Cari produk… (ketik = saring antrian, Enter = cari riwayat juga)"
                   class="w-full rounded-xl border border-slate-300 bg-white pl-9 pr-9 py-2.5 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
            @if($q)
                <a href="{{ route('marketplace.tasks.index', array_filter(['range' => $range, 'store_id' => $storeId, 'brand_id' => $brandId])) }}"
                   class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-rose-500">✕</a>
            @endif
        </div>
        <select name="store_id" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
            <option value="">Semua toko</option>
            @foreach($stores as $s)
                <option value="{{ $s->id }}" @selected($storeId == $s->id)>{{ $s->label() }}</option>
            @endforeach
        </select>
        <select name="brand_id" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
            <option value="">Semua brand</option>
            @foreach($brands as $b)
                <option value="{{ $b->id }}" @selected($brandId == $b->id)>{{ $b->name }}</option>
            @endforeach
        </select>
        <select name="range" onchange="this.form.submit()"
                class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
            <option value="" @selected($range === '')>Semua waktu</option>
            <option value="today" @selected($range === 'today')>Hari ini</option>
            <option value="7d" @selected($range === '7d')>7 hari terakhir</option>
            <option value="30d" @selected($range === '30d')>30 hari terakhir</option>
        </select>
        <button class="rounded-xl bg-slate-900 text-white text-sm font-semibold px-4 py-2.5">Cari</button>
    </form>

    <p id="taskSearchInfo" class="hidden text-[11px] text-slate-400 -mt-3 mb-4"></p>

    @if($pending->isEmpty())
        <div class="bg-white rounded-xl border border-dashed border-slate-300 p-6 text-sm text-slate-400 mb-6">
            Tidak ada tugas menunggu. 👍
            @unless($isCeo)
                <span class="block mt-1 text-[11px]">Kalau Anda merasa harusnya punya tugas, minta CEO menetapkan Anda sebagai PIC brand (menu Brand → Edit).</span>
            @endunless
        </div>
    @endif

    @foreach($pending as $storeLabel => $tasks)
        <section class="mb-5 rounded-xl border border-slate-200 bg-slate-50/50" data-store-section>
            {{-- Judul toko sticky RELATIF ke kotak scroll-nya sendiri: pas scroll tugas
                 di dalam satu toko, judulnya nempel di atas kotak itu. --}}
            <h2 class="sticky top-0 z-10 rounded-t-xl px-4 py-2.5 bg-white border-b border-slate-200 text-sm font-semibold text-slate-600 uppercase tracking-wide">
                🏬 {{ $storeLabel }}
                <span class="text-slate-400 font-normal js-store-count" data-total="{{ $tasks->count() }}">({{ $tasks->count() }} tugas)</span>
            </h2>

            {{-- Scroll per toko: max-height doang, tanpa min — kotak nempel isi kalau
                 tugasnya dikit, baru scroll kalau lewat batas. Header search/filter
                 global tetap di luar, jadi selalu diem. --}}
            <div class="overflow-y-auto p-3" style="max-height: 420px;" data-store-scroll>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($tasks as $t)
                        @php $price = $t->product->priceForStore($t->store); @endphp
                        <div class="bg-white rounded-xl border {{ $t->pinned_at ? 'border-amber-400 ring-1 ring-amber-200' : 'border-slate-200' }} p-4 relative"
                             data-task-card data-search="{{ strtolower($t->product->name.' '.$t->product->brand->name) }}">
                            <form method="POST" action="{{ route('marketplace.tasks.pin', $t) }}" class="absolute top-2 right-2">
                                @csrf
                                <button title="{{ $t->pinned_at ? 'Lepas pin' : 'Pin ke depan' }}"
                                        class="text-base leading-none {{ $t->pinned_at ? '' : 'opacity-30 hover:opacity-100' }}">📌</button>
                            </form>

                            <div class="flex items-center gap-2 mb-1 pr-6">
                                <label class="js-bulk-pick hidden cursor-pointer shrink-0 flex items-center">
                                    <input type="checkbox" class="bulk-cb rounded accent-emerald-500 w-4 h-4 block" value="{{ $t->id }}">
                                </label>
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-medium
                                    @if($t->type === \App\Models\MarketplaceTask::TYPE_POSTING) bg-emerald-100 text-emerald-800
                                    @elseif($t->type === \App\Models\MarketplaceTask::TYPE_REVISION) bg-rose-100 text-rose-800
                                    @else bg-amber-100 text-amber-800 @endif">
                                    {{ $t->typeLabel() }}
                                </span>
                                <span class="text-[11px] text-slate-400 ml-auto">{{ $t->created_at->diffForHumans() }}</span>
                            </div>

                            <p class="font-semibold text-sm">{{ $t->product->name }}</p>
                            <p class="text-xs text-slate-500">{{ $t->product->brand->name }}</p>

                            <p class="text-sm mt-2">
                                Harga pasang:
                                @if($price !== null)
                                    <b>{{ $rp($price) }}</b>
                                    <span class="text-[11px] text-slate-400">({{ $t->store->is_mall ? 'harga mall' : 'harga non-mall' }})</span>
                                @else
                                    <span class="text-rose-600 text-xs font-semibold">belum diset — hubungi CEO</span>
                                @endif
                            </p>

                            @if($t->note)
                                <p class="text-[11px] text-slate-400 mt-1">📝 {{ $t->note }}</p>
                            @endif

                            <form method="POST" action="{{ route('marketplace.tasks.complete', $t) }}" class="js-single-complete mt-3"
                                  onsubmit="return confirm('Tandai selesai? Pastikan {{ $t->type === \App\Models\MarketplaceTask::TYPE_POSTING ? 'postingan sudah tayang' : 'harga sudah diubah' }} di {{ $t->store->name }}.')">
                                @csrf
                                <button class="w-full rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold py-2">
                                    ✓ Tandai Selesai
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endforeach

    <div id="taskNoResults" class="hidden bg-white rounded-xl border border-dashed border-slate-300 p-6 text-sm text-slate-400 mb-6">
        Tidak ada produk yang cocok dengan pencarian.
    </div>

    {{-- Search JS: SATU kali, di luar loop. Sebelumnya kepasang per-toko (jalan
         berkali-kali tiap ketik) — sekarang cukup sekali. --}}
    <script>
    (function () {
        var input = document.getElementById('taskSearch');
        if (!input) return;
        var info     = document.getElementById('taskSearchInfo');
        var noRes    = document.getElementById('taskNoResults');
        var sections = document.querySelectorAll('[data-store-section]');

        function apply() {
            var q = input.value.trim().toLowerCase();
            var totalVisible = 0;

            sections.forEach(function (sec) {
                var cards = sec.querySelectorAll('[data-task-card]');
                var shown = 0;
                cards.forEach(function (card) {
                    var hay = card.getAttribute('data-search') || '';
                    var match = q === '' || hay.indexOf(q) !== -1;
                    card.style.display = match ? '' : 'none';
                    if (match) shown++;
                });
                sec.style.display = shown === 0 ? 'none' : '';
                totalVisible += shown;

                var badge = sec.querySelector('.js-store-count');
                if (badge) {
                    var total = badge.getAttribute('data-total');
                    badge.textContent = q === ''
                        ? '(' + total + ' tugas)'
                        : '(' + shown + ' dari ' + total + ')';
                }
            });

            if (noRes) noRes.classList.toggle('hidden', !(q !== '' && totalVisible === 0));
            if (info) {
                if (q === '') { info.classList.add('hidden'); }
                else { info.classList.remove('hidden'); info.textContent = totalVisible + ' produk cocok.'; }
            }
        }

        input.addEventListener('input', apply);
    })();
    </script>

    <section>
        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
            <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide">
                {{ $isCeo ? 'Selesai' : 'Selesai (saya)' }}
                <span class="text-slate-400 font-normal">
                    @if($range) · {{ ['today' => 'hari ini', '7d' => '7 hari terakhir', '30d' => '30 hari terakhir'][$range] }} @endif
                    @if($q) · "{{ $q }}" @endif
                    · {{ $recentDone->total() }} tugas
                </span>
            </h2>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100">
            @forelse($recentDone as $t)
                <div class="px-4 py-2.5 flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span>{{ $t->typeLabel() }} — {{ $t->product->name }}@if($t->product->trashed()) <span class="text-[10px] px-1 rounded bg-slate-200 text-slate-500">di sampah</span>@endif <span class="text-slate-400">di {{ $t->store->name }}</span></span>
                    <span class="text-xs text-slate-400">
                        @if($t->completed_by === auth()->id() || $isCeo)
                            <form method="POST" action="{{ route('marketplace.tasks.undo', $t) }}" class="inline"
                                  onsubmit="return confirm('Kembalikan tugas ini ke antrian? Catatan posting-nya juga dicabut.')">
                                @csrf
                                <button class="text-rose-500 text-xs font-semibold hover:underline mr-2">Batalkan</button>
                            </form>
                        @endif
                        @if($isCeo && $t->type !== \App\Models\MarketplaceTask::TYPE_REVISION)
                            <form method="POST" action="{{ route('marketplace.tasks.revise', $t) }}" class="inline-flex items-center gap-1">
                                @csrf
                                <input type="text" name="note" required maxlength="300" placeholder="Apa yang salah?"
                                       class="rounded-lg border border-slate-300 px-2 py-1 text-xs w-40">
                                <button class="text-amber-600 text-xs font-semibold hover:underline">Minta Revisi</button>
                            </form>
                        @endif
                        @if($isCeo) oleh {{ $t->completer?->name }} · @endif
                        {{ $t->completed_at->format('d/m H:i') }}
                    </span>
                </div>
            @empty
                <p class="px-4 py-3 text-sm text-slate-400">Belum ada yang selesai.</p>
            @endforelse
        </div>

        @if($recentDone->hasPages())
            <div class="mt-3">{{ $recentDone->links() }}</div>
        @endif
    </section>

    {{-- Form tersembunyi: card udah punya form sendiri (pin & selesai), dan form
         gak boleh nested. Jadi checkbox dikumpulin JS, baru disuntik ke sini. --}}
    <form method="POST" action="{{ route('marketplace.tasks.bulk-complete') }}" id="bulkForm" class="hidden">
        @csrf
        <div id="bulkInputs"></div>
    </form>

    <div id="bulkBar" class="hidden fixed bottom-4 left-1/2 -translate-x-1/2 z-40 flex items-center gap-3
                             rounded-2xl bg-slate-900 text-white px-5 py-3 shadow-2xl">
        <span class="text-sm"><b id="bulkCount">0</b> tugas dipilih</span>
        <button type="button" id="bulkSubmit"
                class="rounded-lg bg-emerald-500 hover:bg-emerald-400 px-4 py-1.5 text-sm font-bold">
            ✓ Selesaikan
        </button>
        <button type="button" id="bulkCancel" class="text-xs text-slate-400 hover:text-white">Batal</button>
    </div>

    <script>
    (function () {
        var toggle = document.getElementById('bulkToggle');
        if (!toggle) return;

        var bar    = document.getElementById('bulkBar');
        var count  = document.getElementById('bulkCount');
        var form   = document.getElementById('bulkForm');
        var inputs = document.getElementById('bulkInputs');
        var on     = false;

        function picks()  { return document.querySelectorAll('.js-bulk-pick'); }
        function singles(){ return document.querySelectorAll('.js-single-complete'); }
        function boxes()  { return document.querySelectorAll('.bulk-cb'); }
        function checked(){ return Array.prototype.filter.call(boxes(), function (b) { return b.checked; }); }

        function refresh() {
            var n = checked().length;
            count.textContent = n;
            bar.classList.toggle('hidden', !on);

            /* Card yang kepilih dikasih ring — banyak checkbox kecil susah dipindai. */
            boxes().forEach(function (b) {
                var card = b.closest('[data-task-card]');
                if (card) { card.classList.toggle('ring-2', b.checked); card.classList.toggle('ring-emerald-400', b.checked); }
            });
        }

        function setMode(v) {
            on = v;
            picks().forEach(function (e)  { e.classList.toggle('hidden', !on); });
            /* Tombol per-card disembunyiin pas mode pilih — dua jalan nyelesaiin
               tugas di layar yang sama itu bikin salah pencet. */
            singles().forEach(function (e) { e.classList.toggle('hidden', on); });
            toggle.textContent = on ? '✕ Batal Pilih' : '☑️ Pilih Tugas';
            if (!on) boxes().forEach(function (b) { b.checked = false; });
            refresh();
        }

        toggle.addEventListener('click', function () { setMode(!on); });
        document.getElementById('bulkCancel').addEventListener('click', function () { setMode(false); });

        document.addEventListener('change', function (e) {
            if (e.target.classList.contains('bulk-cb')) refresh();
        });

        document.getElementById('bulkSubmit').addEventListener('click', function () {
            var sel = checked();
            if (!sel.length) return;

            /* Konfirmasi WAJIB nyebut jumlahnya: nyelesaiin tugas posting itu bikin
               kredit produktivitas atas nama orang yang mencet. Salah pencet =
               kredit palsu. Bisa di-undo, tapi mending jangan kejadian. */
            if (!confirm('Tandai ' + sel.length + ' tugas sebagai selesai? Pastikan semuanya memang sudah dikerjakan.')) return;

            inputs.innerHTML = '';
            sel.forEach(function (b) {
                var i = document.createElement('input');
                i.type  = 'hidden';
                i.name  = 'task_ids[]';
                i.value = b.value;
                inputs.appendChild(i);
            });
            form.submit();
        });
    })();
    </script>
@endsection