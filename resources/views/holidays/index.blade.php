@extends('layouts.app')

@section('title', 'Libur Nasional')

@section('content')
    <h1 class="text-xl font-bold mb-5">Libur Nasional</h1>

    <form method="POST" action="{{ route('holidays.store') }}" class="bg-white rounded-xl border border-slate-200 p-5 flex gap-3 items-end mb-5 max-w-lg">
        @csrf
        <div class="flex-1">
            <label class="block text-xs font-semibold text-slate-600 mb-1">Tanggal</label>
            <input type="date" name="date" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div class="flex-1">
            <label class="block text-xs font-semibold text-slate-600 mb-1">Nama Libur</label>
            <input type="text" name="name" required placeholder="Idul Fitri" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <button class="rounded-lg bg-emerald-500 text-white text-sm font-semibold px-4 py-2">+ Tambah</button>
    </form>

    <div class="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100 max-w-lg">
        @forelse($holidays as $h)
            <div class="px-4 py-2.5 flex items-center justify-between text-sm">
                <span>{{ $h->date->translatedFormat('d M Y') }} — {{ $h->name }}</span>
                <form method="POST" action="{{ route('holidays.destroy', $h) }}" onsubmit="return confirm('Hapus?')">
                    @csrf @method('DELETE')
                    <button class="text-rose-500 text-xs hover:underline">Hapus</button>
                </form>
            </div>
        @empty
            <p class="px-4 py-3 text-sm text-slate-400">Belum ada libur nasional diset.</p>
        @endforelse
    </div>
@endsection