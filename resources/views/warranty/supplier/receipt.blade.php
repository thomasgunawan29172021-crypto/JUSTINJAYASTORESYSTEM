<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Tanda Terima {{ $shipment->shipment_number }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @page { size: A4; margin: 10mm; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .sheet { border: none !important; box-shadow: none !important; width: 100% !important; }
        }
        .sheet { width: 190mm; min-height: 270mm; }
        .secbar { background: #1e293b; color: white; font-size: 11px; font-weight: 700; padding: 3px 10px; letter-spacing: .05em; }
    </style>
</head>
<body class="bg-slate-200 text-slate-800 py-6">
    <div class="no-print max-w-[190mm] mx-auto flex gap-2 mb-3">
        <a href="{{ route('warranty.supplier.show', $shipment) }}" class="rounded-lg bg-white border border-slate-300 px-4 py-2 text-sm font-semibold">← Kembali</a>
        <button onclick="window.print()" class="rounded-lg bg-slate-900 text-white px-4 py-2 text-sm font-semibold">🖨️ Cetak A4</button>
    </div>

    <div class="sheet mx-auto bg-white border border-slate-300 p-6 text-[12px] leading-snug">

        {{-- ===== KOP ===== --}}
        <div class="flex justify-between gap-4 pb-3 border-b-2 border-slate-800">
            <div class="flex gap-3 items-start max-w-[95mm]">
                <img src="{{ asset('images/logo.png') }}" alt="Justin Jaya" class="h-12 w-auto" onerror="this.style.display='none'">
                <div>
                    <p class="font-extrabold text-base leading-tight">JUSTIN JAYA</p>
                    <p class="text-[10px] text-slate-600 leading-tight">Klaim garansi ke distributor / service center</p>
                </div>
            </div>

            <div class="border border-slate-400 rounded p-2 w-[70mm] shrink-0">
                <p class="text-[10px] font-bold text-center border-b border-slate-300 pb-1 mb-1.5">SURAT TANDA TERIMA</p>
                <table class="w-full text-[10px]">
                    <tr><td class="text-slate-500 w-20">No. Kiriman</td><td>: <b class="font-mono">{{ $shipment->shipment_number }}</b></td></tr>
                    <tr><td class="text-slate-500">Tanggal</td><td>: {{ ($shipment->shipped_at ?? $shipment->created_at)->format('d-m-Y') }}</td></tr>
                    <tr><td class="text-slate-500">Jumlah</td><td>: <b>{{ $shipment->items->count() }} unit</b></td></tr>
                </table>
            </div>
        </div>

        {{-- ===== TUJUAN ===== --}}
        <div class="secbar mt-3">DITERIMA OLEH</div>
        <table class="w-full mt-1.5 mb-1">
            <tr>
                <td class="text-slate-500 w-24 py-0.5">Vendor</td><td class="w-[70mm]">: <b>{{ $shipment->vendor->name }}</b></td>
                <td class="text-slate-500 w-16">Kontak</td><td>: {{ $shipment->vendor->phone ?? '—' }}</td>
            </tr>
            @if($shipment->note)
                <tr><td class="text-slate-500 py-0.5 align-top">Catatan</td><td colspan="3" class="align-top">: {{ $shipment->note }}</td></tr>
            @endif
        </table>

        {{-- ===== DAFTAR UNIT ===== --}}
        <div class="secbar mt-3">DAFTAR UNIT YANG DISERAHKAN</div>
        <table class="w-full mt-1.5 border border-slate-300 border-collapse">
            <thead>
                <tr class="bg-slate-100 text-[10px]">
                    <th class="border border-slate-300 px-1.5 py-1 w-6">#</th>
                    <th class="border border-slate-300 px-1.5 py-1 text-left">No. Retur</th>
                    <th class="border border-slate-300 px-1.5 py-1 text-left">Produk</th>
                    <th class="border border-slate-300 px-1.5 py-1 text-left">IMEI / Serial</th>
                    <th class="border border-slate-300 px-1.5 py-1 text-left">Kelengkapan</th>
                    <th class="border border-slate-300 px-1.5 py-1 text-left">Keluhan</th>
                </tr>
            </thead>
            <tbody class="text-[10px]">
                @foreach($shipment->items as $i => $item)
                    @php $claim = $item->claim; @endphp
                    <tr>
                        <td class="border border-slate-300 px-1.5 py-1 text-center">{{ $i + 1 }}</td>
                        <td class="border border-slate-300 px-1.5 py-1 font-mono whitespace-nowrap">{{ $claim->claim_number }}</td>
                        <td class="border border-slate-300 px-1.5 py-1">{{ $claim->product->name }}</td>
                        <td class="border border-slate-300 px-1.5 py-1 font-mono">{{ $claim->imei ?? '—' }}</td>
                        <td class="border border-slate-300 px-1.5 py-1">{{ implode(', ', $claim->completenessLabels()) ?: '—' }}</td>
                        <td class="border border-slate-300 px-1.5 py-1">{{ $claim->reason }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- ===== TANDA TANGAN ===== --}}
        <div class="flex justify-between gap-8 mt-8 text-[11px] text-center">
            <div class="flex-1">
                <p>Diserahkan oleh,</p>
                <div class="h-16"></div>
                <p class="border-t border-slate-400 pt-1">Justin Jaya</p>
            </div>
            <div class="flex-1">
                <p>Diterima oleh,</p>
                <div class="h-16"></div>
                <p class="border-t border-slate-400 pt-1">{{ $shipment->vendor->name }}</p>
            </div>
        </div>

        <p class="text-[9px] text-slate-500 mt-6 pt-2 border-t border-slate-200">
            Surat ini bukti penyerahan barang klaim garansi. Simpan sebagai dasar penagihan penggantian
            (unit pengganti maupun penggantian uang) atas unit-unit yang tercantum di atas.
        </p>
    </div>
</body>
</html>
