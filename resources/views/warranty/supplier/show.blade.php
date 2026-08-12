@extends('layouts.app')

@section('title', $shipment->shipment_number)

@php
    $next     = $shipment->status->next();
    $timeline = \App\Enums\SupplierShipmentStatus::timeline();
    $curIdx   = array_search($shipment->status, $timeline, true);
@endphp

@section('content')
    <a href="{{ route('warranty.supplier.index') }}" class="text-sm text-slate-500 hover:underline">← Klaim Supplier</a>

    <div class="flex flex-wrap items-center justify-between gap-2 mt-2 mb-5">
        <div>
            <h1 class="text-xl font-bold font-mono">{{ $shipment->shipment_number }}</h1>
            <p class="text-sm text-slate-500">
                {{ $shipment->vendor->name }} · {{ $shipment->items->count() }} unit · {{ $shipment->status->label() }}
            </p>
        </div>
        <a href="{{ route('warranty.supplier.receipt', $shipment) }}" target="_blank"
           class="rounded-xl bg-white border border-slate-300 px-4 py-2 text-sm font-semibold hover:border-emerald-400">
            🖨 Surat Tanda Terima
        </a>
    </div>

    {{-- Progress pengiriman --}}
    <div class="mb-5 bg-white rounded-xl border border-slate-200 p-4 overflow-x-auto">
        <div class="flex items-center min-w-[420px]">
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

    @if($shipment->status !== \App\Enums\SupplierShipmentStatus::Selesai)
        <div class="bg-white rounded-xl border border-slate-200 p-4 mb-5">
            <p class="text-xs font-semibold text-slate-500 uppercase mb-3">Aksi</p>

            @if($next)
                <form method="POST" action="{{ route('warranty.supplier.advance', $shipment) }}" class="space-y-2">
                    @csrf
                    <input type="text" name="note" maxlength="500" placeholder="Catatan (opsional)"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <button class="w-full rounded-lg bg-slate-900 hover:bg-slate-800 text-white py-2.5 text-sm font-bold"
                            onclick="return confirm('Majukan pengiriman ke: {{ $next->label() }}?')">
                        → {{ $next->label() }}
                    </button>
                </form>
                @if($next === \App\Enums\SupplierShipmentStatus::Dikirim)
                    <p class="text-[11px] text-slate-400 mt-1">
                        Unit alur <b>kirim dahulu</b> di kiriman ini otomatis maju ke tahap supplier — tidak perlu diketik ulang satu-satu.
                    </p>
                @endif
            @else
                <p class="text-sm text-slate-500">
                    Tinggal menunggu hasil tiap unit di bawah. Pengiriman menutup sendiri kalau semua unit sudah kelar.
                </p>
            @endif

            <form method="POST" action="{{ route('warranty.supplier.followup', $shipment) }}" class="mt-3 pt-3 border-t border-slate-100">
                @csrf
                <button class="rounded-lg bg-amber-500 hover:bg-amber-400 text-white px-3 py-2 text-xs font-bold">
                    📣 Follow-up ke vendor
                </button>
                @if($shipment->last_followed_up_at)
                    <span class="text-[11px] text-slate-400 ml-2">terakhir {{ $shipment->last_followed_up_at->diffForHumans() }}</span>
                @endif
            </form>
        </div>
    @endif

    <div class="space-y-3">
        @foreach($shipment->items as $item)
            @php $claim = $item->claim; @endphp
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <a href="{{ route('warranty.claims.show', $claim) }}"
                           class="font-mono text-sm font-bold text-emerald-700 hover:underline">{{ $claim->claim_number }}</a>
                        <p class="text-sm">{{ $claim->product->name }}</p>
                        <p class="text-[11px] text-slate-400">
                            {{ $claim->branch->name }} · {{ $claim->flow->label() }}
                            @if($claim->imei) · <span class="font-mono">{{ $claim->imei }}</span>@endif
                        </p>
                    </div>
                    <div class="text-right">
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-medium
                            @if($item->status === \App\Models\WarrantySupplierShipmentItem::STATUS_SELESAI) bg-emerald-100 text-emerald-800
                            @elseif($item->status === \App\Models\WarrantySupplierShipmentItem::STATUS_DIKIRIM_GUDANG) bg-sky-100 text-sky-800
                            @else bg-slate-100 text-slate-600 @endif">
                            {{ \App\Models\WarrantySupplierShipmentItem::outcomeLabel($item->outcome) }}
                        </span>
                        @if($item->refund_amount)
                            <p class="text-xs font-bold text-emerald-700 mt-0.5">Rp {{ number_format($item->refund_amount, 0, ',', '.') }}</p>
                        @endif
                    </div>
                </div>

                @if($item->outcome_note)
                    <p class="text-xs text-slate-500 mt-2">{{ $item->outcome_note }}</p>
                @endif

                {{-- Isi hasil: baru boleh setelah barangnya benar-benar dicek supplier --}}
                @if($shipment->status === \App\Enums\SupplierShipmentStatus::Dicek && $item->status === \App\Models\WarrantySupplierShipmentItem::STATUS_MENUNGGU)
                    <form method="POST" action="{{ route('warranty.supplier.items.resolve', [$shipment, $item]) }}"
                          class="mt-3 pt-3 border-t border-slate-100 space-y-2">
                        @csrf
                        <div class="grid sm:grid-cols-3 gap-2">
                            <label class="rounded-lg border border-slate-300 px-3 py-2 text-xs text-center cursor-pointer has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50 font-semibold">
                                <input type="radio" name="outcome" value="ganti_produk" required class="accent-emerald-500"> Ganti Produk
                            </label>
                            <label class="rounded-lg border border-slate-300 px-3 py-2 text-xs text-center cursor-pointer has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50 font-semibold">
                                <input type="radio" name="outcome" value="ganti_uang" required class="accent-emerald-500"> Ganti Uang
                            </label>
                            <label class="rounded-lg border border-slate-300 px-3 py-2 text-xs text-center cursor-pointer has-[:checked]:border-rose-400 has-[:checked]:bg-rose-50 font-semibold">
                                <input type="radio" name="outcome" value="ditolak" required class="accent-rose-500"> Ditolak
                            </label>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <input type="number" name="refund_amount" min="1" placeholder="Nominal (khusus ganti uang)"
                                   class="w-52 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <input type="text" name="outcome_note" maxlength="1000" placeholder="Catatan supplier…"
                                   class="flex-1 min-w-40 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <button class="rounded-lg bg-slate-900 hover:bg-slate-800 text-white px-4 py-2 text-xs font-bold">Simpan hasil</button>
                        </div>
                        @if($claim->flow === \App\Enums\WarrantyClaimFlow::KirimDulu)
                            <p class="text-[11px] text-slate-400">
                                Alur kirim dahulu — hasil ini sekaligus jadi hasil pengecekan buat pelanggan yang masih menunggu.
                            </p>
                        @endif
                    </form>
                @endif

                @if($item->status === \App\Models\WarrantySupplierShipmentItem::STATUS_DIKIRIM_GUDANG)
                    <form method="POST" action="{{ route('warranty.supplier.items.arrived', [$shipment, $item]) }}"
                          class="mt-3 pt-3 border-t border-slate-100">
                        @csrf
                        <button class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white px-4 py-2 text-xs font-bold">
                            ✓ Unit pengganti sudah sampai gudang pusat
                        </button>
                    </form>
                @endif

                @if($shipment->status->canEditItems())
                    <form method="POST" action="{{ route('warranty.supplier.items.remove', [$shipment, $item]) }}"
                          class="mt-2 text-right" onsubmit="return confirm('Keluarkan unit ini dari pengiriman?')">
                        @csrf @method('DELETE')
                        <button class="text-rose-400 text-[11px] hover:underline">keluarkan dari pengiriman</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
@endsection
