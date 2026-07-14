@extends('layouts.app')

@section('title', 'User Management')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-bold">User Management</h1>
        <a href="{{ route('users.create') }}"
           class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-semibold px-4 py-2">
            + Akun Baru
        </a>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-500 uppercase tracking-wide bg-slate-50">
                <tr>
                    <th class="px-4 py-3">Id</th>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Kontak</th>
                    <th class="px-4 py-3">Role</th>
                    <th class="px-4 py-3">Cabang</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($users as $u)
                <tr class="hover:bg-slate-50 {{ ! $u->is_active ? 'opacity-50' : '' }}">
                        <td class="px-4 py-3 text-slate-600">{{ $u->id }}</td>
                        <td class="px-4 py-3 font-semibold">
                            {{ $u->name }}
                            @if($u->id === auth()->id())
                                <span class="ml-1 px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 text-[10px]">Anda</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ $u->email }}
                            @if($u->phone)
                                <span class="block text-[11px] text-slate-400">📱 {{ $u->phone }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $u->role->label() }}</td>
                        <td class="px-4 py-3">{{ $u->branch?->code ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if($u->is_active)
                                <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[11px] font-medium">Aktif</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full bg-slate-200 text-slate-600 text-[11px] font-medium">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('users.edit', $u) }}" class="text-emerald-700 hover:underline text-xs font-semibold">Edit</a>
                            @if($u->id !== auth()->id())
                                <form method="POST" action="{{ route('users.destroy', $u) }}" class="inline ml-2"
                                      onsubmit="return confirm('Hapus akun {{ $u->name }}? Kalau akun ini pernah menangani tiket, hapus akan ditolak — gunakan Nonaktifkan di halaman Edit.')">
                                    @csrf @method('DELETE')
                                    <button class="text-rose-500 hover:underline text-xs font-semibold">Hapus</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection