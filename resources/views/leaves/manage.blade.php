@extends('layouts.app')

@section('title', 'Approval Izin')

@section('content')
    <h1 class="text-xl font-bold mb-5">Approval Izin & Cuti</h1>

    <section class="mb-6">
        <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-2">
            Menunggu Keputusan ({{ $pendings->count() }})
        </h2>

        @if($pendings->isEmpty())
            <div class="bg-white rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-400">
                Tidak ada pengajuan menunggu. 👍
            </div>
        @else
            <div class="space-y-3">
                @foreach($pendings as $r)
                    <div class="bg-white rounded-xl border border-slate-200 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                            <div>
                                <p class="font-semibold text-sm">
                                    {{ $r->user->name }}
                                    <span class="text-slate-400 font-normal">· {{ $r->user->role->label() }} · {{ $r->user->branch?->code ?? '—' }}</span>
                                </p>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    <b>{{ $r->type->label() }}</b> ·
                                    {{ $r->date_from->translatedFormat('d M') }} – {{ $r->date_to->translatedFormat('d M Y') }}
                                    ({{ $r->days() }} hari)
                                </p>
                                @php $age = (int) $r->created_at->diffInDays(now()); @endphp
                                <p class="text-[11px] mt-0.5 {{ $age >= 3 ? 'text-rose-600 font-semibold' : 'text-slate-400' }}">
                                    Diajukan {{ $age }} hari lalu{{ $age >= 3 ? ' — kedaluwarsa otomatis di hari ke-7!' : '' }}
                                </p>
                                <p class="text-sm mt-1">“{{ $r->reason }}”</p>
                                @if($r->attachment_path)
                                    <a href="{{ Storage::disk(config('filesystems.default'))->url($r->attachment_path) }}" target="_blank"
                                       class="text-xs text-emerald-700 underline">📎 Lihat lampiran</a>
                                @endif
                            </div>
                        </div>

                        <form method="POST" action="{{ route('leaves.decide', $r) }}"
                              class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
                            @csrf
                            <input type="text" name="decision_note" placeholder="Catatan (opsional)"
                                   class="flex-1 min-w-40 rounded-lg border border-slate-300 px-3 py-2 text-sm">

                            @if($r->type === \App\Enums\LeaveType::Izin && auth()->user()->role->isCeo())
                                <label class="flex items-center gap-1.5 text-xs text-slate-600 whitespace-nowrap">
                                    <input type="checkbox" name="is_paid" value="1" class="rounded">
                                    Tidak dipotong <span class="text-slate-400">(khusus CEO)</span>
                                </label>
                            @endif

                            <button name="decision" value="approve"
                                    class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-semibold px-4 py-2">
                                ✓ Setujui
                            </button>
                            <button name="decision" value="reject"
                                    class="rounded-lg bg-rose-500 hover:bg-rose-400 text-white text-sm font-semibold px-4 py-2">
                                ✕ Tolak
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section>
        <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-2">Keputusan Terakhir</h2>
        <div class="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100">
            @forelse($recents as $r)
                <div class="px-4 py-2.5 flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span>
                        {{ $r->user->name }} — {{ $r->type->label() }}
                        ({{ $r->date_from->format('d/m') }}–{{ $r->date_to->format('d/m') }})
                    </span>
                    <span class="text-xs text-slate-400">
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-medium {{ $r->status->color() }}">{{ $r->status->label() }}</span>
                        oleh {{ $r->decider?->name ?? 'Sistem' }} · {{ $r->decided_at?->format('d/m H:i') }}
                    </span>
                </div>
            @empty
                <p class="px-4 py-3 text-sm text-slate-400">Belum ada keputusan.</p>
            @endforelse
        </div>
    </section>
@endsection