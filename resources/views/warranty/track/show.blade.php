<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $claim->claim_number }} — Lacak Retur</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen p-4">
    <div class="max-w-md mx-auto">
        <p class="text-center font-extrabold text-xl mt-2 mb-4">Justin Jaya<span class="text-emerald-500">.</span></p>

        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm mb-4">
            <p class="text-center font-mono font-bold">{{ $claim->claim_number }}</p>
            <p class="text-center text-sm text-slate-500 mb-1">{{ $claim->product->name }}</p>

            @if($claim->status->value === 'batal')
                <p class="text-center mt-2 px-3 py-2 rounded-xl bg-slate-100 text-sm font-semibold text-slate-600">⛔ Klaim dibatalkan</p>
            @else
                <p class="text-center mt-2 px-3 py-2 rounded-xl bg-emerald-50 text-sm font-semibold text-emerald-700">
                    {{ $claim->status->label() }}
                </p>
                @if($claim->outcome)
                    <p class="text-center mt-1 text-xs font-bold {{ $claim->outcome === 'diterima' ? 'text-emerald-600' : 'text-rose-600' }}">
                        Hasil pengecekan: {{ strtoupper($claim->outcome) }}
                    </p>
                @endif
            @endif
        </div>

        @if($claim->status->value !== 'batal')
            @php
                $timeline = \App\Enums\WarrantyClaimStatus::timeline();
                $curIdx = array_search($claim->status, $timeline, true);
            @endphp
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm mb-4">
                <div class="space-y-3">
                    @foreach($timeline as $i => $st)
                        <div class="flex items-center gap-3 text-sm">
                            <span class="w-6 h-6 shrink-0 rounded-full grid place-items-center text-[11px] font-bold
                                {{ $i < $curIdx ? 'bg-emerald-500 text-white' : ($i === $curIdx ? 'bg-emerald-100 text-emerald-700 ring-2 ring-emerald-400' : 'bg-slate-100 text-slate-400') }}">
                                {{ $i < $curIdx ? '✓' : $i + 1 }}
                            </span>
                            <span class="{{ $i === $curIdx ? 'font-bold' : ($i < $curIdx ? 'text-slate-600' : 'text-slate-400') }}">
                                {{ $st->label() }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <p class="text-xs font-semibold text-slate-500 uppercase mb-3">Riwayat</p>
            <div class="space-y-2.5">
                @foreach($claim->histories->sortByDesc('created_at') as $h)
                    <div class="text-sm">
                        <p>
                            @if($h->is_followup)
                                📣 <b>Telah di-follow up</b> oleh {{ $claim->branch->name }}
                            @else
                                {{ $h->to_status?->label() }}
                            @endif
                        </p>
                        @if($h->note && ! $h->is_followup)<p class="text-xs text-slate-500">{{ $h->note }}</p>@endif
                        <p class="text-[11px] text-slate-400">{{ $h->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <p class="text-center text-[11px] text-slate-400 mt-4 mb-2">Ada pertanyaan? Hubungi toko tempat Anda menyerahkan barang.</p>
    </div>
</body>
</html>
