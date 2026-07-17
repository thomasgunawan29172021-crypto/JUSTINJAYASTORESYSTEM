@extends('layouts.app')

@section('title', 'Edit '.$store->name)

@section('content')
    <a href="{{ route('marketplace.stores.index') }}" class="text-sm text-slate-500 hover:underline">← Toko Marketplace</a>
    <h1 class="text-xl font-bold mt-2 mb-5">Edit Toko — {{ $store->name }}</h1>

    <form method="POST" action="{{ route('marketplace.stores.update', $store) }}"
          class="bg-white rounded-xl border border-slate-200 p-5 max-w-lg space-y-4">
        @csrf @method('PUT')

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Nama toko *</label>
            <input type="text" name="name" value="{{ old('name', $store->name) }}" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Marketplace *</label>
            <input type="text" name="marketplace" value="{{ old('marketplace', $store->marketplace) }}" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Tier *</label>
            <input type="text" name="tier" value="{{ old('tier', $store->tier) }}" required
                   placeholder="biasa / star / mall" list="tier-list"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <datalist id="tier-list">
                @foreach($tierOptions ?? [] as $t)
                    <option value="{{ $t }}">
                @endforeach
            </datalist>
            <p class="text-[11px] text-slate-400 mt-1">
                Menentukan biaya admin marketplace yang dipakai buat hitung harga jual rekomendasi.
                Tier <b>mall</b> otomatis menandai toko ini sebagai Toko Mall — tidak perlu diatur terpisah.
            </p>
        </div>

        <div class="border-t border-slate-100 pt-4">
            <p class="text-xs font-semibold text-slate-600 mb-1">🔑 Kredensial Akun Toko</p>
            <p class="text-[11px] text-slate-400 mb-3">
                Murni pegangan CEO buat login ke akun marketplace toko ini — tidak mempengaruhi sistem/tugas posting.
            </p>

            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Email / Username Login</label>
                    <input type="text" name="account_email" value="{{ old('account_email', $store->account_email) }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">No. Telepon Terdaftar</label>
                    <input type="text" name="account_phone" value="{{ old('account_phone', $store->account_phone) }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Password</label>
                    <div class="relative">
                        <input type="password" name="account_password" id="storePwField"
                               value="{{ old('account_password', $store->account_password) }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm pr-10">
                        <button type="button" tabindex="-1"
                                onclick="var f=document.getElementById('storePwField');f.type=f.type==='password'?'text':'password';this.textContent=f.type==='password'?'👁':'🙈'"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">👁</button>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Kosongkan lalu simpan untuk menghapus password.</p>
                </div>
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $store->is_active)) class="rounded">
            Toko aktif
        </label>

        <div class="flex items-center gap-3 pt-1">
            <button class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white px-5 py-2 text-sm font-bold">Simpan</button>
            <a href="{{ route('marketplace.stores.index') }}" class="text-sm text-slate-500 hover:underline">Batal</a>
        </div>
    </form>
@endsection