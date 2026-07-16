@extends('layouts.app')

@section('title', 'Diskon')

@section('content')
    <h1 class="text-xl font-bold mb-1">Pengingat Diskon</h1>
    <p class="text-sm text-slate-500 mb-5">
        Catatan masa berlaku promo per toko — <b>tidak mengubah harga</b> & tidak membuat tugas.
        Alert muncul di dashboard 30 hari sebelum berakhir.
    </p>

    <form method="POST" action="{{ route('marketplace.discounts.store') }}"
          class="bg-white rounded-xl border border-slate-200 p-5 space-y-4 mb-5">
        @csrf
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="lg:col-span-2">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Nama promo *</label>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="150" placeholder="Promo 7.7"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Tipe promosi *</label>
                <select name="type" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
                    @foreach(\App\Models\ProductDiscount::TYPES as $val => $label)
                        <option value="{{ $val }}" @selected(old('type') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Catatan</label>
                <input type="text" name="note" value="{{ old('note') }}" maxlength="300" placeholder="opsional"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="lg:col-span-2">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Mulai * <span class="font-normal text-slate-400">(tanggal & jam)</span></label>
                <input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="lg:col-span-2">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Berakhir * <span class="font-normal text-slate-400">(tanggal & jam)</span></label>
                <input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>

        <div>
            <p class="text-xs font-semibold text-slate-600 mb-1">Toko marketplace * <span class="font-normal text-slate-400">(boleh lebih dari satu)</span></p>
            @if($stores->isEmpty())
                <p class="text-xs text-amber-600">Belum ada toko aktif — tambahkan toko dulu.</p>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach($stores as $s)
                        <label class="flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-sm cursor-pointer hover:bg-slate-50">
                            <input type="checkbox" name="stores[]" value="{{ $s->id }}" class="rounded accent-emerald-500"
                                   @checked(in_array($s->id, old('stores', [])))>
                            {{ $s->label() }}
                        </label>
                    @endforeach
                </div>
            @endif
        </div>

        <button class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-semibold px-4 py-2">
            + Catat Diskon
        </button>
    </form>

    {{-- Filter toko (#4c) --}}
    <form method="GET" class="flex flex-wrap items-end gap-2 mb-4">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Filter toko</label>
            <select name="store_id" onchange="this.form.submit()"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
                <option value="">Semua toko</option>
                @foreach($stores as $s)
                    <option value="{{ $s->id }}" @selected($storeId == $s->id)>{{ $s->label() }}</option>
                @endforeach
            </select>
        </div>
        @if($storeId)
            <a href="{{ route('marketplace.discounts.index') }}" class="text-xs font-semibold text-rose-500 hover:underline pb-2.5">✕ reset</a>
        @endif
    </form>

    <div class="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100">
        @forelse($discounts as $d)
            <div class="px-4 py-3 flex flex-wrap items-center justify-between gap-2 text-sm
                        {{ $d->hasEnded() ? 'bg-rose-50' : ($d->endsSoon() ? 'bg-amber-50' : '') }}">
                <div class="min-w-0">
                    <p class="font-semibold">
                        {{ $d->name }}
                        <span class="ml-1 px-1.5 py-0.5 rounded bg-violet-100 text-violet-700 text-[10px] font-medium">{{ $d->typeLabel() }}</span>
                    </p>
                    <p class="text-xs text-slate-500">
                        🏬 {{ $d->stores->pluck('name')->join(', ') ?: '— tanpa toko' }}
                    </p>
                    <p class="text-xs text-slate-500">
                        {{ $d->starts_at->translatedFormat('d M Y H:i') }} – {{ $d->ends_at->translatedFormat('d M Y H:i') }}
                        @if($d->hasEnded())
                            <span class="ml-1 px-1.5 py-0.5 rounded bg-rose-100 text-rose-700 text-[10px] font-semibold">SUDAH BERAKHIR — cabut di marketplace!</span>
                        @elseif($d->endsSoon())
                            <span class="ml-1 px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 text-[10px] font-semibold">berakhir {{ $d->ends_at->diffForHumans() }}</span>
                        @endif
                        @if($d->note) · {{ $d->note }} @endif
                    </p>
                </div>
                <form method="POST" action="{{ route('marketplace.discounts.destroy', $d) }}"
                      onsubmit="return confirm('Hapus pengingat ini? (Pastikan diskonnya sudah dicabut di marketplace)')">
                    @csrf @method('DELETE')
                    <button class="text-rose-500 text-xs font-semibold hover:underline">Selesai / Hapus</button>
                </form>
            </div>
        @empty
            <p class="px-4 py-8 text-center text-sm text-slate-400">
                {{ $storeId ? 'Tidak ada diskon untuk toko ini.' : 'Belum ada pengingat diskon.' }}
            </p>
        @endforelse
    </div>
@endsection
