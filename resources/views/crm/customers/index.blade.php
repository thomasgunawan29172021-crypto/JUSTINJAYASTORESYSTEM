@extends('layouts.app')

@section('title', 'Pelanggan CRM')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-2 mb-5">
        <h1 class="text-xl font-bold">Pelanggan</h1>
        <a href="{{ route('crm.customers.create') }}"
           class="rounded-xl bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-bold px-4 py-2">+ Pelanggan Baru</a>
    </div>

    <form method="GET" class="mb-5 flex flex-wrap items-center gap-2">
        <input type="text" name="q" value="{{ request('q') }}"
               placeholder="Cari nama / nomor HP / WA…"
               class="flex-1 min-w-52 max-w-md rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
        <button class="rounded-xl bg-slate-900 text-white text-sm font-semibold px-4 py-2.5">Cari</button>
        @if(request('q'))
            <a href="{{ route('crm.customers.index') }}" class="text-xs font-semibold text-rose-500 hover:underline">✕ reset</a>
        @endif
    </form>

    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-500 uppercase tracking-wide bg-slate-50">
                <tr>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Kontak Utama</th>
                    <th class="px-4 py-3">Sumber</th>
                    <th class="px-4 py-3">Cabang</th>
                    <th class="px-4 py-3">Terdaftar</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($customers as $c)
                    @php $contact = $c->primaryContact(); @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-semibold">{{ $c->name }}</td>
                        <td class="px-4 py-3">
                            @if($contact)
                                <span class="block text-xs text-slate-400 uppercase">{{ $contact->type }}</span>
                                {{ $contact->value }}
                            @else
                                <span class="text-slate-300 text-xs">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ $c->source ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ $c->branch->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-400 whitespace-nowrap">
                            {{ $c->created_at->format('d M Y') }}
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('crm.customers.show', $c) }}"
                               class="text-xs font-semibold text-sky-600 hover:underline">Detail →</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400 text-sm">
                            Belum ada pelanggan ditemukan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($customers->hasPages())
        <div class="mt-4">{{ $customers->withQueryString()->links() }}</div>
    @endif
@endsection