@extends('layouts.app')

@section('title', 'Vendor Retur')

@section('content')
    <h1 class="text-xl font-bold mb-5">Vendor Retur <span class="text-sm text-slate-400 font-normal">(distributor / supplier / service center)</span></h1>

    <form method="POST" action="{{ route('warranty.vendors.store') }}"
          class="bg-white rounded-xl border border-slate-200 p-5 flex flex-wrap gap-3 items-end mb-5">
        @csrf
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
    </form>

    <div class="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100">
        @forelse($vendors as $v)
            <form method="POST" action="{{ route('warranty.vendors.update', $v) }}"
                  class="px-4 py-3 flex flex-wrap items-center gap-2">
                @csrf @method('PUT')
                <input type="text" name="name" value="{{ $v->name }}" required
                       class="flex-1 min-w-40 rounded-lg border border-slate-200 px-2 py-1.5 text-sm font-semibold">
                <input type="text" name="phone" value="{{ $v->phone }}" placeholder="kontak"
                       class="w-36 rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
                <span class="text-[11px] text-slate-400">{{ $v->claims_count }} klaim</span>
                <button class="text-emerald-700 text-xs font-semibold hover:underline">Simpan</button>
            </form>
            <form method="POST" action="{{ route('warranty.vendors.destroy', $v) }}" class="px-4 pb-2 -mt-2 text-right"
                  onsubmit="return confirm('Hapus vendor {{ $v->name }}?')">
                @csrf @method('DELETE')
                <button class="text-rose-400 text-[11px] hover:underline">hapus</button>
            </form>
        @empty
            <p class="px-4 py-8 text-center text-sm text-slate-400">Belum ada vendor — tambah dulu sebelum kirim barang.</p>
        @endforelse
    </div>
@endsection
