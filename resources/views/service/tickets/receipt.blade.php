<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nota {{ $ticket->ticket_number }}</title>
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
        <a href="{{ route('service.tickets.show', $ticket) }}" class="rounded-lg bg-white border border-slate-300 px-4 py-2 text-sm font-semibold">← Kembali</a>
        <button onclick="window.print()" class="rounded-lg bg-slate-900 text-white px-4 py-2 text-sm font-semibold">🖨️ Cetak A4</button>
    </div>

    <div class="sheet mx-auto bg-white border border-slate-300 p-6 text-[12px] leading-snug">

        <div class="flex justify-between gap-4 pb-3 border-b-2 border-slate-800">
            <div class="flex gap-3 items-start max-w-[70mm]">
                <img src="{{ asset('images/logo.png') }}" alt="Justin Jaya" class="h-12 w-auto" onerror="this.style.display='none'">
                <div>
                    <p class="font-extrabold text-base leading-tight">JUSTIN JAYA</p>
                    <p class="text-[10px] text-slate-600 leading-tight">{{ $ticket->branch->name }}</p>
                    @if($ticket->branch->address)<p class="text-[10px] text-slate-600 leading-tight mt-0.5">{{ $ticket->branch->address }}</p>@endif
                    @if($ticket->branch->phone)<p class="text-[10px] text-slate-600">{{ $ticket->branch->phone }} (WA)</p>@endif
                </div>
            </div>

            <div class="text-center shrink-0">
                <p class="text-[10px] font-bold mb-1">SERVICE REPAIR</p>
                <div id="qr" class="mx-auto w-fit"></div>
                <p class="text-[9px] font-bold mt-1">CEK STATUS SERVICE</p>
            </div>

            <div class="border border-slate-400 rounded p-2 w-[62mm] shrink-0">
                <p class="text-[10px] font-bold text-center border-b border-slate-300 pb-1 mb-1.5">SERVICE RECEIVE</p>
                <table class="w-full text-[10px]">
                    <tr><td class="text-slate-500 w-16">Service No</td><td>: <b class="font-mono">{{ $ticket->ticket_number }}</b></td></tr>
                    <tr><td class="text-slate-500">Date</td><td>: {{ $ticket->checked_in_at->format('d-m-Y H:i') }}</td></tr>
                    <tr><td class="text-slate-500">Created By</td><td>: {{ $ticket->creator->name }}</td></tr>
                </table>
                <svg id="barcode" class="w-full mt-1"></svg>
            </div>
        </div>

        <div class="secbar mt-3">CUSTOMER INFORMATION</div>
        <table class="w-full mt-1.5 mb-1">
            <tr>
                <td class="text-slate-500 w-24 py-0.5">Customer</td><td class="w-[60mm]">: <b>{{ $ticket->customer_name }}</b></td>
                <td class="text-slate-500 w-20">No. HP</td><td>: {{ $ticket->customer_phone }}</td>
            </tr>
        </table>

        <div class="secbar mt-2">DEVICE INFORMATION</div>
        <table class="w-full mt-1.5 mb-1">
            <tr>
                <td class="text-slate-500 w-24 py-0.5">Unit</td><td class="w-[60mm]">: <b>{{ $ticket->device_brand }} {{ $ticket->device_model }}</b></td>
                <td class="text-slate-500 w-20">SN/IMEI</td><td>: <span class="font-mono">{{ $ticket->imei ?? '—' }}</span></td>
            </tr>
            <tr>
                <td class="text-slate-500 py-0.5 align-top">Keluhan</td><td class="align-top">: {{ $ticket->complaint }}</td>
                <td class="text-slate-500 align-top">Estimasi</td><td class="align-top">: {{ $ticket->estimated_done_at?->translatedFormat('d M Y') ?? '—' }}</td>
            </tr>
            <tr>
                <td class="text-slate-500 py-0.5 align-top">Kondisi fisik</td>
                <td class="align-top">: {{ $ticket->physical_condition ? implode(', ', $ticket->physical_condition) : '—' }}</td>
                <td class="text-slate-500 align-top">Kelengkapan</td>
                <td class="align-top">: {{ $ticket->accessories ? implode(', ', $ticket->accessories) : '—' }}</td>
            </tr>
            <tr>
                <td class="text-slate-500 py-0.5">Garansi servis</td><td colspan="3">: {{ $ticket->warranty_days }} hari setelah selesai</td>
            </tr>
        </table>

        <div class="secbar mt-2">SYARAT DAN KETENTUAN</div>
        <div class="columns-2 gap-5 mt-1.5 text-[9px] text-slate-600 leading-relaxed text-justify">
            <p class="mb-1">1. Pelanggan menyatakan data pada nota ini benar dan telah memeriksa kondisi unit bersama petugas saat penyerahan.</p>
            <p class="mb-1">2. Pelanggan disarankan mencadangkan data sebelum menyerahkan unit. Justin Jaya tidak bertanggung jawab atas kehilangan data selama proses pengecekan/perbaikan.</p>
            <p class="mb-1">3. Estimasi selesai dapat berubah mengikuti ketersediaan sparepart dan tingkat kerusakan. Perkembangan dapat dipantau melalui halaman lacak dengan nomor servis dan nomor HP.</p>
            <p class="mb-1">4. Garansi servis {{ $ticket->warranty_days }} hari hanya berlaku untuk keluhan dan sparepart yang sama, dan gugur apabila: unit terkena cairan, jatuh/benturan, segel dibuka pihak lain, atau kerusakan baru di luar pengerjaan.</p>
            <p class="mb-1">5. Kelengkapan yang diserahkan hanya yang tercantum pada nota ini. Kelengkapan yang tidak tercantum bukan tanggung jawab Justin Jaya.</p>
            <p class="mb-1">6. Unit yang tidak diambil dalam 30 hari sejak diberitahu selesai berada di luar tanggung jawab Justin Jaya.</p>
            <p class="mb-1">7. Pengambilan unit wajib membawa nota ini. Pengambilan oleh orang lain wajib disertai nota dan konfirmasi dari nomor HP terdaftar.</p>
            <p class="mb-1">8. Dengan menyerahkan unit, pelanggan dianggap telah membaca dan menyetujui seluruh ketentuan ini.</p>
        </div>

        <div class="grid grid-cols-2 gap-3 mt-4 max-w-[120mm]">
            @foreach([['PENERIMAAN','Petugas',$ticket->creator->name],['PENERIMAAN','Pelanggan',$ticket->customer_name],['PENGAMBILAN','Petugas',''],['PENGAMBILAN','Pelanggan','']] as [$fase,$siapa,$nama])
                <div class="border border-slate-400 rounded">
                    <p class="secbar text-center !py-0.5">{{ $fase }}</p>
                    <div class="h-14"></div>
                    <p class="text-center text-[10px] border-t border-slate-300 py-1">{{ $siapa }}{{ $nama ? ' — '.$nama : '' }}</p>
                </div>
            @endforeach
        </div>

        <p class="text-[9px] text-slate-400 text-center mt-3 border-t border-slate-200 pt-2">
            Lacak: <b>{{ route('track.form') }}</b> — masukkan no. servis + no. HP · Simpan nota ini sebagai bukti pengambilan unit
        </p>
    </div>

    <script>
        new QRCode(document.getElementById('qr'), {
            text: @json($ticket->trackingUrl()), width: 84, height: 84, correctLevel: QRCode.CorrectLevel.M
        });
        JsBarcode('#barcode', @json($ticket->ticket_number), {
            format: 'CODE128', displayValue: false, height: 26, margin: 0
        });
    </script>
</body>
</html>
