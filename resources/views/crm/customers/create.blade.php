@extends('layouts.app')

@section('title', 'Pelanggan Baru')

@section('content')
    <div class="flex items-center gap-3 mb-5">
        <a href="{{ route('crm.customers.index') }}" class="text-slate-400 hover:text-slate-700">
            ← Kembali
        </a>
        <h1 class="text-xl font-bold">Pelanggan Baru</h1>
    </div>

    {{-- ===== BOX WARNING DEDUP ===== --}}
    @if(!empty($duplicates) && $duplicates->isNotEmpty())
        <div class="mb-5 rounded-xl border border-amber-300 bg-amber-50 px-4 py-4">
            <p class="font-bold text-amber-800 mb-2">⚠️ Pelanggan serupa mungkin sudah ada:</p>
            <ul class="space-y-1 mb-4">
                @foreach($duplicates as $d)
                    <li class="text-sm text-amber-700">
                        <a href="{{ route('crm.customers.show', $d['id']) }}"
                        class="font-semibold underline" target="_blank">
                            {{ $d['name'] }}
                        </a>
                        —
                        {{ $d['reason'] }}
                        <span class="text-xs text-amber-500">(skor: {{ $d['score'] }})</span>
                    </li>
                @endforeach
            </ul>
            {{-- Re-submit form yang sama + confirm_create = 1 --}}
            <form method="POST" action="{{ route('crm.customers.store') }}">
                @csrf
                <input type="hidden" name="confirm_create" value="1">
                {{-- Semua field dari old() --}}
                <input type="hidden" name="name"    value="{{ old('name') }}">
                <input type="hidden" name="address" value="{{ old('address') }}">
                <input type="hidden" name="source"  value="{{ old('source') }}">
                <input type="hidden" name="source_other" value="{{ old('source_other') }}">
                <input type="hidden" name="branch_id" value="{{ old('branch_id') }}">
                <input type="hidden" name="notes"   value="{{ old('notes') }}">
                @foreach(old('contact_type', []) as $i => $ct)
                    <input type="hidden" name="contact_type[]"  value="{{ $ct }}">
                    <input type="hidden" name="contact_value[]" value="{{ old('contact_value')[$i] ?? '' }}">
                    <input type="hidden" name="contact_primary" value="{{ old('contact_primary', 0) }}">
                @endforeach
                <button type="submit"
                        class="rounded-xl bg-amber-500 hover:bg-amber-400 text-white text-sm font-bold px-4 py-2">
                    Ya, tetap buat pelanggan baru
                </button>
            </form>
        </div>
    @endif

    {{-- ===== FORM UTAMA ===== --}}
    <form method="POST" action="{{ route('crm.customers.store') }}" data-draft="crm-customer-create">
        @csrf

        <div class="grid gap-5 lg:grid-cols-2">

            {{-- Kolom kiri --}}
            <div class="space-y-4">
                <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
                    <h2 class="font-bold text-slate-700">Informasi Pelanggan</h2>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Nama <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm @error('name') border-rose-400 @enderror">
                        @error('name')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Alamat <span class="text-slate-400 font-normal">(opsional)</span></label>
                        <input type="text" name="address" value="{{ old('address') }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Sumber Pelanggan</label>
                        <select name="source" id="source-select"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white"
                                onchange="toggleSourceOther(this.value)">
                            <option value="">— Pilih —</option>
                            @foreach(['walk-in','referral','tiktok','instagram','facebook','whatsapp'] as $src)
                                <option value="{{ $src }}" @selected(old('source') === $src)>{{ ucfirst($src) }}</option>
                            @endforeach
                            <option value="lainnya" @selected(old('source') === 'lainnya')>Lainnya</option>
                        </select>
                        <input type="text" name="source_other" id="source-other"
                               value="{{ old('source_other') }}"
                               placeholder="Tulis sumber lainnya…"
                               class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm {{ old('source') !== 'lainnya' ? 'hidden' : '' }}">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Cabang <span class="text-rose-500">*</span></label>

                        @if($user->isManager())
                            <select name="branch_id" required
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white @error('branch_id') border-rose-400 @enderror">
                                <option value="">— Pilih cabang —</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}" @selected(old('branch_id') == $b->id)>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        @else
                            {{-- Non-manager: dikunci ke cabang sendiri, gak bisa salah pilih --}}
                            <input type="text" value="{{ $user->branch->name }}" disabled
                                class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">
                            <input type="hidden" name="branch_id" value="{{ $user->branch_id }}">
                        @endif

                        @error('branch_id')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Catatan <span class="text-slate-400 font-normal">(opsional)</span></label>
                        <textarea name="notes" rows="3"
                                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Kolom kanan: kontak --}}
            <div class="space-y-4">
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="font-bold text-slate-700">Kontak</h2>
                        <button type="button" onclick="addContact()"
                                class="text-xs font-semibold text-emerald-600 hover:text-emerald-500">+ Tambah kontak</button>
                    </div>

                    <div id="contact-rows" class="space-y-3">
                        {{-- Render ulang dari old() kalau ada, atau 1 baris kosong --}}
                        @php
                            $oldTypes   = old('contact_type',  ['whatsapp']);
                            $oldValues  = old('contact_value', ['']);
                            $oldPrimary = old('contact_primary', 0);
                        @endphp
                        @foreach($oldTypes as $i => $ct)
                            <div class="contact-row flex items-center gap-2">
                                <select name="contact_type[]"
                                        class="rounded-lg border border-slate-300 px-2 py-2 text-sm bg-white w-32 flex-shrink-0">
                                    @foreach(['whatsapp','phone','email','other'] as $type)
                                        <option value="{{ $type }}" @selected($ct === $type)>{{ ucfirst($type) }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="contact_value[]"
                                       value="{{ $oldValues[$i] ?? '' }}"
                                       placeholder="Nomor / alamat…"
                                       class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <label class="flex items-center gap-1 text-xs text-slate-500 flex-shrink-0">
                                    <input type="radio" name="contact_primary" value="{{ $i }}"
                                           @checked($oldPrimary == $i)>
                                    Utama
                                </label>
                                @if($i > 0)
                                    <button type="button" onclick="this.closest('.contact-row').remove()"
                                            class="text-rose-400 hover:text-rose-600 text-lg leading-none">×</button>
                                @else
                                    <span class="w-5"></span>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @error('contact_value.0')
                        <p class="text-xs text-rose-500 mt-2">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div class="mt-5 flex gap-3">
            <button type="submit"
                    class="rounded-xl bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-bold px-6 py-2.5">
                Simpan Pelanggan
            </button>
            <a href="{{ route('crm.customers.index') }}"
               class="rounded-xl border border-slate-300 text-slate-600 text-sm font-semibold px-5 py-2.5 hover:bg-slate-50">
                Batal
            </a>
        </div>
    </form>
@endsection

@push('scripts')
<script>
function toggleSourceOther(val) {
    document.getElementById('source-other').classList.toggle('hidden', val !== 'lainnya');
}

function addContact() {
    var rows  = document.getElementById('contact-rows');
    var index = rows.querySelectorAll('.contact-row').length;
    var tpl   = document.querySelector('.contact-row').cloneNode(true);

    // Reset value clone
    tpl.querySelector('select').selectedIndex = 0;
    tpl.querySelector('input[type="text"]').value = '';

    var radio = tpl.querySelector('input[type="radio"]');
    radio.value   = index;
    radio.checked = false;

    // Tombol hapus — baris baru selalu boleh dihapus
    var delBtn = tpl.querySelector('button[onclick]');
    if (delBtn) {
        delBtn.onclick = function() { tpl.remove(); };
    } else {
        // Baris pertama tadinya cuma <span> placeholder, ganti jadi tombol hapus
        var span = tpl.querySelector('span.w-5');
        if (span) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'text-rose-400 hover:text-rose-600 text-lg leading-none';
            btn.textContent = '×';
            btn.onclick = function() { tpl.remove(); };
            span.replaceWith(btn);
        }
    }

    rows.appendChild(tpl);
}
</script>
@endpush