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

    <form method="GET" class="mb-4 flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-3 sm:flex-row sm:items-end">
        <label class="text-xs font-semibold text-slate-600">Filter tipe promotor
            <select name="promotor_type" class="mt-1 block min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm sm:w-56">
                <option value="">Semua tipe</option>
                <option value="none" @selected(($filters['promotor_type'] ?? '') === 'none')>Bukan Promotor</option>
                @foreach(\App\Models\User::PROMOTOR_TYPES as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['promotor_type'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <button class="min-h-10 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Tampilkan</button>
        @if(filled($filters['promotor_type'] ?? null))<a href="{{ route('users.index') }}" class="flex min-h-10 items-center justify-center rounded-lg border px-4 py-2 text-sm">Reset</a>@endif
    </form>

    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-500 uppercase tracking-wide bg-slate-50">
                <tr>
                    <th class="px-4 py-3">Id</th>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Kontak</th>
                    <th class="px-4 py-3">Role</th>
                    <th class="px-4 py-3">Tipe Promotor</th>
                    <th class="px-4 py-3">Cabang Absensi</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($users as $u)
                    @php
                        $attendanceBranches = $u->branches;
                        if ($u->branch && ! $attendanceBranches->contains('id', $u->branch->id)) {
                            $attendanceBranches = $attendanceBranches->push($u->branch);
                        }
                        $attendanceBranches = $attendanceBranches->unique('id')->sortBy('name');
                    @endphp
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
                        <td class="px-4 py-3">
                            {{ $u->role->label() }}
                            @foreach($u->extra_roles ?? [] as $er)
                                @php $erEnum = \App\Enums\UserRole::tryFrom($er); @endphp
                                @if($erEnum)
                                    <span class="ml-1 px-1.5 py-0.5 rounded bg-violet-100 text-violet-700 text-[10px]">+ {{ $erEnum->label() }}</span>
                                @endif
                            @endforeach
                        </td>
                        <td class="px-4 py-3">
                            @if($u->promotor_type)
                                <span class="rounded-full bg-violet-100 px-2 py-1 text-[11px] font-semibold text-violet-700">{{ \App\Models\User::PROMOTOR_TYPES[$u->promotor_type] }}</span>
                            @else
                                <span class="text-xs text-slate-400">Bukan Promotor</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @forelse($attendanceBranches as $branch)
                                <span class="inline-flex items-center mb-1 mr-1 px-2 py-0.5 rounded-full text-[11px] font-medium
                                    {{ $branch->id === $u->branch_id ? 'bg-emerald-100 text-emerald-800' : 'bg-sky-100 text-sky-800' }}">
                                    {{ $branch->code }}{{ $branch->id === $u->branch_id ? ' · utama' : '' }}
                                </span>
                            @empty
                                <span class="text-slate-400">—</span>
                            @endforelse
                        </td>
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
