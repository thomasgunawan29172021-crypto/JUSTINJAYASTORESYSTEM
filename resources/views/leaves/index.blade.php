@extends('layouts.app')

@section('title', 'Izin & Cuti')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-2 mb-5">
        <h1 class="text-xl font-bold">Izin & Cuti</h1>
        <span class="text-sm text-slate-500">
            Kuota cuti {{ now()->year }}: <b>{{ $cutiQuota - $cutiUsed }}</b> / {{ $cutiQuota }} hari tersisa
        </span>
    </div>

    <div class="grid lg:grid-cols-2 gap-4">
        {{-- Form pengajuan --}}
        <form method="POST" action="{{ route('leaves.store') }}" enctype="multipart/form-data"
              class="bg-white rounded-xl border border-slate-200 p-5 space-y-4 self-start">
            @csrf
            <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide">Ajukan Baru</h2>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Jenis *</label>
                <select name="type" id="leave-type" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
                    @foreach(\App\Enums\LeaveType::cases() as $t)
                        <option value="{{ $t->value }}" @selected(old('type') === $t->value)>{{ $t->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Dari *</label>
                    <input type="date" name="date_from" value="{{ old('date_from') }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Sampai *</label>
                    <input type="date" name="date_to" value="{{ old('date_to') }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Alasan *</label>
                <textarea name="reason" rows="2" required
                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('reason') }}</textarea>
            </div>

            <div id="attachment-box">
                <label class="block text-xs font-semibold text-slate-600 mb-1">
                    Lampiran <span id="attachment-hint" class="font-normal text-slate-400">(wajib untuk sakit — surat dokter)</span>
                </label>
                <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf"
                       class="w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-900 file:text-white file:px-4 file:py-2 file:text-sm">
            </div>

            <button class="w-full rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-2.5 text-sm">
                Kirim Pengajuan
            </button>
            <p class="text-[11px] text-slate-400">
                Izin pribadi dipotong gaji kecuali CEO memutuskan sebaliknya. Sakit bersurat & cuti dibayar penuh.
            </p>
        </form>

        {{-- Riwayat pengajuan --}}
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-3">Pengajuan Saya</h2>
            @if($requests->isEmpty())
                <p class="text-sm text-slate-400">Belum ada pengajuan.</p>
            @else
                <div class="space-y-2">
                    @foreach($requests as $r)
                        <div class="rounded-lg border border-slate-200 px-4 py-3">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold">{{ $r->type->label() }}</p>
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-medium {{ $r->status->color() }}">
                                    {{ $r->status->label() }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">
                                {{ $r->date_from->translatedFormat('d M') }} – {{ $r->date_to->translatedFormat('d M Y') }}
                                ({{ $r->days() }} hari) · {{ $r->reason }}
                            </p>
                            @if($r->status === \App\Enums\LeaveStatus::Approved)
                                <p class="text-[11px] mt-1 {{ $r->is_paid ? 'text-emerald-600' : 'text-rose-600' }}">
                                    {{ $r->is_paid ? '✓ Dibayar (tidak dipotong)' : '✕ Dipotong gaji' }}
                                    · oleh {{ $r->decider?->name }} {{ $r->decided_at?->format('d/m H:i') }}
                                    @if($r->decision_note) — “{{ $r->decision_note }}” @endif
                                </p>
                            @elseif($r->status === \App\Enums\LeaveStatus::Rejected)
                                <p class="text-[11px] text-rose-600 mt-1">
                                    Ditolak oleh {{ $r->decider?->name }}
                                    @if($r->decision_note) — “{{ $r->decision_note }}” @endif
                                </p>
                            @elseif($r->status === \App\Enums\LeaveStatus::Pending)
                                <form method="POST" action="{{ route('leaves.destroy', $r) }}" class="mt-1"
                                      onsubmit="return confirm('Batalkan pengajuan ini?')">
                                    @csrf @method('DELETE')
                                    <button class="text-[11px] text-rose-500 hover:underline">Batalkan</button>
                                </form>
                            @else
                                <p class="text-[11px] text-slate-500 mt-1">
                                    ⏱ Kedaluwarsa — tidak diputuskan dalam 7 hari. Silakan ajukan ulang jika masih perlu.
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection