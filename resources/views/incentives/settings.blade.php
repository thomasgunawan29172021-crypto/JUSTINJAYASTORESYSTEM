@extends('layouts.app')
@section('title', 'Pengaturan Insentif')
@section('content')
@php
    $money = fn($n, $c) => ($c === 'IDR' ? 'Rp ' : 'NT$ ').number_format((float) $n, 0, ',', '.');
    $editing = (bool) $editingTier;
    $formType = old('type', $editingTier?->type ?? 'fl');
    $targetDisplay = old('min_target') !== null
        ? number_format((float) str_replace('.', '', (string) old('min_target')), 0, ',', '.')
        : ($editingTier ? number_format((float) $editingTier->min_target, 0, ',', '.') : '');
    $rewardRaw = old('reward_value', $editingTier?->reward_value);
@endphp

<div class="mx-auto max-w-6xl space-y-5">
    <div>
        <a href="{{ route('incentives.index') }}" class="text-sm text-emerald-700">← Kembali</a>
        <h1 class="mt-1 text-2xl font-bold">Pengaturan target insentif</h1>
        <p class="text-sm text-slate-500">Target berlaku sama untuk seluruh promotor dalam cabang yang dipilih. Perubahan sekarang efektif 1 {{ \Carbon\Carbon::parse($effectiveDate)->translatedFormat('F Y') }} dan tidak mengubah bulan lama.</p>
    </div>

    @if(session('ok'))<div class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-700">{{ session('ok') }}</div>@endif
    @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 text-sm text-rose-700">@foreach($errors->all() as $error)<p>• {{ $error }}</p>@endforeach</div>@endif

    <form id="tier-form" method="POST" action="{{ $editing ? route('incentives.settings.update', $editingTier) : route('incentives.settings.store') }}" class="grid gap-3 rounded-xl border {{ $editing ? 'border-amber-300 bg-amber-50' : 'bg-white' }} p-4 md:grid-cols-2 xl:grid-cols-6">
        @csrf @if($editing) @method('PUT') @endif
        <label>Cabang<select name="branch_id" required class="mt-1 block w-full rounded-lg border p-2"><option value="">Pilih cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id', $editingTier?->branch_id) == $branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
        <label>Mata uang<select name="currency" required class="mt-1 block w-full rounded-lg border p-2">@foreach(\App\Models\IncentiveSubmission::CURRENCIES as $code => $label)<option value="{{ $code }}" @selected(old('currency', $editingTier?->currency ?? 'IDR') === $code)>{{ $label }}</option>@endforeach</select></label>
        <label>Tipe<select name="type" id="tier-type" required class="mt-1 block w-full rounded-lg border p-2">@foreach(\App\Models\IncentiveTier::TYPES as $key => $label)<option value="{{ $key }}" @selected($formType === $key)>{{ $label }}</option>@endforeach</select></label>
        <label>Nama peringkat <span class="text-xs text-slate-400">(opsional)</span><input name="label" maxlength="80" value="{{ old('label', $editingTier?->label) }}" placeholder="Bronze / Bintang 1" class="mt-1 block w-full rounded-lg border p-2"></label>
        <label><span id="target-label">Target keuntungan</span><input name="min_target" data-tier-money inputmode="numeric" required value="{{ $targetDisplay }}" class="mt-1 block w-full rounded-lg border p-2" placeholder="2.500.000"></label>
        <label><span id="reward-label">Insentif (%)</span><div class="mt-1 flex gap-2"><input name="reward_value" id="reward-value" inputmode="decimal" required value="{{ filled($rewardRaw) ? ($formType === 'social' ? number_format((float) $rewardRaw, 0, ',', '.') : rtrim(rtrim(number_format((float) $rewardRaw, 4, ',', '.'), '0'), ',')) : '' }}" class="block min-w-0 flex-1 rounded-lg border p-2"><button class="rounded-lg bg-emerald-700 px-4 font-semibold text-white">{{ $editing ? 'Update' : 'Simpan' }}</button></div></label>
        @if($editing)<div class="md:col-span-2 xl:col-span-6 flex items-center justify-between gap-3"><p class="text-xs text-amber-800">Mode edit: {{ $editingTier->branch->name }} · {{ \App\Models\IncentiveTier::TYPES[$editingTier->type] }}</p><a href="{{ route('incentives.settings') }}" class="rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm">Batal edit</a></div>@endif
    </form>

    <div class="space-y-4">
        @forelse($tiers->groupBy(fn($t) => $t->branch->name.'|'.$t->currency.'|'.$t->type) as $key => $group)
            @php [$branchName, $currency, $type] = explode('|', $key); $first = $group->first(); @endphp
            <section class="overflow-hidden rounded-xl border bg-white">
                <div class="flex flex-col gap-3 bg-slate-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <div><h2 class="font-bold">{{ $branchName }} · {{ $currency }}</h2><p class="text-xs text-slate-500">{{ \App\Models\IncentiveTier::TYPES[$type] }} · efektif {{ $first->effective_from->format('d/m/Y') }}</p></div>
                    <form method="POST" action="{{ route('incentives.settings.copy-all') }}" data-no-draft onsubmit="return confirm('Salin seluruh tingkat pada grup ini ke semua cabang untuk bulan berikutnya?')">@csrf<input type="hidden" name="source_branch_id" value="{{ $first->branch_id }}"><input type="hidden" name="currency" value="{{ $currency }}"><input type="hidden" name="type" value="{{ $type }}"><button class="rounded-lg border border-sky-300 bg-white px-3 py-2 text-xs font-semibold text-sky-700">Salin ke semua cabang</button></form>
                </div>
                <div class="overflow-x-auto"><table class="w-full min-w-[650px] text-sm"><thead class="text-left text-xs uppercase text-slate-500"><tr><th class="px-5 py-2">Peringkat</th><th class="px-3 py-2">Mulai target</th><th class="px-3 py-2">Insentif</th><th class="px-5 py-2 text-right">Aksi</th></tr></thead><tbody class="divide-y">@foreach($group as $tier)<tr><td class="px-5 py-3 font-medium">{{ $tier->label ?: '—' }}</td><td class="px-3 py-3">{{ $type === 'social' ? number_format((float) $tier->min_target, 0, ',', '.').' orang' : $money($tier->min_target, $currency) }}</td><td class="px-3 py-3 font-semibold">{{ $type === 'social' ? $money($tier->reward_value, $currency).' / orang' : rtrim(rtrim(number_format((float) $tier->reward_value, 4, ',', '.'), '0'), ',').'%' }}</td><td class="px-5 py-3"><div class="flex justify-end gap-3"><a href="{{ route('incentives.settings', ['edit' => $tier->id]) }}" class="font-semibold text-sky-700">Edit data</a><form method="POST" action="{{ route('incentives.settings.destroy', $tier) }}" data-no-draft onsubmit="return confirm('Hapus tingkat target ini mulai bulan berikutnya?')">@csrf @method('DELETE')<button class="font-semibold text-rose-600">Hapus</button></form></div></td></tr>@endforeach</tbody></table></div>
            </section>
        @empty
            <div class="rounded-xl border border-dashed bg-white p-8 text-center text-sm text-slate-500">Belum ada target. Tambahkan target FL, Brand, dan Rating/Follow untuk setiap cabang.</div>
        @endforelse
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('tier-form'), type = document.getElementById('tier-type'), target = document.getElementById('target-label'), rewardLabel = document.getElementById('reward-label'), targetInput = form.querySelector('[data-tier-money]'), reward = document.getElementById('reward-value');
    const thousands = input => { const digits = input.value.replace(/\D/g, ''); input.value = digits ? Number(digits).toLocaleString('id-ID') : ''; };
    const sync = () => { target.textContent = type.value === 'fl' ? 'Target keuntungan' : (type.value === 'brand' ? 'Target penjualan' : 'Target orang'); rewardLabel.textContent = type.value === 'social' ? 'Insentif per orang' : 'Insentif (%)'; reward.inputMode = type.value === 'social' ? 'numeric' : 'decimal'; if (type.value === 'social') thousands(reward); };
    targetInput.addEventListener('input', () => thousands(targetInput));
    reward.addEventListener('input', () => { if (type.value === 'social') thousands(reward); });
    type.addEventListener('change', sync);
    form.addEventListener('submit', () => { targetInput.value = targetInput.value.replace(/\./g, ''); if (type.value === 'social') reward.value = reward.value.replace(/\./g, ''); });
    sync();
});
</script>
@endsection
