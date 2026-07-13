<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 — Akses Ditolak</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 flex items-center justify-center p-6">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm max-w-md w-full p-8 text-center">
        <div class="text-5xl font-black text-slate-300 mb-2">403</div>
        <h1 class="text-lg font-bold text-slate-800 mb-1">Akses Ditolak</h1>
        <p class="text-sm text-slate-500 mb-6">
            {{ $exception?->getMessage() ?: 'Kamu tidak punya akses ke halaman ini.' }}
        </p>
        <div class="flex flex-col sm:flex-row gap-2 justify-center">
            <a href="{{ url()->previous() }}"
               class="rounded-lg bg-white border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:border-slate-400">
                ← Kembali
            </a>
            <a href="{{ route('dashboard') }}"
               class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white px-5 py-2.5 text-sm font-semibold">
                🏠 Ke Dashboard
            </a>
        </div>
    </div>
</body>
</html>