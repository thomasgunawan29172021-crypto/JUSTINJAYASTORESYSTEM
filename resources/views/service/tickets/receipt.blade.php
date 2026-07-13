<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota {{ $ticket->ticket_number }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen py-6 text-slate-800">
    <div class="max-w-md mx-auto px-4">
        <div class="no-print flex gap-2 mb-4">
            <a href="{{ route('service.tickets.show', $ticket) }}"
               class="rounded-lg bg-white border border-slate-300 px-4 py-2 text-sm font-semibold hover:border-slate-400">← Kembali</a>
            <button onclick="window.print()"
                    class="rounded-lg bg-slate-900 text-white px-4 py-2 text-sm font-semibold hover:bg-slate-800">🖨️ Cetak</button>
        </div>

        <div class="bg-white p-6 text-sm border border-slate-200">
            {{-- Kop --}}
            <div class="text-center border-b border-dashed border-slate-300 pb-3 mb-3">
                <p class="font-extrabold text-base">JUSTIN JAYA — {{ strtoupper($ticket->branch->name) }}</p>
                @if($ticket->branch->address)<p class="text-xs">{{ $ticket->branch->address }}</p>@endif
                @if($ticket->branch->phone)<p class="text-xs">Telp/WA: {{ $ticket->branch->phone }}</p>@endif
            </div>

            <p class="text-center font-mono font-bold text-lg mb-3">{{ $ticket->ticket_number }}</p>

            <table class="w-full text-xs">
                <tr><td class="py-0.5 text-slate-500 w-28 align-top">Tanggal masuk</td><td>: {{ $ticket->checked_in_at->translatedFormat('d M Y, H:i') }}</td></tr>
                @if($ticket->estimated_done_at)
                    <tr><td class="py-0.5 text-slate-500 align-top">Estimasi selesai</td><td>: {{ $ticket->estimated_done_at->translatedFormat('d M Y') }}</td></tr>
                @endif
                <tr><td class="py-0.5 text-slate-500 align-top">Customer</td><td>: {{ $ticket->customer_name }} ({{ $ticket->customer_phone }})</td></tr>
                <tr><td class="py-0.5 text-slate-500 align-top">Unit</td><td>: {{ $ticket->device_brand }} {{ $ticket->device_model }}@if($ticket->imei) · IMEI {{ $ticket->imei }}@endif</td></tr>
                <tr><td class="py-0.5 text-slate-500 align-top">Keluhan</td><td>: {{ $ticket->complaint }}</td></tr>
                @if($ticket->physical_condition)
                    <tr><td class="py-0.5 text-slate-500 align-top">Kondisi fisik</td><td>: {{ implode(', ', $ticket->physical_condition) }}</td></tr>
                @endif
                @if($ticket->accessories)
                    <tr><td class="py-0.5 text-slate-500 align-top">Kelengkapan</td><td>: {{ implode(', ', $ticket->accessories) }}</td></tr>
                @endif
                <tr><td class="py-0.5 text-slate-500 align-top">Garansi servis</td><td>: {{ $ticket->warranty_days }} hari setelah selesai</td></tr>
                <tr><td class="py-0.5 text-slate-500 align-top">Penerima</td><td>: {{ $ticket->creator->name }}</td></tr>
            </table>

            {{-- QR + cara lacak --}}
            <div class="border-t border-dashed border-slate-300 mt-3 pt-3 text-center">
                <div id="qrcode" class="mx-auto w-fit my-2"></div>
                <p class="text-[11px] text-slate-500">
                    Scan QR untuk cek status servis, atau buka<br>
                    <b>{{ route('track.form') }}</b><br>
                    masukkan nomor servis + nomor HP Anda.
                </p>
            </div>

            <div class="border-t border-dashed border-slate-300 mt-3 pt-3">
                <p class="text-[10px] text-slate-400 leading-relaxed">
                    S&K: Unit yang tidak diambil lebih dari 30 hari setelah dikabari selesai di luar tanggung jawab toko.
                    Garansi servis tidak berlaku untuk kerusakan baru, bekas air, atau segel dibuka pihak lain.
                    Simpan nota ini sebagai bukti pengambilan unit.
                </p>
            </div>

            {{-- Tanda tangan --}}
            <div class="grid grid-cols-2 gap-4 text-center text-xs mt-6">
                <div>
                    <p class="text-slate-500">Penerima Unit</p>
                    <p class="mt-12 border-t border-slate-300 pt-1">{{ $ticket->creator->name }}</p>
                </div>
                <div>
                    <p class="text-slate-500">Customer</p>
                    <p class="mt-12 border-t border-slate-300 pt-1">{{ $ticket->customer_name }}</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        new QRCode(document.getElementById('qrcode'), {
            text: @json($ticket->trackingUrl()),
            width: 112,
            height: 112,
        });
    </script>
</body>
</html>