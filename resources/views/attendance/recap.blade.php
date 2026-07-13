@extends('layouts.app')

@section('title', 'Rekap Absensi')

@php use App\Services\AttendanceStatusResolver as R; @endphp

@section('content')
    <h1 class="text-xl font-bold mb-4">Rekap Absensi — {{ $month->translatedFormat('F Y') }}</h1>

    <form method="GET" class="flex flex-wrap items-end gap-2 mb-5">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Bulan</label>
            <input type="month" name="month" value="{{ $month->format('Y-m') }}"
                   class="rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Cabang</label>
            <select name="branch_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
                <option value="">Semua</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" @selected(request('branch_id') == $b->id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="rounded-lg bg-slate-900 text-white px-4 py-2 text-sm font-semibold">Terapkan</button>
    </form>

    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-500 uppercase tracking-wide bg-slate-50">
                <tr>
                    <th class="px-3 py-3">Karyawan</th>
                    <th class="px-2 py-3">Hadir</th>
                    <th class="px-2 py-3">Telat</th>
                    <th class="px-2 py-3">Off</th>
                    <th class="px-2 py-3">Libur</th>
                    <th class="px-2 py-3" title="Sakit/cuti/izin yang dibayar">Dibayar</th>
                    <th class="px-2 py-3" title="Izin disetujui tapi dipotong gaji">Izin ✕</th>
                    <th class="px-2 py-3">Menunggu</th>
                    <th class="px-2 py-3">Alpha</th>
                    <th class="px-2 py-3" title="Total hari yang dipotong gaji (izin dipotong + alpha)">Potong</th>
                    <th class="px-2 py-3">Jam Kerja</th>
                    <th class="px-2 py-3">Mnt Telat</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($rows as $row)
                    @php $c = $row['recap']['counts']; @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-3 py-2.5">
                            <a href="{{ route('attendance.recap.show', ['user' => $row['user'], 'month' => $month->format('Y-m')]) }}"
                               class="font-semibold text-emerald-700 hover:underline">{{ $row['user']->name }}</a>
                            <p class="text-[11px] text-slate-400">{{ $row['user']->role->label() }} · {{ $row['user']->branch?->code ?? '—' }}</p>
                        </td>
                        <td class="px-2 py-2.5">{{ $c[R::HADIR] ?? 0 }}</td>
                        <td class="px-2 py-2.5 {{ ($c[R::TELAT] ?? 0) > 0 ? 'text-amber-600 font-semibold' : '' }}">{{ $c[R::TELAT] ?? 0 }}</td>
                        <td class="px-2 py-2.5">{{ $c[R::OFF] ?? 0 }}</td>
                        <td class="px-2 py-2.5">{{ $c[R::LIBUR_NASIONAL] ?? 0 }}</td>
                        <td class="px-2 py-2.5">{{ $c[R::DIBAYAR] ?? 0 }}</td>
                        <td class="px-2 py-2.5">{{ $c[R::IZIN_DIPOTONG] ?? 0 }}</td>
                        <td class="px-2 py-2.5">{{ $c[R::MENUNGGU] ?? 0 }}</td>
                        <td class="px-2 py-2.5 {{ ($c[R::ALPHA] ?? 0) > 0 ? 'text-rose-600 font-bold' : '' }}">{{ $c[R::ALPHA] ?? 0 }}</td>
                        <td class="px-2 py-2.5 font-bold {{ $row['recap']['deducted_days'] > 0 ? 'text-rose-600' : '' }}">{{ $row['recap']['deducted_days'] }}</td>
                        <td class="px-2 py-2.5">{{ $row['recap']['worked_hours'] }} j</td>
                        <td class="px-2 py-2.5">{{ $row['recap']['late_minutes'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="px-4 py-8 text-center text-slate-400">Tidak ada karyawan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="text-[11px] text-slate-400 mt-2">
        "Potong" = jumlah hari kena potongan gaji (izin dipotong + alpha). Kolom ini yang nanti dipakai payroll (Fase A4).
    </p>
@endsection