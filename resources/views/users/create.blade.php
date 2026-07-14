@extends('layouts.app')

@section('title', 'Akun Baru')

@section('content')
    <a href="{{ route('users.index') }}" class="text-sm text-slate-500 hover:underline">← User Management</a>
    <h1 class="text-xl font-bold mt-2 mb-5">Buat Akun Baru</h1>

    <form method="POST" action="{{ route('users.store') }}" class="bg-white rounded-xl border border-slate-200 p-5 max-w-lg space-y-4">
        @csrf

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Nama *</label>
            <input type="text" name="name" value="{{ old('name') }}" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Email *</label>
            <input type="text" name="email" value="{{ old('email') }}" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">No. Telepon <span class="font-normal text-slate-400">(opsional — pegangan kontak)</span></label>
            <input type="text" name="phone" inputmode="tel" value="{{ old('phone') }}" maxlength="20"
                   placeholder="08xxxxxxxxxx"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Password * <span class="font-normal text-slate-400">(min. 6 karakter)</span></label>
            <div class="relative">
                <input type="password" name="password" id="pwField" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm pr-10">
                <button type="button" tabindex="-1"
                        onclick="var f=document.getElementById('pwField');f.type=f.type==='password'?'text':'password';this.textContent=f.type==='password'?'👁':'🙈'"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">👁</button>
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Role *</label>
                <select name="role" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
                    @foreach($roles as $r)
                        <option value="{{ $r->value }}" @selected(old('role') === $r->value)>{{ $r->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Cabang *</label>
                <select name="branch_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" @selected(old('branch_id') == $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Gaji pokok / bulan (Rp)</label>
                <input type="text" inputmode="numeric" name="base_salary"
                    value="{{ old('base_salary') ? number_format(old('base_salary'), 0, ',', '.') : '' }}"
                    placeholder="0"
                    class="money-input w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="is_active" value="1" checked class="rounded"> Akun aktif
        </label>

        <div class="flex items-center gap-3 pt-1">
            <button class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white px-5 py-2 text-sm font-bold">Buat Akun</button>
            <a href="{{ route('users.index') }}" class="text-sm text-slate-500 hover:underline">Batal</a>
        </div>
    </form>
@endsection