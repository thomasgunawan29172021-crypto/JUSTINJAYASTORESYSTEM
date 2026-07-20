<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lacak Retur — Justin Jaya</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen grid place-items-center p-4">
    <div class="w-full max-w-sm">
        <p class="text-center font-extrabold text-2xl mb-1">Justin Jaya<span class="text-emerald-500">.</span></p>
        <p class="text-center text-sm text-slate-500 mb-5">Lacak status klaim retur / garansi</p>

        <form method="POST" action="{{ route('warranty.track.lookup') }}"
              class="bg-white rounded-2xl border border-slate-200 p-5 space-y-4 shadow-sm">
            @csrf
            @if($errors->any())
                <div class="rounded-xl bg-rose-50 border border-rose-200 px-3 py-2 text-xs text-rose-700">
                    {{ $errors->first() }}
                </div>
            @endif
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Nomor retur</label>
                <input type="text" name="claim_number" value="{{ old('claim_number') }}" required
                       placeholder="RT-XXX-0000-0000"
                       class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm font-mono uppercase">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">No. HP saat klaim</label>
                <input type="tel" name="phone" value="{{ old('phone') }}" required placeholder="08xxxxxxxxxx"
                       class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm">
            </div>
            <button class="w-full rounded-xl bg-emerald-500 hover:bg-emerald-400 text-white py-3 text-sm font-bold">
                Lacak
            </button>
        </form>

        <p class="text-center text-[11px] text-slate-400 mt-4">
            Servis HP? <a href="{{ route('track.form') }}" class="text-emerald-600 hover:underline">Lacak servis di sini</a>
        </p>
    </div>
</body>
</html>
