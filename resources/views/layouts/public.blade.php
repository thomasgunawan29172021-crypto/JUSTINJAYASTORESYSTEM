<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Lacak Servis') — Justin Jaya</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
    <header class="bg-slate-900 text-white">
        <div class="max-w-xl mx-auto px-4 h-14 flex items-center justify-between">
            <span class="font-extrabold tracking-tight">Justin Jaya<span class="text-emerald-400">.</span></span>
            <span class="text-[10px] tracking-[0.3em] text-slate-500 uppercase">Lacak Servis</span>
        </div>
    </header>

    <main class="max-w-xl mx-auto px-4 py-8">
        @yield('content')
    </main>

    <footer class="max-w-xl mx-auto px-4 pb-8 text-center text-xs text-slate-400">
        © {{ date('Y') }} Justin Jaya Store
    </footer>
</body>
</html>