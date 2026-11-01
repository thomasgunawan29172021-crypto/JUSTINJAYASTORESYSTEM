@extends('layouts.app')
@section('title', 'Pengaturan Insentif')
@section('content')
@php $money = fn($n,$c) => ($c==='IDR'?'Rp ':'NT$ ').number_format((float)$n,0,',','.'); @endphp
<div class="mx-auto max-w-6xl space-y-5">
    <div><a href="{{ route('incentives.index') }}" class="text-sm text-emerald-700">← Kembali</a><h1 class="mt-1 text-2xl font-bold">Pengaturan target insentif</h1><p class="text-sm text-slate-500">Target dibuat per cabang dan mata uang. Jika capaian melewati target, sistem memakai tingkat tertinggi yang sudah tercapai.</p></div>
    @if(session('ok'))<div class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-700">{{ session('ok') }}</div>@endif
    @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 text-sm text-rose-700">@foreach($errors->all() as $error)<p>• {{ $error }}</p>@endforeach</div>@endif
    <form method="POST" action="{{ route('incentives.settings.store') }}" class="grid gap-3 rounded-xl border bg-white p-5 md:grid-cols-2 xl:grid-cols-6">@csrf
        <label>Cabang<select name="branch_id" required class="mt-1 block w-full rounded-lg border p-2"><option value="">Pilih cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id')==$branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
        <label>Mata uang<select name="currency" required class="mt-1 block w-full rounded-lg border p-2">@foreach(\App\Models\IncentiveSubmission::CURRENCIES as $code=>$label)<option value="{{ $code }}" @selected(old('currency','IDR')===$code)>{{ $label }}</option>@endforeach</select></label>
        <label>Tipe<select name="type" id="tier-type" required class="mt-1 block w-full rounded-lg border p-2">@foreach(\App\Models\IncentiveTier::TYPES as $key=>$label)<option value="{{ $key }}" @selected(old('type')===$key)>{{ $label }}</option>@endforeach</select></label>
        <label>Nama peringkat <span class="text-xs text-slate-400">(opsional)</span><input name="label" maxlength="80" value="{{ old('label') }}" placeholder="Bronze / Bintang 1" class="mt-1 block w-full rounded-lg border p-2"></label>
        <label><span id="target-label">Target keuntungan</span><input name="min_target" type="number" min="0" step="0.01" required value="{{ old('min_target') }}" class="mt-1 block w-full rounded-lg border p-2"></label>
        <label><span id="reward-label">Insentif (%)</span><div class="mt-1 flex gap-2"><input name="reward_value" type="number" min="0" step="0.0001" required value="{{ old('reward_value') }}" class="block min-w-0 flex-1 rounded-lg border p-2"><button class="rounded-lg bg-emerald-700 px-4 font-semibold text-white">Simpan</button></div></label>
    </form>
    <div class="space-y-4">
        @forelse($tiers->groupBy(fn($t) => $t->branch->name.'|'.$t->currency.'|'.$t->type) as $key=>$group)
        @php [$branchName,$currency,$type] = explode('|',$key); @endphp
        <section class="rounded-xl border bg-white overflow-hidden"><div class="flex justify-between bg-slate-50 px-5 py-3"><h2 class="font-bold">{{ $branchName }} · {{ $currency }}</h2><span class="text-sm font-semibold text-slate-600">{{ \App\Models\IncentiveTier::TYPES[$type] }}</span></div><table class="w-full text-sm"><thead class="text-left text-xs uppercase text-slate-500"><tr><th class="px-5 py-2">Peringkat</th><th class="px-3 py-2">Mulai target</th><th class="px-3 py-2">Insentif</th><th class="px-5 py-2"></th></tr></thead><tbody class="divide-y">@foreach($group as $tier)<tr><td class="px-5 py-3 font-medium">{{ $tier->label ?: '—' }}</td><td class="px-3 py-3">{{ $type==='social' ? number_format((float)$tier->min_target,0).' orang' : $money($tier->min_target,$currency) }}</td><td class="px-3 py-3 font-semibold">{{ $type==='social' ? $money($tier->reward_value,$currency).' / orang' : rtrim(rtrim(number_format((float)$tier->reward_value,4,',','.'),'0'),',').'%' }}</td><td class="px-5 py-3 text-right"><form method="POST" action="{{ route('incentives.settings.destroy',$tier) }}" onsubmit="return confirm('Hapus tingkat target ini?')">@csrf @method('DELETE')<button class="text-rose-600">Hapus</button></form></td></tr>@endforeach</tbody></table></section>
        @empty<div class="rounded-xl border border-dashed bg-white p-8 text-center text-sm text-slate-500">Belum ada target. Tambahkan target FL, Brand, dan Rating/Follow untuk setiap cabang.</div>@endforelse
    </div>
</div>
<script>document.addEventListener('DOMContentLoaded',()=>{const type=document.getElementById('tier-type'), target=document.getElementById('target-label'), reward=document.getElementById('reward-label'); const sync=()=>{target.textContent=type.value==='fl'?'Target keuntungan':(type.value==='brand'?'Target penjualan':'Target orang');reward.textContent=type.value==='social'?'Insentif per orang':'Insentif (%)'};type.onchange=sync;sync();});</script>
@endsection
