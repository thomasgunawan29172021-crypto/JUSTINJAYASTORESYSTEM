@extends('layouts.app')

@section('title', 'Edit ' . $customer->name)

@section('content')
    <div class="flex items-center gap-3 mb-5">
        <a href="{{ route('crm.customers.show', $customer) }}" class="text-slate-400 hover:text-slate-700">
            ← Kembali
        </a>
        <h1 class="text-xl font-bold">Edit Pelanggan</h1>
    </div>

    <form method="POST" action="{{ route('crm.customers.update', $customer) }}">
        @csrf @method('PUT')

        <div class="max-w-xl space-y-4">
            <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
                <h2 class="font-bold text-slate-700">Informasi Pelanggan</h2>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Nama <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $customer->name) }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm @error('name') border-rose-400 @enderror">
                    @error('name')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Alamat</label>
                    <input type="text" name="address" value="{{ old('address', $customer->address) }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Sumber Pelanggan</label>
                    <select name="source" id="source-select"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white"
                            onchange="toggleSourceOther(this.value)">
                        <option value="">— Pilih —</option>
                        @foreach(['walk-in','referral','tiktok','instagram','facebook','whatsapp'] as $src)
                            <option value="{{ $src }}" @selected(old('source', $customer->source) === $src)>{{ ucfirst($src) }}</option>
                        @endforeach
                        <option value="lainnya" @selected(!in_array($customer->source, ['walk-in','referral','tiktok','instagram','facebook','whatsapp','',null]))>Lainnya</option>
                    </select>
                    @php
                        $isOther = $customer->source && !in_array($customer->source, ['walk-in','referral','tiktok','instagram','facebook','whatsapp']);
                    @endphp
                    <input type="text" name="source_other" id="source-other"
                           value="{{ old('source_other', $isOther ? $customer->source : '') }}"
                           placeholder="Tulis sumber lainnya…"
                           class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm {{ (!old('source') && !$isOther) ? 'hidden' : '' }}">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Cabang <span class="text-rose-500">*</span></label>
                    <select name="branch_id" required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" @selected(old('branch_id', $customer->branch_id) == $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Catatan</label>
                    <textarea name="notes" rows="3"
                              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('notes', $customer->notes) }}</textarea>
                </div>
            </div>

            <div class="flex gap-3">
                <button type="submit"
                        class="rounded-xl bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-bold px-6 py-2.5">
                    Simpan Perubahan
                </button>
                <a href="{{ route('crm.customers.show', $customer) }}"
                   class="rounded-xl border border-slate-300 text-slate-600 text-sm font-semibold px-5 py-2.5 hover:bg-slate-50">
                    Batal
                </a>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
function toggleSourceOther(val) {
    document.getElementById('source-other').classList.toggle('hidden', val !== 'lainnya');
}
</script>
@endpush