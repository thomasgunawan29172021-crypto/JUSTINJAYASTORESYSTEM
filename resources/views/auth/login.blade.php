<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Justin Jaya Command Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-900 flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
        <div class="text-center mb-6">
            <p class="text-2xl font-extrabold text-white">Justin Jaya<span class="text-emerald-400">.</span></p>
            <p class="text-[10px] tracking-[0.3em] text-slate-500 uppercase mt-1">Command Center</p>
        </div>

        <form method="POST" action="{{ route('login.attempt') }}" class="bg-white rounded-2xl p-6 space-y-4 shadow-xl">
            @csrf

            @if($errors->any())
                <div class="rounded-lg bg-rose-50 border border-rose-200 text-rose-700 px-3 py-2 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Email</label>
                <input type="text" name="email" value="{{ old('email') }}" required autofocus
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Password</label>
                <div class="relative">
                    <input type="password" name="password" id="pwField" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm pr-10 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <button type="button" tabindex="-1"
                            onclick="var f=document.getElementById('pwField');f.type=f.type==='password'?'text':'password';this.textContent=f.type==='password'?'👁':'🙈'"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">👁</button>
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" value="1" class="rounded"> Ingat saya
            </label>

            <button class="w-full rounded-lg bg-slate-900 text-white py-2.5 text-sm font-semibold hover:bg-slate-800">
                Masuk
            </button>
        </form>
    </div>
</body>
</html>