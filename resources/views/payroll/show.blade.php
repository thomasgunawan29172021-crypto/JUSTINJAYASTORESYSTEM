@extends('layouts.app')

@section('title', 'Slip '.$slip->user->name.' '.$slip->period)

@php
    use App\Services\AttendanceStatusResolver as R;
    $rp = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.');
@endphp

@section('content')
    <a href="{{ route('payroll.index', ['period' => $slip->period]) }}" class="text-sm text-slate-500 hover:underline">← Payroll</a>

    <div class="max-w-2xl mt-2">
        <div class="bg-white rounded-xl border border-slate-200 p-6 mb-4">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <p class="text-lg font-bold">{{ $slip->user->name }}</p>
                    <p class="text-xs text-slate-500">{{ $slip->user->role->label() }} · {{ $slip->user->branch?->name ?? '—' }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-slate-500">Periode</p>
                    <p class="font-bold">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $slip->period)->translatedFormat('F Y') }}</p>
                </div>
            </div>

            <dl class="text-sm space-y-2">
                <div class="flex justify-between"><dt class="text-slate-500">Gaji pokok</dt><dd class="font-semibold">{{ $rp($slip->base_salary) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Hari kerja (pembagi)</dt><dd>{{ $slip->workdays }} hari</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Tarif per hari</dt><dd>{{ $rp($slip->daily_rate) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Hari dipotong (alpha + izin dipotong)</dt><dd class="text-rose-600 font-semibold">{{ $slip->deducted_days }} hari</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Total potongan</dt><dd class="text-rose-600 font-semibold">− {{ $rp($slip->deduction_amount) }}</dd></div>
                <div class="flex justify-between border-t border-slate-200 pt-2 mt-2">
                    <dt class="font-bold">GAJI BERSIH</dt><dd class="font-extrabold text-lg">{{ $rp($slip->net_salary) }}</dd>
                </div>
            </dl>

            <p class="text-[11px] text-slate-400 mt-4">
                Diterbitkan {{ $slip->issued_at->translatedFormat('d M Y, H:i') }} oleh {{ $slip->issuer->name }} · snapshot permanen
            </p>
        </div>

        <details class="bg-white rounded-xl border border-slate-200 p-5">
            <summary class="text-sm font-semibold text-slate-600 cursor-pointer select-none">Rincian status per tanggal (bukti perhitungan)</summary>
            <div class="mt-3 grid grid-cols-2 sm:grid-cols-3 gap-1 text-xs">
                @foreach($slip->day_statuses as $date => $status)
                    <div class="flex justify-between rounded px-2 py-1 {{ $status && R::isDeducted($status) ? 'bg-rose-50 text-rose-700' : 'bg-slate-50 text-slate-600' }}">
                        <span>{{ \Illuminate\Support\Carbon::parse($date)->format('d/m') }}</span>
                        <span class="font-medium">{{ $status ? R::label($status) : '—' }}</span>
                    </div>
                @endforeach
            </div>
        </details>
    </div>
@endsection