@extends('layouts.app')

@section('title', 'Akun Baru')

@section('content')
    <a href="{{ route('users.index') }}" class="text-sm text-slate-500 hover:underline">← User Management</a>
    <h1 class="text-xl font-bold mt-2 mb-5">Buat Akun Baru</h1>

    @php
        $defaultBranchId = $branches->first()?->id;
        $selectedBranchIds = collect(old('branch_ids', $defaultBranchId ? [$defaultBranchId] : []))
            ->map(fn ($id) => (int) $id)
            ->all();
        $primaryBranchId = (int) old('branch_id', $selectedBranchIds[0] ?? 0);
    @endphp

    <form method="POST" action="{{ route('users.store') }}" class="bg-white rounded-xl border border-slate-200 p-5 max-w-2xl space-y-4">
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
                <label class="block text-xs font-semibold text-slate-600 mb-1">Gaji pokok / bulan (Rp)</label>
                <input type="text" inputmode="numeric" name="base_salary"
                    value="{{ old('base_salary') ? number_format(old('base_salary'), 0, ',', '.') : '' }}"
                    placeholder="0"
                    class="money-input w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>

        <div>
            <div class="flex items-start justify-between gap-3 mb-2">
                <div>
                    <label class="block text-xs font-semibold text-slate-600">Cabang absensi *</label>
                    <p class="text-[11px] text-slate-400 mt-0.5">
                        Centang semua cabang yang boleh dipakai akun ini. Tandai satu sebagai cabang utama untuk kompatibilitas fitur lama.
                    </p>
                </div>
            </div>

            @if($branches->isEmpty())
                <div class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-700">
                    Belum ada cabang. Buat cabang terlebih dahulu.
                </div>
            @else
                <div class="rounded-xl border border-slate-200 divide-y divide-slate-100 overflow-hidden" id="branchPicker">
                    @foreach($branches as $b)
                        @php $checked = in_array((int) $b->id, $selectedBranchIds, true); @endphp
                        <div class="flex items-center justify-between gap-4 px-3 py-3 hover:bg-slate-50 branch-row" data-branch-id="{{ $b->id }}">
                            <label class="flex items-start gap-3 min-w-0 cursor-pointer flex-1">
                                <input type="checkbox" name="branch_ids[]" value="{{ $b->id }}"
                                       class="branch-checkbox rounded mt-0.5" @checked($checked)>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-slate-700">{{ $b->name }}</span>
                                    <span class="block text-[11px] text-slate-400">
                                        {{ $b->code }}{{ $b->address ? ' · '.$b->address : '' }}
                                    </span>
                                </span>
                            </label>

                            <label class="inline-flex items-center gap-1.5 shrink-0 cursor-pointer text-xs font-medium text-slate-500">
                                <input type="radio" name="branch_id" value="{{ $b->id }}"
                                       class="branch-primary" required @checked($primaryBranchId === (int) $b->id)>
                                Utama
                            </label>
                        </div>
                    @endforeach
                </div>
            @endif

            @error('branch_ids')
                <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
            @enderror
            @error('branch_id')
                <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="is_active" value="1" checked class="rounded"> Akun aktif
        </label>

        <div class="flex items-center gap-3 pt-1">
            <button class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white px-5 py-2 text-sm font-bold">Buat Akun</button>
            <a href="{{ route('users.index') }}" class="text-sm text-slate-500 hover:underline">Batal</a>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const picker = document.getElementById('branchPicker');
            if (!picker) return;

            const checkboxes = Array.from(picker.querySelectorAll('.branch-checkbox'));
            const radios = Array.from(picker.querySelectorAll('.branch-primary'));

            function radioFor(checkbox) {
                return picker.querySelector('.branch-primary[value="' + checkbox.value + '"]');
            }

            function checkboxFor(radio) {
                return picker.querySelector('.branch-checkbox[value="' + radio.value + '"]');
            }

            radios.forEach(radio => {
                radio.addEventListener('change', function () {
                    if (radio.checked) checkboxFor(radio).checked = true;
                });
            });

            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function () {
                    const radio = radioFor(checkbox);

                    if (!checkbox.checked && radio.checked) {
                        radio.checked = false;
                        const next = checkboxes.find(item => item.checked);
                        if (next) radioFor(next).checked = true;
                    }

                    if (checkbox.checked && !radios.some(item => item.checked)) {
                        radio.checked = true;
                    }
                });
            });

            // Data lama/old input yang aneh tetap dibuat konsisten di browser.
            const checkedPrimary = radios.find(radio => radio.checked);
            if (checkedPrimary) {
                checkboxFor(checkedPrimary).checked = true;
            } else {
                const firstChecked = checkboxes.find(checkbox => checkbox.checked);
                if (firstChecked) radioFor(firstChecked).checked = true;
            }
        });
    </script>
@endsection
