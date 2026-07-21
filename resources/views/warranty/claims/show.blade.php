@extends('layouts.app')

@section('title', $claim->claim_number)

@php
    $canProcess = auth()->user()->canProcessWarrantyClaim();
    $next = $claim->status->next();
    $timeline = \App\Enums\WarrantyClaimStatus::timeline();
    $curIdx = array_search($claim->status, $timeline, true);
@endphp

@section('content')
    <a href="{{ route('warranty.claims.index') }}" class="text-sm text-slate-500 hover:underline">← Klaim Retur</a>

    <div class="flex flex-wrap items-center justify-between gap-2 mt-2 mb-5">
        <div>
            <h1 class="text-xl font-bold font-mono">{{ $claim->claim_number }}</h1>
            <p class="text-sm text-slate-500">{{ $claim->status->label() }}
                @if($claim->outcome)
                    · <b class="{{ $claim->outcome === 'diterima' ? 'text-emerald-600' : 'text-rose-600' }}">{{ strtoupper($claim->outcome) }}</b>
                @endif
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('warranty.claims.receipt', $claim) }}" target="_blank"
               class="rounded-xl bg-white border border-slate-300 px-4 py-2 text-sm font-semibold hover:border-emerald-400">🖨 Cetak Nota</a>
        </div>
    </div>

    {{-- Progress 8 tahap (Batal ditampilin sebagai banner, bukan di garis) --}}
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
                            {{ $st->label() }}
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
                    {{ collect($claim->completeness)->map(fn ($i) => ucwords(str_replace('_',' ',$i)))->join(', ') ?: '—' }}</p>
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
            @if($canProcess && ! $claim->status->isFinal())
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase mb-3">Aksi</p>

                    @if($next)
                        <form method="POST" action="{{ route('warranty.claims.advance', $claim) }}" enctype="multipart/form-data" class="space-y-3">
                            @csrf
                            <p class="text-sm">Tahap berikutnya: <b>{{ $next->label() }}</b></p>

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
                                    <input type="file" name="shipping_photos[]" accept="image/*" capture="environment" multiple
                                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
                                </div>
                            @endif

                            <input type="text" name="note" maxlength="500" placeholder="Catatan (opsional — kelihatan di lacak pelanggan)"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">

                            <button class="w-full rounded-lg bg-slate-900 hover:bg-slate-800 text-white py-2.5 text-sm font-bold"
                                    onclick="return confirm('Majukan ke: {{ $next->label() }}?')">
                                → {{ $next->label() }}
                            </button>
                        </form>
                    @endif

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
                </div>
            @endif

            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <p class="text-xs font-semibold text-slate-500 uppercase mb-3">Riwayat</p>
                <div class="space-y-2.5">
                    @foreach($claim->histories->sortByDesc('created_at') as $h)
                        <div class="flex gap-3 text-sm">
                            <span class="shrink-0 mt-0.5">{{ $h->is_followup ? '📣' : '●' }}</span>
                            <div>
                                <p>
                                    @if($h->is_followup)
                                        <b>Di-follow up</b> oleh {{ $h->user?->name ?? 'sistem' }}
                                    @else
                                        <b>{{ $h->to_status?->label() }}</b>
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
