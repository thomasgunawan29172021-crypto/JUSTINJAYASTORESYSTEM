@extends('layouts.app')

@section('title', 'Sampah Cuti & Izin')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('leaves.manage') }}"
               class="rounded-lg bg-white border border-slate-300 px-3 py-1.5 text-xs font-semibold hover:border-slate-400">
                ← Kembali ke Approval
            </a>
            <p class="text-sm text-rose-700">🗑 Sampah Cuti & Izin — terhapus permanen otomatis setelah <b>60 hari</b>.</p>
        </div>
        @if($trashed->total() > 0)
            <form method="POST" action="{{ route('leaves.trash.clear') }}"
                  onsubmit="return confirm('Hapus PERMANEN semua {{ $trashed->total() }} pengajuan di sampah? Tidak bisa dibatalkan.')">
                @csrf @method('DELETE')
                <button class="rounded-lg bg-rose-600 text-white text-xs font-bold px-3 py-1.5">Kosongkan Sampah</button>
            </form>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100">
        @forelse($trashed as $r)
            <div class="px-4 py-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                <div>
                    <p class="font-semibold">
                        {{ $r->user->name }}
                        <span class="text-slate-400 font-normal">· {{ $r->type->label() }} · {{ $r->date_from->format('d/m') }}–{{ $r->date_to->format('d/m/Y') }}</span>
                    </p>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Status sebelumnya:
                        <span class="px-1.5 py-0.5 rounded-full text-[11px] font-medium {{ $r->status->color() }}">{{ $r->status->label() }}</span>
                        @if($r->status === \App\Enums\LeaveStatus::Approved)
                            <span class="text-rose-600 font-semibold">— sedang dihitung ALPHA selama di sampah</span>
                        @endif
                    </p>
                    <p class="text-[11px] text-slate-400 mt-0.5">
                        Dihapus {{ $r->deleted_at->diffForHumans() }} · musnah permanen {{ $r->deleted_at->copy()->addDays(60)->translatedFormat('d M Y') }}
                    </p>
                </div>
                <div class="whitespace-nowrap flex items-center gap-2">
                    <form method="POST" action="{{ route('leaves.trash.restore', $r->id) }}" class="inline">
                        @csrf @method('PATCH')
                        <button class="text-sky-600 text-xs font-semibold hover:underline">Pulihkan</button>
                    </form>
                    <form method="POST" action="{{ route('leaves.trash.destroy', $r->id) }}" class="inline"
                          onsubmit="return confirm('Hapus PERMANEN pengajuan {{ $r->user->name }}? Tidak bisa dipulihkan lagi.')">
                        @csrf @method('DELETE')
                        <button class="text-rose-600 text-xs font-semibold hover:underline">Hapus Permanen</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="px-4 py-8 text-center text-sm text-slate-400">Sampah kosong.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $trashed->links() }}</div>
@endsection
