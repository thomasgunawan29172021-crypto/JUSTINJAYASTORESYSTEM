<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nota Retur {{ $claim->claim_number }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.5/JsBarcode.all.min.js"></script>
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
        <a href="{{ route('warranty.claims.show', $claim) }}" class="rounded-lg bg-white border border-slate-300 px-4 py-2 text-sm font-semibold">← Kembali</a>
        <button onclick="window.print()" class="rounded-lg bg-slate-900 text-white px-4 py-2 text-sm font-semibold">🖨️ Cetak A4</button>
    </div>

    <div class="sheet mx-auto bg-white border border-slate-300 p-6 text-[12px] leading-snug">

        {{-- ===== KOP 3 KOLOM (gaya MitraCare) ===== --}}
        <div class="flex justify-between gap-4 pb-3 border-b-2 border-slate-800">
            <div class="flex gap-3 items-start max-w-[70mm]">
                <img src="{{ asset('images/logo.png') }}" alt="Justin Jaya" class="h-12 w-auto" onerror="this.style.display='none'">
                <div>
                    <p class="font-extrabold text-base leading-tight">JUSTIN JAYA</p>
                    <p class="text-[10px] text-slate-600 leading-tight">{{ $claim->branch->name }}</p>
                    @if($claim->branch->address)<p class="text-[10px] text-slate-600 leading-tight mt-0.5">{{ $claim->branch->address }}</p>@endif
                    @if($claim->branch->phone)<p class="text-[10px] text-slate-600">{{ $claim->branch->phone }} (WA)</p>@endif
                </div>
            </div>

            <div class="text-center shrink-0">
                <p class="text-[10px] font-bold mb-1">KLAIM RETUR / GARANSI</p>
                <div id="qr" class="mx-auto w-fit"></div>
                <p class="text-[9px] font-bold mt-1">CEK STATUS RETUR</p>
            </div>

            <div class="border border-slate-400 rounded p-2 w-[62mm] shrink-0">
                <p class="text-[10px] font-bold text-center border-b border-slate-300 pb-1 mb-1.5">TANDA TERIMA RETUR</p>
                <table class="w-full text-[10px]">
                    <tr><td class="text-slate-500 w-16">No. Retur</td><td>: <b class="font-mono">{{ $claim->claim_number }}</b></td></tr>
                    <tr><td class="text-slate-500">Tanggal</td><td>: {{ $claim->created_at->format('d-m-Y H:i') }}</td></tr>
                    <tr><td class="text-slate-500">Petugas</td><td>: {{ $claim->creator->name }}</td></tr>
                </table>
                <svg id="barcode" class="w-full mt-1"></svg>
            </div>
        </div>

        {{-- ===== CUSTOMER ===== --}}
        <div class="secbar mt-3">CUSTOMER INFORMATION</div>
        <table class="w-full mt-1.5 mb-1">
            <tr>
                <td class="text-slate-500 w-24 py-0.5">Customer</td><td class="w-[60mm]">: <b>{{ $claim->customer_name }}</b></td>
                <td class="text-slate-500 w-20">No. HP</td><td>: {{ $claim->customer_phone }}</td>
            </tr>
            <tr>
                <td class="text-slate-500 py-0.5">No. Nota/Pesanan</td><td>: {{ $claim->order_number ?? '—' }}</td>
                <td class="text-slate-500">Tgl Beli</td><td>: {{ $claim->purchased_at?->format('d-m-Y') ?? '—' }}</td>
            </tr>
        </table>

        {{-- ===== DEVICE ===== --}}
        <div class="secbar mt-2">DEVICE INFORMATION</div>
        <table class="w-full mt-1.5 mb-1">
            <tr>
                <td class="text-slate-500 w-24 py-0.5 align-top">Produk</td><td class="w-[60mm] align-top">: <b>{{ $claim->product->name }}</b></td>
                <td class="text-slate-500 w-20 align-top">IMEI/SN</td><td class="align-top">: <span class="font-mono">{{ $claim->imei ?? '—' }}</span></td>
            </tr>
            <tr>
                <td class="text-slate-500 py-0.5 align-top">Kelengkapan</td>
                <td class="align-top">: {{ collect($claim->completeness)->map(fn ($i) => ucwords(str_replace('_',' ',$i)))->join(', ') ?: '—' }}</td>
                <td class="text-slate-500 align-top">Cabang</td><td class="align-top">: {{ $claim->branch->code }}</td>
            </tr>
            <tr>
                <td class="text-slate-500 py-0.5 align-top">Keluhan</td>
                <td colspan="3" class="align-top">: {{ $claim->reason }}</td>
            </tr>
        </table>

        {{-- ===== S&K DUA KOLOM ===== --}}
        <div class="secbar mt-2">SYARAT DAN KETENTUAN</div>
        <div class="columns-2 gap-5 mt-1.5 text-[9px] text-slate-600 leading-relaxed text-justify">
            <p class="mb-1">1. Pelanggan menyatakan data yang tercantum pada nota ini benar dan telah memeriksa kondisi barang bersama petugas saat penyerahan.</p>
            <p class="mb-1">2. Proses klaim mengikuti ketentuan garansi masing-masing brand/distributor. Justin Jaya bertindak sebagai perantara klaim; keputusan diterima/ditolaknya klaim sepenuhnya berada pada pihak brand/distributor/service center.</p>
            <p class="mb-1">3. Estimasi waktu proses mengikuti antrian pihak service center dan dapat berubah tanpa pemberitahuan. Perkembangan proses dapat dipantau melalui halaman lacak menggunakan nomor retur dan nomor HP.</p>
            <p class="mb-1">4. Garansi dapat ditolak apabila ditemukan: kerusakan akibat cairan, jatuh/benturan, perbaikan oleh pihak tidak resmi, segel rusak, atau perbedaan IMEI/nomor seri.</p>
            <p class="mb-1">5. Pelanggan disarankan mencadangkan data sebelum menyerahkan perangkat. Justin Jaya dan pihak service center tidak bertanggung jawab atas kehilangan data selama proses pengecekan/perbaikan.</p>
            <p class="mb-1">6. Kelengkapan yang diserahkan hanya yang tercantum pada nota ini. Kelengkapan yang tidak tercantum bukan tanggung jawab Justin Jaya.</p>
            <p class="mb-1">7. Apabila klaim ditolak oleh pihak brand/distributor, barang dikembalikan kepada pelanggan dalam kondisi apa adanya sesuai hasil pengecekan.</p>
            <p class="mb-1">8. Pengambilan barang wajib membawa nota ini. Pengambilan oleh orang lain wajib disertai nota dan konfirmasi dari nomor HP terdaftar.</p>
            <p class="mb-1">9. Barang yang tidak diambil dalam 30 hari sejak pemberitahuan "siap diambil" berada di luar tanggung jawab Justin Jaya.</p>
            <p class="mb-1">10. Dengan menyerahkan barang, pelanggan dianggap telah membaca dan menyetujui seluruh ketentuan ini.</p>
        </div>

        {{-- ===== TANDA TANGAN 4 KOTAK + FOTO KANAN BAWAH ===== --}}
        <div class="flex gap-4 mt-4">
            <div class="grid grid-cols-2 gap-3 flex-1">
                @foreach([['PENERIMAAN','Petugas',$claim->creator->name],['PENERIMAAN','Pelanggan',$claim->customer_name],['PENGAMBILAN','Petugas',''],['PENGAMBILAN','Pelanggan','']] as [$fase,$siapa,$nama])
                    <div class="border border-slate-400 rounded">
                        <p class="secbar text-center !py-0.5">{{ $fase }}</p>
                        <div class="h-14"></div>
                        <p class="text-center text-[10px] border-t border-slate-300 py-1">{{ $siapa }}{{ $nama ? ' — '.$nama : '' }}</p>
                    </div>
                @endforeach
            </div>

            @php $photo = $claim->photos->firstWhere('type', 'intake') ?? $claim->photos->first(); @endphp
            @if($photo)
                <div class="w-[42mm] shrink-0">
                    <p class="text-[9px] text-slate-500 text-center mb-1">Kondisi barang saat diterima</p>
                    <img src="{{ route('warranty.claims.photo', [$claim, $photo]) }}"
                         class="w-full aspect-square object-cover border border-slate-300 rounded">
                </div>
            @endif
        </div>

        <p class="text-[9px] text-slate-400 text-center mt-3 border-t border-slate-200 pt-2">
            Lacak: <b>{{ route('warranty.track.form') }}</b> — masukkan no. retur + no. HP · Simpan nota ini untuk pengambilan barang
        </p>
    </div>

    <script>
        new QRCode(document.getElementById('qr'), {
            text: @json($claim->trackingUrl()), width: 84, height: 84, correctLevel: QRCode.CorrectLevel.M
        });
        JsBarcode('#barcode', @json($claim->claim_number), {
            format: 'CODE128', displayValue: false, height: 26, margin: 0
        });
    </script>
</body>
</html>
