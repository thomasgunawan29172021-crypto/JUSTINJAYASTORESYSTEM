@extends('layouts.app')

@section('title', 'Program Brand')

@php
    $pct = fn ($v) => $v === null || $v === ''
        ? ''
        : rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
@endphp

@section('content')
    <h1 class="text-xl font-bold mb-1">Program / Subsidi Brand</h1>
    <p class="text-sm text-slate-500 mb-5">
        Potongan dari supplier. Dipotong dari modal <b>sebelum</b> hitung harga jual —
        <b>bertingkat</b>: potong belakang dihitung dari sisa setelah potong depan.
    </p>

    <div class="mb-4 max-w-2xl rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-[13px] text-sky-900">
        Contoh: modal <b>Rp 10.000</b>, depan <b>10%</b>, belakang <b>5%</b> →
        10.000 × 0,9 = <b>9.000</b> → 9.000 × 0,95 = <b>8.550</b>.
        <span class="text-sky-700">Bukan 8.500 — potong belakang ngambil dari 9.000, bukan dari 10.000.</span>
    </div>

    <form method="GET" class="flex flex-wrap gap-2 mb-4 max-w-lg">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama brand…"
               class="flex-1 min-w-48 rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
        <button class="rounded-lg bg-slate-900 text-white px-4 py-2 text-sm font-semibold">Cari</button>
        @if(request('q'))
            <a href="{{ route('pricing.brand-programs.index') }}" class="self-center text-xs font-semibold text-rose-500 hover:underline">✕ reset</a>
        @endif
    </form>

    <form method="POST" action="{{ route('pricing.brand-programs.update') }}">
        @csrf @method('PUT')

        <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-slate-500 uppercase tracking-wide bg-slate-50">
                    <tr>
                        <th class="px-4 py-3">Brand</th>
                        <th class="px-3 py-3 w-40">Potong Depan (%)</th>
                        <th class="px-3 py-3 w-40">Potong Belakang (%)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($brands as $b)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-2.5 font-semibold">{{ $b->name }}</td>
                            <td class="px-3 py-2.5">
                                <input type="text" inputmode="decimal" name="programs[{{ $b->id }}][front]"
                                       value="{{ $pct($b->program_front_percent) }}" placeholder="—"
                                       class="percent-input w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                            </td>
                            <td class="px-3 py-2.5">
                                <input type="text" inputmode="decimal" name="programs[{{ $b->id }}][back]"
                                       value="{{ $pct($b->program_back_percent) }}" placeholder="—"
                                       class="percent-input w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-8 text-center text-slate-400">
                            {{ request('q') ? 'Tidak ada brand cocok.' : 'Belum ada brand.' }}
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($brands->isNotEmpty())
            <div class="flex items-center gap-3 mt-4">
                <button class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white px-5 py-2 text-sm font-bold">Simpan Semua</button>
                <p class="text-[11px] text-slate-400">
                    Kosong = brand ini gak punya program. Tambahan khusus per produk diatur di form Produk.
                </p>
            </div>
        @endif
    </form>
@endsection
