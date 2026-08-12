@extends('layouts.app')

@section('title', 'Vendor Retur')

@section('content')
    <h1 class="text-xl font-bold">Vendor Retur <span class="text-sm text-slate-400 font-normal">(distributor / supplier / service center)</span></h1>
    <p class="text-sm text-slate-500 mt-1 mb-5">
        Setelan di sini yang menentukan <b>alur retur tiap brand</b>. Brand yang belum dipasang ke vendor mana pun
        otomatis dianggap <b>wajib kirim dahulu</b> — alur paling aman.
    </p>

    <form method="POST" action="{{ route('warranty.vendors.store') }}"
          class="bg-white rounded-xl border border-slate-200 p-5 mb-5">
        @csrf
        <div class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-40">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Nama vendor</label>
                <input type="text" name="name" required placeholder="Robot Distributor Palembang"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="flex-1 min-w-32">
                <label class="block text-xs font-semibold text-slate-600 mb-1">No. HP / kontak</label>
                <input type="text" name="phone" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <button class="rounded-lg bg-emerald-500 text-white text-sm font-semibold px-4 py-2">+ Tambah</button>
        </div>

        <div class="mt-3 pt-3 border-t border-slate-100">
            <p class="text-xs font-semibold text-slate-600 mb-2">Sistem retur vendor ini</p>
            <div class="grid sm:grid-cols-2 gap-2">
                <label class="rounded-lg border border-slate-300 px-3 py-2 text-sm cursor-pointer has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                    <input type="radio" name="requires_send_first" value="1" checked class="accent-emerald-500">
                    <b>Wajib kirim dahulu</b>
                    <span class="block text-[11px] text-slate-500 ml-5">Barang dikirim ke supplier dulu, pelanggan menunggu hasilnya. Contoh: Ugreen, Anker.</span>
                </label>
                <label class="rounded-lg border border-slate-300 px-3 py-2 text-sm cursor-pointer has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                    <input type="radio" name="requires_send_first" value="0" class="accent-emerald-500">
                    <b>Tidak wajib kirim dahulu</b>
                    <span class="block text-[11px] text-slate-500 ml-5">Barang pelanggan langsung kita ganti, klaim ke suppliernya jalan belakangan. Contoh: Robot, Olike.</span>
                </label>
            </div>
        </div>
    </form>

    <div class="space-y-3">
        @forelse($vendors as $v)
            @php $ownBrands = $brands->where('warranty_vendor_id', $v->id); @endphp
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <form method="POST" action="{{ route('warranty.vendors.update', $v) }}" class="space-y-3">
                    @csrf @method('PUT')

                    <div class="flex flex-wrap items-center gap-2">
                        <input type="text" name="name" value="{{ $v->name }}" required
                               class="flex-1 min-w-40 rounded-lg border border-slate-200 px-2 py-1.5 text-sm font-semibold">
                        <input type="text" name="phone" value="{{ $v->phone }}" placeholder="kontak"
                               class="w-36 rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
                        <span class="text-[11px] text-slate-400">{{ $v->claims_count }} klaim</span>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-2">
                        <label class="rounded-lg border border-slate-200 px-3 py-2 text-sm cursor-pointer has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                            <input type="radio" name="requires_send_first" value="1" @checked($v->requires_send_first) class="accent-emerald-500">
                            Wajib kirim dahulu
                        </label>
                        <label class="rounded-lg border border-slate-200 px-3 py-2 text-sm cursor-pointer has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                            <input type="radio" name="requires_send_first" value="0" @checked(! $v->requires_send_first) class="accent-emerald-500">
                            Tidak wajib kirim dahulu
                        </label>
                    </div>

                    {{-- Brand yang returnya diklaim ke sini. Brand cuma boleh punya SATU
                         vendor, jadi mencentang di sini otomatis mencabutnya dari vendor lain. --}}
                    <details class="rounded-lg border border-slate-200" @if($ownBrands->isEmpty()) open @endif>
                        <summary class="px-3 py-2 text-xs font-semibold text-slate-600 cursor-pointer">
                            Brand yang diklaim ke sini
                            <span class="font-normal text-slate-400">
                                — {{ $ownBrands->isEmpty() ? 'belum ada' : $ownBrands->pluck('name')->join(', ') }}
                            </span>
                        </summary>
                        <div class="px-3 pb-3 flex flex-wrap gap-1.5">
                            @forelse($brands as $b)
                                @php $takenBy = $b->warranty_vendor_id && $b->warranty_vendor_id !== $v->id; @endphp
                                <label class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs cursor-pointer
                                              {{ $takenBy ? 'border-slate-100 text-slate-300' : 'border-slate-200' }}
                                              has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50 has-[:checked]:text-slate-700">
                                    <input type="checkbox" name="brands[]" value="{{ $b->id }}"
                                           @checked($b->warranty_vendor_id === $v->id) class="rounded accent-emerald-500">
                                    {{ $b->name }}
                                </label>
                            @empty
                                <p class="text-xs text-slate-400">Belum ada brand di master produk.</p>
                            @endforelse
                        </div>
                    </details>

                    <div class="flex justify-end gap-3">
                        <button class="rounded-lg bg-slate-900 text-white text-xs font-bold px-4 py-2">Simpan</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('warranty.vendors.destroy', $v) }}" class="text-right -mt-6"
                      onsubmit="return confirm('Hapus vendor {{ $v->name }}?')">
                    @csrf @method('DELETE')
                    <button class="text-rose-400 text-[11px] hover:underline">hapus</button>
                </form>
            </div>
        @empty
            <p class="bg-white rounded-xl border border-slate-200 px-4 py-8 text-center text-sm text-slate-400">
                Belum ada vendor — tambah dulu sebelum kirim barang.
            </p>
        @endforelse
    </div>

    @php $orphans = $brands->whereNull('warranty_vendor_id'); @endphp
    @if($orphans->isNotEmpty())
        <div class="mt-5 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3">
            <p class="text-sm font-semibold text-amber-800">{{ $orphans->count() }} brand belum punya vendor retur</p>
            <p class="text-xs text-amber-700 mt-0.5">
                {{ $orphans->pluck('name')->join(', ') }}
            </p>
            <p class="text-[11px] text-amber-600 mt-1">
                Retur brand ini dijalankan dengan alur <b>wajib kirim dahulu</b> sampai dipasangkan ke vendor.
            </p>
        </div>
    @endif
@endsection
