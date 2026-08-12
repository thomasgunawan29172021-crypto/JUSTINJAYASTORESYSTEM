@extends('layouts.app')

@section('title', $claim->claim_number)

@php
    $u           = auth()->user();
    $canProcess  = $u->canProcessWarrantyClaim();
    $canHandover = $u->canHandoverWarrantyClaim();
    $canNotify   = $u->canNotifyWarrantyCustomer();
    $canSupplier = $u->canManageSupplierClaim();

    $next     = $claim->nextStatus();
    $timeline = $claim->flow->timeline();
    $curIdx   = array_search($claim->status, $timeline, true);

    // Tahap cabang boleh frontliner; sisanya cuma tim retur. Dipisah supaya
    // frontliner tetap lihat panel aksi walau tahap tengah bukan haknya.
    $canAdvance = $next && ($next->isBranchStage() ? $canHandover : $canProcess);
@endphp

@section('content')
    <a href="{{ route('warranty.claims.index') }}" class="text-sm text-slate-500 hover:underline">← Klaim Retur</a>

    <div class="flex flex-wrap items-center justify-between gap-2 mt-2 mb-5">
        <div>
            <h1 class="text-xl font-bold font-mono">{{ $claim->claim_number }}</h1>
            <p class="text-sm text-slate-500">{{ $claim->statusLabel() }}
                @if($claim->outcome)
                    · <b class="{{ $claim->outcome === 'diterima' ? 'text-emerald-600' : 'text-rose-600' }}">{{ strtoupper($claim->outcome) }}</b>
                @endif
            </p>
            <p class="text-[11px] text-slate-400 mt-0.5">Alur: {{ $claim->flow->label() }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('warranty.claims.receipt', $claim) }}" target="_blank"
               class="rounded-xl bg-white border border-slate-300 px-4 py-2 text-sm font-semibold hover:border-emerald-400">🖨 Cetak Nota</a>
        </div>
    </div>

    {{-- Progress mengikuti ALUR klaim ini — 8 tahap (kirim dulu), 6 (ganti dulu),
         atau 2 (tukar di tempat). Batal ditampilin sebagai banner, bukan di garis. --}}
    @if($claim->status->value === 'batal')
        <div class="mb-5 rounded-xl bg-slate-100 border border-slate-300 px-4 py-3 text-sm text-slate-600">
            ⛔ Klaim <b>dibatalkan</b>@if($claim->cancel_reason): {{ $claim->cancel_reason }}@endif
        </div>
    @else
        <div class="mb-5 bg-white rounded-xl border border-slate-200 p-4 overflow-x-auto">
            <div class="flex items-center min-w-[640px]">
                @foreach($timeline as $i => $st)
                    <div class="flex-1 flex flex-col items-center text-center">
                        <div class="w-7 h-7 rounded-full grid place-items-center text-xs font-bold
                            {{ $i < $curIdx ? 'bg-emerald-500 text-white' : ($i === $curIdx ? 'bg-emerald-100 text-emerald-700 ring-2 ring-emerald-400' : 'bg-slate-100 text-slate-400') }}">
                            {{ $i < $curIdx ? '✓' : $i + 1 }}
                        </div>
                        <p class="text-[10px] mt-1 leading-tight {{ $i === $curIdx ? 'font-bold text-slate-700' : 'text-slate-400' }}">
                            {{ $claim->statusLabel($st) }}
                        </p>
                    </div>
                    @if(! $loop->last)
                        <div class="flex-1 h-0.5 -mt-4 {{ $i < $curIdx ? 'bg-emerald-400' : 'bg-slate-200' }}"></div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid lg:grid-cols-3 gap-4">
        {{-- Kolom info --}}
        <div class="lg:col-span-1 space-y-4">
            <div class="bg-white rounded-xl border border-slate-200 p-4 text-sm space-y-1.5">
                <p class="text-xs font-semibold text-slate-500 uppercase mb-2">Data Klaim</p>
                <p><span class="text-slate-400">Pelanggan:</span> <b>{{ $claim->customer_name }}</b> · {{ $claim->customer_phone }}</p>
                <p><span class="text-slate-400">Produk:</span> {{ $claim->product->name }}</p>
                @if($claim->imei)<p><span class="text-slate-400">IMEI:</span> <span class="font-mono">{{ $claim->imei }}</span></p>@endif
                @if($claim->order_number)<p><span class="text-slate-400">No. nota:</span> {{ $claim->order_number }}</p>@endif
                @if($claim->purchased_at)<p><span class="text-slate-400">Tgl beli:</span> {{ $claim->purchased_at->format('d/m/Y') }}</p>@endif
                <p><span class="text-slate-400">Cabang:</span> {{ $claim->branch->name }}</p>
                @if($claim->vendor)<p><span class="text-slate-400">Vendor:</span> {{ $claim->vendor->name }}</p>@endif
                <p><span class="text-slate-400">Kelengkapan:</span>
                    {{ implode(', ', $claim->completenessLabels()) ?: '—' }}</p>
                <p class="pt-1 border-t border-slate-100"><span class="text-slate-400">Alasan:</span> {{ $claim->reason }}</p>
                @if($claim->outcome_note)
                    <p><span class="text-slate-400">Catatan vendor:</span> {{ $claim->outcome_note }}</p>
                @endif
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <p class="text-xs font-semibold text-slate-500 uppercase mb-2">Foto ({{ $claim->photos->count() }})</p>
                <div class="grid grid-cols-3 gap-2">
                    @foreach($claim->photos as $ph)
                        <a href="{{ route('warranty.claims.photo', [$claim, $ph]) }}" target="_blank" class="block relative">
                            <img src="{{ route('warranty.claims.photo', [$claim, $ph]) }}" class="rounded-lg aspect-square object-cover w-full">
                            @if($ph->type === 'shipping')
                                <span class="absolute bottom-1 left-1 px-1 rounded bg-sky-600 text-white text-[9px]">resi</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Kolom aksi + riwayat --}}
        <div class="lg:col-span-2 space-y-4">
            @if(($canProcess || $canAdvance) && ! $claim->status->isFinal())
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase mb-3">Aksi</p>

                    {{-- Tahap supplier di alur kirim-dulu digerakkan dari pengiriman,
                         bukan dari sini — kalau boleh dua-duanya, status klaim dan
                         status pengiriman bakal beda cerita. --}}
                    @if($next && $claim->isShipmentDriven($next))
                        <div class="rounded-lg bg-slate-50 border border-slate-200 px-3 py-2.5 text-sm text-slate-600">
                            Tahap berikutnya <b>{{ $next->label() }}</b> bergerak sendiri lewat
                            <a href="{{ route('warranty.supplier.index') }}" class="text-emerald-700 font-semibold hover:underline">Klaim Supplier</a>.
                            Masukkan unitnya ke pengiriman, jangan dimajukan dari sini.
                        </div>
                    @elseif($next && $canAdvance)
                        <form method="POST" action="{{ route('warranty.claims.advance', $claim) }}" enctype="multipart/form-data" class="space-y-3">
                            @csrf
                            <p class="text-sm">Tahap berikutnya: <b>{{ $claim->statusLabel($next) }}</b></p>

                            @if($next === \App\Enums\WarrantyClaimStatus::DikirimVendor)
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Kirim ke vendor *</label>
                                    <select name="vendor_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
                                        <option value="">— pilih vendor —</option>
                                        @foreach($vendors as $v)
                                            <option value="{{ $v->id }}">{{ $v->name }}</option>
                                        @endforeach
                                    </select>
                                    <p class="text-[11px] text-slate-400 mt-1">Belum ada vendornya? <a href="{{ route('warranty.vendors.index') }}" class="text-emerald-700 hover:underline">Tambah dulu di Vendor Retur</a>.</p>
                                </div>
                            @endif

                            @if($next === \App\Enums\WarrantyClaimStatus::HasilVendor)
                                <div class="flex gap-3">
                                    <label class="flex-1 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2.5 text-sm cursor-pointer text-center font-semibold text-emerald-800">
                                        <input type="radio" name="outcome" value="diterima" required class="accent-emerald-500"> DITERIMA
                                    </label>
                                    <label class="flex-1 rounded-lg border border-rose-300 bg-rose-50 px-3 py-2.5 text-sm cursor-pointer text-center font-semibold text-rose-800">
                                        <input type="radio" name="outcome" value="ditolak" required class="accent-rose-500"> DITOLAK
                                    </label>
                                </div>
                                <textarea name="outcome_note" rows="2" placeholder="Hasil pengecekan / rekomendasi vendor…"
                                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                            @endif

                            @if(in_array($next, [\App\Enums\WarrantyClaimStatus::DikirimVendor, \App\Enums\WarrantyClaimStatus::DikirimBalik], true))
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Bukti pengiriman / resi</label>
                                    <input type="file" name="shipping_photos[]" accept="image/*" capture="environment" multiple data-compress
                                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
                                </div>
                            @endif

                            <input type="text" name="note" maxlength="500" placeholder="Catatan (opsional — kelihatan di lacak pelanggan)"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">

                            <button class="w-full rounded-lg bg-slate-900 hover:bg-slate-800 text-white py-2.5 text-sm font-bold"
                                    onclick="return confirm('Majukan ke: {{ $claim->statusLabel($next) }}?')">
                                → {{ $claim->statusLabel($next) }}
                            </button>
                        </form>
                    @endif

                    @if($canProcess)
                        <div class="grid sm:grid-cols-2 gap-3 mt-3 pt-3 border-t border-slate-100">
                            <form method="POST" action="{{ route('warranty.claims.followup', $claim) }}" class="flex gap-2">
                                @csrf
                                <input type="text" name="note" maxlength="500" placeholder="Follow-up ke supplier/ekspedisi…"
                                       class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <button class="rounded-lg bg-amber-500 hover:bg-amber-400 text-white px-3 py-2 text-xs font-bold whitespace-nowrap">📣 Follow-up</button>
                            </form>

                            @if($claim->status->canCancel())
                                <form method="POST" action="{{ route('warranty.claims.cancel', $claim) }}" class="flex gap-2"
                                      onsubmit="return confirm('Batalkan klaim ini? Tidak bisa dikembalikan.')">
                                    @csrf
                                    <input type="text" name="cancel_reason" required maxlength="500" placeholder="Alasan batal…"
                                           class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <button class="rounded-lg bg-rose-500 hover:bg-rose-400 text-white px-3 py-2 text-xs font-bold">⛔ Batal</button>
                                </form>
                            @endif
                        </div>
                    @endif
                </div>
            @endif

            {{-- ===== Kabar ke pelanggan (admin chat) ===== --}}
            @if($canNotify && ! $claim->status->isFinal())
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <p class="text-xs font-semibold text-slate-500 uppercase">Kabar ke pelanggan</p>
                        @if($claim->last_notified_at)
                            <span class="text-[11px] text-emerald-600 font-semibold">
                                ✓ terakhir dikabari {{ $claim->last_notified_at->diffForHumans() }}
                            </span>
                        @else
                            <span class="text-[11px] text-amber-600 font-semibold">belum pernah dikabari</span>
                        @endif
                    </div>
                    <p class="text-sm mb-2">
                        {{ $claim->customer_name }} ·
                        <a href="https://wa.me/{{ $claim->customer_phone }}" target="_blank"
                           class="font-mono text-emerald-700 hover:underline">{{ $claim->customer_phone }}</a>
                    </p>
                    <form method="POST" action="{{ route('warranty.claims.notify', $claim) }}" class="flex gap-2">
                        @csrf
                        <input type="text" name="note" maxlength="500" placeholder="Dikabari apa? (opsional)"
                               class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <button class="rounded-lg bg-sky-600 hover:bg-sky-500 text-white px-3 py-2 text-xs font-bold whitespace-nowrap">✓ Sudah dikabari</button>
                    </form>
                </div>
            @endif

            {{-- ===== Jalur klaim ke supplier — INTERNAL, tidak masuk lacak pelanggan ===== --}}
            @if($canSupplier && ($claim->needsSupplierDecision() || $claim->supplier_queued_at || $claim->supplier_skipped_at || $claim->supplierItems->isNotEmpty()))
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase mb-3">
                        Klaim ke Supplier <span class="normal-case font-normal text-slate-400">— internal, tidak dilihat pelanggan</span>
                    </p>

                    @if($claim->needsSupplierDecision())
                        <div class="rounded-lg bg-amber-50 border border-amber-200 p-3">
                            <p class="text-sm font-semibold text-amber-800 mb-1">Perlu diputuskan</p>
                            <p class="text-xs text-amber-700 mb-3">
                                @if($claim->flow === \App\Enums\WarrantyClaimFlow::TukarTempat)
                                    Unit ini ditukar di tempat dan belum diperiksa tim pusat. Kalau layak, masukkan ke antrean klaim supplier.
                                @else
                                    Hasil pengecekan DITOLAK, jadi unit ini tidak masuk antrean otomatis. Tetap mau dicoba klaim ke supplier?
                                @endif
                            </p>
                            <form method="POST" action="{{ route('warranty.claims.supplier', $claim) }}" class="space-y-2">
                                @csrf
                                <input type="text" name="note" maxlength="500" placeholder="Catatan / alasan…"
                                       class="w-full rounded-lg border border-amber-300 px-3 py-2 text-sm">
                                <div class="flex gap-2">
                                    <button name="decision" value="klaim"
                                            class="flex-1 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white py-2 text-xs font-bold">
                                        ✓ Layak — masukkan antrean klaim
                                    </button>
                                    <button name="decision" value="lepas"
                                            onclick="return confirm('Tandai TIDAK diklaim? Ruginya kita tanggung sendiri.')"
                                            class="flex-1 rounded-lg border border-rose-300 text-rose-600 py-2 text-xs font-bold">
                                        ✕ Tidak diklaim
                                    </button>
                                </div>
                            </form>
                        </div>
                    @elseif($claim->supplier_skipped_at)
                        <p class="text-sm text-slate-500">
                            ✕ Ditandai <b>tidak diklaim</b> {{ $claim->supplier_skipped_at->format('d/m/Y') }}
                            @if($claim->supplier_note)<span class="block text-xs text-slate-400">{{ $claim->supplier_note }}</span>@endif
                        </p>
                    @elseif($claim->supplierItems->isEmpty())
                        <p class="text-sm text-slate-600">
                            ⏳ Menunggu dimasukkan ke pengiriman —
                            <a href="{{ route('warranty.supplier.index') }}" class="text-emerald-700 font-semibold hover:underline">buka antrean</a>.
                            <span class="block text-xs text-slate-400">Masuk antrean {{ $claim->supplier_queued_at?->diffForHumans() }}</span>
                        </p>
                    @endif

                    @foreach($claim->supplierItems->sortByDesc('created_at') as $it)
                        <div class="mt-2 rounded-lg border border-slate-200 p-3 text-sm">
                            <a href="{{ route('warranty.supplier.show', $it->shipment) }}"
                               class="font-mono font-semibold text-emerald-700 hover:underline">{{ $it->shipment->shipment_number }}</a>
                            <span class="text-slate-400">· {{ $it->shipment->vendor->name }}</span>
                            <p class="text-xs mt-0.5">
                                {{ \App\Models\WarrantySupplierShipmentItem::outcomeLabel($it->outcome) }}
                                @if($it->refund_amount)
                                    <b class="text-emerald-700">Rp {{ number_format($it->refund_amount, 0, ',', '.') }}</b>
                                @endif
                                <span class="text-slate-400">· {{ $it->statusLabel() }}</span>
                            </p>
                            @if($it->outcome_note)<p class="text-xs text-slate-500 mt-0.5">{{ $it->outcome_note }}</p>@endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <p class="text-xs font-semibold text-slate-500 uppercase mb-3">Riwayat</p>
                <div class="space-y-2.5">
                    @foreach($claim->histories->sortByDesc('created_at') as $h)
                        <div class="flex gap-3 text-sm">
                            <span class="shrink-0 mt-0.5">{{ $h->is_followup ? '📣' : ($h->is_notify ? '💬' : '●') }}</span>
                            <div>
                                <p>
                                    @if($h->is_followup)
                                        <b>Di-follow up</b> oleh {{ $h->user?->name ?? 'sistem' }}
                                    @elseif($h->is_notify)
                                        <b>Pelanggan dikabari</b> oleh {{ $h->user?->name ?? 'sistem' }}
                                    @elseif($h->to_status)
                                        <b>{{ $claim->statusLabel($h->to_status) }}</b>
                                        <span class="text-slate-400">oleh {{ $h->user?->name ?? 'sistem' }}</span>
                                    @else
                                        {{-- to_status null & bukan followup/notify = catatan jalur supplier --}}
                                        <b class="text-slate-600">Catatan</b>
                                        <span class="text-slate-400">oleh {{ $h->user?->name ?? 'sistem' }}</span>
                                    @endif
                                </p>
                                @if($h->note)<p class="text-xs text-slate-500">{{ $h->note }}</p>@endif
                                <p class="text-[11px] text-slate-400">{{ $h->created_at->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection
