@extends('layouts.app')
@section('title', 'Dashboard Insentif')
@section('content')
@php
    $money = fn ($n, $currency) => ($currency === 'IDR' ? 'Rp ' : 'NT$ ').number_format((float) $n, 0, ',', '.');
    $typeLabels = \App\Models\IncentiveTier::TYPES;
    $typeIcons = ['fl' => '💼', 'brand' => '📱', 'social' => '⭐'];
    $periodLabel = \Carbon\Carbon::parse($period.'-01')->translatedFormat('F Y');
    $selectedBranchName = isset($filters['branch_id']) ? $branches->firstWhere('id', (int) $filters['branch_id'])?->name : null;
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Dashboard Insentif</h1>
            <p class="text-sm text-slate-500">Pencapaian dari transaksi yang sudah disetujui CEO dan lunas.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if(auth()->user()->isCeo())
                <a href="{{ route('incentives.settings') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">Atur target</a>
            @endif
            <a href="{{ route('incentives.create') }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-emerald-500">+ Ajukan invoice</a>
        </div>
    </div>

    @if(session('ok'))<div class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-700">{{ session('ok') }}</div>@endif
    @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 text-sm text-rose-700">{{ $errors->first() }}</div>@endif

    <form method="GET" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-6">
        <label class="text-sm font-medium">Periode<input type="month" name="period" value="{{ $period }}" class="mt-1 block w-full rounded-lg border p-2"></label>
        <label class="text-sm font-medium">Cabang<select name="branch_id" class="mt-1 block w-full rounded-lg border p-2"><option value="">Semua cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? '') == $branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
        <label class="text-sm font-medium">Approval<select name="status" class="mt-1 block w-full rounded-lg border p-2"><option value="">Semua</option><option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Menunggu</option><option value="approved" @selected(($filters['status'] ?? '') === 'approved')>Disetujui</option><option value="rejected" @selected(($filters['status'] ?? '') === 'rejected')>Ditolak</option></select></label>
        <label class="text-sm font-medium">Pembayaran<select name="payment" class="mt-1 block w-full rounded-lg border p-2"><option value="">Semua</option><option value="paid" @selected(($filters['payment'] ?? '') === 'paid')>Lunas</option><option value="unpaid" @selected(($filters['payment'] ?? '') === 'unpaid')>Belum lunas</option></select></label>
        <label class="text-sm font-medium">Pencarian<input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Invoice / barang" class="mt-1 block w-full rounded-lg border p-2"></label>
        <div class="flex items-end"><button class="w-full rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white">Tampilkan</button></div>
    </form>

    <section class="overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-indigo-950 to-emerald-900 text-white shadow-xl">
        <div class="relative p-5 sm:p-7">
            <div class="absolute -right-16 -top-20 h-56 w-56 rounded-full bg-emerald-400/20 blur-3xl"></div>
            <div class="relative flex items-start gap-4">
                <div class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-white/10 text-2xl ring-1 ring-white/20">🚀</div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-300">Semangat {{ $periodLabel }}</p>
                    <h2 class="mt-1 text-xl font-black sm:text-2xl">Terima kasih untuk kerja hebatmu!</h2>
                    <p class="mt-2 max-w-4xl text-sm leading-6 text-slate-200">{{ $motivation['message'] }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="space-y-3">
        <div>
            <h2 class="text-lg font-black text-slate-900">Pencapaian Saya</h2>
            <p class="text-xs text-slate-500">Insentif digabung, sementara target berikutnya tetap mengikuti skema yang berlaku.</p>
        </div>

        @if($motivation['personal']->isEmpty())
            <div class="rounded-2xl border border-dashed border-emerald-300 bg-emerald-50/50 p-6 text-center">
                <p class="text-3xl">🌱</p>
                <p class="mt-2 font-bold text-slate-800">Perjalanan bulan ini siap dimulai</p>
                <p class="mt-1 text-sm text-slate-500">Belum ada transaksi approved dan lunas. Ayo raih pencapaian pertamamu!</p>
            </div>
        @else
            <div class="grid gap-4 xl:grid-cols-2">
                @foreach($motivation['personal'] as $personal)
                    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="bg-gradient-to-r from-emerald-500 to-teal-600 p-5 text-white">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-100">Insentif sementara · {{ $personal['market'] }}</p>
                                    <p class="mt-1 text-3xl font-black">{{ $money($personal['incentive'], $personal['currency']) }}</p>
                                </div>
                                <span class="rounded-full bg-white/20 px-3 py-1 text-xs font-bold">{{ $personal['currency'] }}</span>
                            </div>
                        </div>
                        <div class="space-y-5 p-5">
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                <div class="rounded-xl bg-slate-50 p-3"><p class="text-[11px] uppercase text-slate-400">Transaksi bulan ini</p><p class="mt-1 text-lg font-black">{{ $personal['transactions'] }}</p></div>
                                <div class="rounded-xl bg-slate-50 p-3"><p class="text-[11px] uppercase text-slate-400">Total {{ $motivation['year'] }}</p><p class="mt-1 text-lg font-black text-emerald-700">{{ $money($personal['year_incentive'], $personal['currency']) }}</p></div>
                                <div class="col-span-2 rounded-xl p-3 sm:col-span-1 {{ $personal['comparison_percent'] === null ? 'bg-slate-50' : ($personal['comparison_percent'] >= 0 ? 'bg-emerald-50' : 'bg-rose-50') }}">
                                    <p class="text-[11px] uppercase text-slate-400">Dibanding bulan lalu</p>
                                    @if($personal['comparison_percent'] === null)
                                        <p class="mt-1 text-sm font-bold text-slate-600">Belum ada pembanding</p>
                                    @else
                                        <p class="mt-1 text-lg font-black {{ $personal['comparison_percent'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $personal['comparison_percent'] >= 0 ? '↑' : '↓' }} {{ number_format(abs($personal['comparison_percent']), 1, ',', '.') }}%</p>
                                    @endif
                                </div>
                            </div>

                            <div class="space-y-3">
                                <h3 class="text-sm font-black text-slate-800">Misi target berikutnya</h3>
                                @forelse($personal['goals'] as $goal)
                                    <div class="rounded-2xl border border-slate-200 p-4">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="font-bold text-slate-800">{{ $typeIcons[$goal['type']] }} {{ $typeLabels[$goal['type']] }}{{ $goal['next_tier_label'] ? ' · '.$goal['next_tier_label'] : '' }}</p>
                                                <p class="text-xs text-slate-400">{{ $goal['branch']->name }}</p>
                                            </div>
                                            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold">{{ number_format($goal['progress_percent'], 0) }}%</span>
                                        </div>

                                        @if($goal['is_highest_tier'])
                                            <div class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800">🏆 Selamat, target tertinggi tercapai. Terima kasih—pertahankan performa luar biasa ini!</div>
                                        @else
                                            <div class="mt-3 h-3 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-teal-500 transition-all" style="width: {{ min(100, $goal['progress_percent']) }}%"></div></div>
                                            <div class="mt-2 flex flex-wrap justify-between gap-2 text-xs">
                                                <span class="text-slate-500">Sekarang: <strong class="text-slate-700">{{ $goal['type'] === 'social' ? number_format($goal['metric'], 0, ',', '.').' orang' : $money($goal['metric'], $goal['currency']) }}</strong></span>
                                                <span class="text-slate-500">Target: <strong class="text-slate-700">{{ $goal['type'] === 'social' ? number_format($goal['next_target'], 0, ',', '.').' orang' : $money($goal['next_target'], $goal['currency']) }}</strong></span>
                                            </div>
                                            <p class="mt-3 text-sm font-semibold text-emerald-700">Tinggal {{ $goal['type'] === 'social' ? number_format($goal['remaining_to_next'], 0, ',', '.').' orang' : $money($goal['remaining_to_next'], $goal['currency']) }} lagi. Capai target dan raih potensi insentif {{ $money($goal['next_incentive'], $goal['currency']) }}! 🔥</p>
                                        @endif
                                    </div>
                                @empty
                                    <p class="rounded-xl bg-slate-50 p-3 text-sm text-slate-500">Target cabang belum diatur. Pencapaianmu tetap tercatat dan akan dihitung saat skema tersedia.</p>
                                @endforelse
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="space-y-4">
        <div>
            <h2 class="text-lg font-black text-slate-900">Leaderboard Tim</h2>
            <p class="text-xs text-slate-500">Ranking berdasarkan nominal insentif; jika sama, jumlah transaksi menjadi penentu.</p>
        </div>

        @foreach($motivation['leaderboards'] as $market)
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between bg-slate-900 px-5 py-4 text-white">
                    <div><p class="text-lg font-black">{{ $market['flag'] }} {{ $market['label'] }}</p><p class="text-xs text-slate-300">{{ $selectedBranchName ?: 'Seluruh cabang' }} · {{ $market['currency'] }}</p></div>
                    <span class="text-2xl">🏆</span>
                </div>
                <div class="grid divide-y lg:grid-cols-2 lg:divide-x lg:divide-y-0">
                    @foreach(['monthly' => 'Bulan '.$periodLabel, 'yearly' => 'Tahun '.$motivation['year']] as $boardKey => $boardLabel)
                        <div class="p-4 sm:p-5">
                            <h3 class="mb-3 text-sm font-black uppercase tracking-wide text-slate-500">{{ $boardLabel }}</h3>
                            <div class="space-y-2">
                                @forelse($market[$boardKey] as $ranked)
                                    <div class="flex items-center gap-3 rounded-xl border p-3 {{ $ranked['user_id'] === auth()->id() ? 'border-emerald-300 bg-emerald-50' : 'border-slate-100 bg-slate-50' }}">
                                        <div class="grid h-9 w-9 shrink-0 place-items-center rounded-full font-black {{ $ranked['rank'] === 1 ? 'bg-amber-400 text-amber-950' : ($ranked['rank'] === 2 ? 'bg-slate-300 text-slate-700' : ($ranked['rank'] === 3 ? 'bg-orange-300 text-orange-900' : 'bg-white text-slate-500')) }}">{{ $ranked['rank'] }}</div>
                                        <div class="min-w-0 flex-1"><p class="truncate font-bold text-slate-800">{{ $ranked['user']->name }} @if($ranked['user_id'] === auth()->id())<span class="text-xs text-emerald-600">(Saya)</span>@endif</p><p class="text-xs text-slate-400">{{ $ranked['transactions'] }} transaksi</p></div>
                                        <p class="text-right text-sm font-black text-emerald-700">{{ $money($ranked['incentive'], $ranked['currency']) }}</p>
                                    </div>
                                @empty
                                    <div class="rounded-xl border border-dashed p-5 text-center text-sm text-slate-400">Belum ada pencapaian eligible.</div>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </article>
        @endforeach
    </section>

    <section class="space-y-3">
        <div><h2 class="font-black text-slate-900">Rincian Perhitungan {{ $periodLabel }}</h2><p class="text-xs text-slate-500">Target dan nominal tetap dihitung sesuai cabang, mata uang, dan skema promotor.</p></div>
        @if($summary->isEmpty())
            <div class="rounded-xl border border-dashed bg-white p-6 text-center text-sm text-slate-500">Belum ada transaksi eligible pada periode ini.</div>
        @else
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach($summary as $row)
                    <article class="rounded-xl border bg-white p-4 shadow-sm">
                        <div class="flex justify-between gap-2"><div><p class="font-bold">{{ $row['user']->name }}</p><p class="text-xs text-slate-500">{{ $row['branch']->name }} · {{ $typeLabels[$row['type']] }}{{ $row['tier_label'] ? ' · '.$row['tier_label'] : '' }}</p></div><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold">{{ $row['currency'] }}</span></div>
                        <div class="mt-4 grid grid-cols-2 gap-2 text-sm"><div><p class="text-xs text-slate-400">Capaian</p><p class="font-semibold">{{ $row['type'] === 'social' ? number_format($row['metric']).' orang' : $money($row['metric'], $row['currency']) }}</p></div><div><p class="text-xs text-slate-400">Tarif aktif</p><p class="font-semibold">{{ $row['type'] === 'social' ? $money($row['reward_value'], $row['currency']).'/orang' : rtrim(rtrim(number_format($row['reward_value'], 4, ',', '.'), '0'), ',').'%' }}</p></div></div>
                        <div class="mt-3 border-t pt-3"><p class="text-xs text-slate-400">Insentif sementara</p><p class="text-xl font-black text-emerald-700">{{ $money($row['incentive'], $row['currency']) }}</p></div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="overflow-x-auto rounded-xl border bg-white">
        <table class="w-full min-w-[900px] text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Invoice</th><th class="px-3 py-3">Tanggal / Cabang</th><th class="px-3 py-3">Promotor</th><th class="px-3 py-3">Penjualan</th><th class="px-3 py-3">Untung</th><th class="px-3 py-3">Approval</th><th class="px-3 py-3">Pembayaran</th><th class="px-3 py-3"></th></tr></thead>
            <tbody class="divide-y">
                @forelse($submissions as $item)
                    <tr class="hover:bg-slate-50"><td class="px-4 py-3"><p class="font-semibold">{{ $item->invoice_number }}</p><p class="text-xs text-slate-400">oleh {{ $item->submitter->name }}{{ $item->is_cooperation ? ' · kerja sama' : '' }}</p></td><td class="px-3 py-3">{{ $item->transaction_date->format('d/m/Y') }}<p class="text-xs text-slate-400">{{ $item->branch->name }}</p></td><td class="px-3 py-3">@foreach($item->participants as $participant)<p>{{ $participant->user->name }} <span class="text-xs text-slate-400">({{ strtoupper($participant->incentive_type) }} {{ number_format((float) $participant->share_percent, 0) }}%)</span></p>@endforeach</td><td class="px-3 py-3 font-medium">{{ $money($item->total_sales, $item->currency) }}</td><td class="px-3 py-3 {{ (float) $item->total_profit < 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ $money($item->total_profit, $item->currency) }}</td><td class="px-3 py-3"><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $item->approval_status === 'approved' ? 'bg-emerald-100 text-emerald-700' : ($item->approval_status === 'rejected' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700') }}">{{ ['approved' => 'Disetujui', 'rejected' => 'Ditolak', 'pending' => 'Menunggu'][$item->approval_status] }}</span></td><td class="px-3 py-3"><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $item->payment_status === 'paid' ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-600' }}">{{ $item->payment_status === 'paid' ? 'Lunas' : 'Belum lunas' }}</span></td><td class="px-3 py-3 text-right"><a href="{{ route('incentives.show', $item) }}" class="font-semibold text-emerald-700">Detail →</a></td></tr>
                @empty
                    <tr><td colspan="8" class="p-8 text-center text-slate-500">Belum ada pengajuan pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    {{ $submissions->links() }}
</div>
@endsection
