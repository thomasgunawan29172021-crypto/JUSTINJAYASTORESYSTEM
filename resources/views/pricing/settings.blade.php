@extends('layouts.app')

@section('title', 'Pengaturan Harga')

@section('content')
    @php
        // 8.00 → "8", 3.50 → "3,5", null → ""
        $fmtPercent = fn ($v) => $v === null || $v === ''
            ? ''
            : rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
    @endphp
    <h1 class="text-xl font-bold mb-1">Pengaturan Harga</h1>
    <p class="text-sm text-slate-500 mb-5">Aturan yang dipakai sistem buat ngitung harga jual rekomendasi.</p>

    @if(session('err'))
        <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">{{ session('err') }}</div>
    @endif

    {{-- Pajak & Margin --}}
    <form method="POST" action="{{ route('pricing.settings.update') }}"
          class="bg-white rounded-xl border border-slate-200 p-5 mb-5">
        @csrf @method('PUT')
        <p class="text-xs font-semibold text-slate-600 mb-3">⚙️ Pajak & Target Margin</p>

        <div class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-40">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Pajak (%)</label>
                <input type="text" inputmode="decimal" name="tax_percent"
                       value="{{ $fmtPercent(old('tax_percent', $settings->tax_percent)) }}"
                       placeholder="0,5"
                       class="percent-input w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <p class="text-[11px] text-slate-400 mt-1">PPh Final — persen dari omzet.</p>
            </div>
            <div class="flex-1 min-w-40">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Target Margin (%)</label>
                <input type="text" inputmode="decimal" name="margin_percent"
                       value="{{ $fmtPercent(old('margin_percent', $settings->margin_percent)) }}"
                       placeholder="7"
                       class="percent-input w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <p class="text-[11px] text-slate-400 mt-1">Untung bersih dari <b>harga jual</b>, bukan dari modal.</p>
            </div>
            <button class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-semibold px-4 py-2">Simpan</button>
        </div>

        @if($settings->margin_percent === null)
            <p class="mt-3 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
                ⚠ Target margin belum diisi — harga jual rekomendasi belum bisa dihitung sama sekali.
            </p>
        @endif
    </form>

    {{-- Kategori --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5 mb-5">
        <p class="text-xs font-semibold text-slate-600 mb-3">🏷️ Kategori Produk</p>

        <form method="POST" action="{{ route('pricing.categories.store') }}" class="flex flex-wrap gap-2 items-end mb-4">
            @csrf
            <div class="flex-1 min-w-40">
                <input type="text" name="name" required placeholder="Powerbank, Kabel Data, Handphone..."
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <button class="rounded-lg bg-emerald-500 text-white text-sm font-semibold px-4 py-2">+ Tambah</button>
        </form>

        @forelse($categories as $c)
            <span class="inline-flex items-center gap-1.5 mr-2 mb-2 rounded-lg bg-slate-100 pl-3 pr-1.5 py-1.5 text-sm">
                {{ $c->name }}
                <form method="POST" action="{{ route('pricing.categories.destroy', $c) }}" class="inline"
                      onsubmit="return confirm('Hapus kategori {{ $c->name }}? Biaya admin & ongkir kategori ini ikut kehapus.')">
                    @csrf @method('DELETE')
                    <button class="text-slate-400 hover:text-rose-500 text-xs px-1">✕</button>
                </form>
            </span>
        @empty
            <p class="text-sm text-slate-400">Belum ada kategori. Tanpa kategori, biaya admin & ongkir gak bisa diatur.</p>
        @endforelse
    </div>

    {{-- Grid biaya --}}
    <form method="POST" action="{{ route('pricing.fees.update') }}">
        @csrf @method('PUT')

        @if($categories->isEmpty())
            <div class="bg-white rounded-xl border border-slate-200 p-8 text-center text-sm text-slate-400">
                Tambah kategori dulu di atas — biaya diatur per kategori.
            </div>
        @elseif($combos->isEmpty())
            <div class="bg-white rounded-xl border border-slate-200 p-8 text-center text-sm text-slate-400">
                Belum ada toko aktif. Biaya diatur per kombinasi marketplace + tier toko yang kamu punya.
            </div>
        @else
            @foreach($combos as $combo)
                <div class="bg-white rounded-xl border border-slate-200 p-5 mb-4">
                    <p class="text-xs font-semibold text-slate-600 mb-1">
                        💰 {{ ucfirst($combo['marketplace']) }}
                        <span class="ml-1 px-1.5 py-0.5 rounded bg-violet-100 text-violet-700 text-[10px]">{{ ucfirst($combo['tier'] ?? '?') }}</span>
                    </p>
                    <p class="text-[11px] text-slate-400 mb-3">Toko: {{ implode(', ', $combo['store_names']) }}</p>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm min-w-[680px]">
                            <thead class="text-left text-xs text-slate-500 uppercase tracking-wide bg-slate-50">
                                <tr>
                                    <th class="px-3 py-2">Kategori</th>
                                    @foreach($feeFields as $label)
                                        <th class="px-3 py-2 w-32">{{ ucfirst($label) }} (%)</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($categories as $cat)
                                    @php
                                        $key = $combo['marketplace'].'|'.$combo['tier'].'|'.$cat->id;
                                        $fee = $fees->get($key);
                                    @endphp
                                    <tr>
                                        <td class="px-3 py-2 font-medium whitespace-nowrap">{{ $cat->name }}</td>
                                        @foreach($feeFields as $field => $label)
                                            <td class="px-3 py-2">
                                                <input type="text" inputmode="decimal"
                                                       name="fees[{{ $key }}][{{ $field }}]"
                                                       value="{{ $fmtPercent($fee?->$field) }}" placeholder="belum diisi"
                                                       class="percent-input w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach

            <div class="flex items-center gap-3">
                <button class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white px-5 py-2 text-sm font-bold">Simpan Semua Biaya</button>
                <p class="text-[11px] text-slate-400">
                    Kosong = <b>belum diisi</b>, beda dengan <b>0</b> (memang gratis). Yang kosong bikin harga rekomendasi gak keluar, bukan dianggap nol.
                </p>
            </div>
        @endif
    </form>
@endsection