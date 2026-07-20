@extends('layouts.public')

@section('title', 'Lacak Servis')

@section('content')
    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
        <h1 class="text-lg font-bold">Lacak Status Servis</h1>
        <p class="text-sm text-slate-500 mt-1 mb-5">
            Masukkan nomor servis yang tertera di nota dan nomor HP yang terdaftar.
        </p>

        @if($errors->any())
            <div class="mb-4 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 px-3 py-2 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('track.lookup') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Nomor servis</label>
                <input type="text" name="ticket_number" value="{{ old('ticket_number') }}" required
                       placeholder="SV-ILR-2607-0001"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono uppercase focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">No. HP (yang terdaftar di nota)</label>
                <input type="text" name="phone" value="{{ old('phone') }}" required
                       placeholder="08xxxxxxxxxx"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <button class="w-full rounded-lg bg-emerald-500 text-white py-2.5 text-sm font-semibold hover:bg-emerald-600">
                🔍 Lacak
            </button>
        </form>
    </div>

    <p class="text-center text-[11px] text-slate-400 mt-4">
        Klaim retur / garansi barang? <a href="{{ route('warranty.track.form') }}" class="text-emerald-600 hover:underline">Lacak retur di sini</a>
    </p>
@endsection