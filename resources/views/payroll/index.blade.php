@extends('layouts.app')

@section('title', 'Payroll')

@php $rp = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.'); @endphp

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
        <h1 class="text-xl font-bold">Payroll</h1>
        <form method="GET">
            <input type="month" name="period" value="{{ $period }}" max="{{ now()->subMonth()->format('Y-m') }}"
                   onchange="this.form.submit()"
                   class="rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
        </form>
    </div>
    <p class="text-sm text-slate-500 mb-5">Gajian tanggal 5 — slip untuk bulan sebelumnya. Slip yang sudah terbit tidak bisa diubah (snapshot).</p>

    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-500 uppercase tracking-wide bg-slate-50">
                <tr>
                    <th class="px-4 py-3">Karyawan</th>
                    <th class="px-3 py-3">Gaji Pokok</th>
                    <th class="px-3 py-3" title="hari kalender − hari off">Hari Kerja</th>
                    <th class="px-3 py-3">Tarif/Hari</th>
                    <th class="px-3 py-3">Hari Potong</th>
                    <th class="px-3 py-3">Potongan</th>
                    <th class="px-3 py-3">Netto</th>
                    <th class="px-3 py-3 text-right">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($rows as $row)
                    @php $d = $row['slip'] ?? $row['draft']; @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <p class="font-semibold">{{ $row['user']->name }}</p>
                            <p class="text-[11px] text-slate-400">{{ $row['user']->role->label() }}</p>
                        </td>
                        @if($row['error'])
                            <td colspan="6" class="px-3 py-3 text-xs text-amber-700">⚠️ {{ $row['error'] }}</td>
                            <td class="px-3 py-3 text-right">
                                <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-[11px] font-medium">Terblokir</span>
                            </td>
                        @else
                            <td class="px-3 py-3">{{ $rp($d['base_salary']) }}</td>
                            <td class="px-3 py-3">{{ $d['workdays'] }}</td>
                            <td class="px-3 py-3">{{ $rp($d['daily_rate']) }}</td>
                            <td class="px-3 py-3 {{ $d['deducted_days'] > 0 ? 'text-rose-600 font-semibold' : '' }}">{{ $d['deducted_days'] }}</td>
                            <td class="px-3 py-3 text-rose-600">− {{ $rp($d['deduction_amount']) }}</td>
                            <td class="px-3 py-3 font-bold">{{ $rp($d['net_salary']) }}</td>
                            <td class="px-3 py-3 text-right whitespace-nowrap">
                                @if($row['slip'])
                                    <a href="{{ route('payroll.show', $row['slip']) }}"
                                       class="text-emerald-700 text-xs font-semibold hover:underline">
                                        ✓ Terbit {{ $row['slip']->issued_at->format('d/m') }} — lihat slip
                                    </a>
                                @else
                                    <form method="POST" action="{{ route('payroll.issue', $row['user']) }}"
                                          onsubmit="return confirm('Terbitkan slip {{ $row['user']->name }} {{ $period }}? Setelah terbit TIDAK bisa diubah.')">
                                        @csrf
                                        <input type="hidden" name="period" value="{{ $period }}">
                                        <button class="rounded-lg bg-slate-900 text-white text-xs font-semibold px-3 py-1.5">Terbitkan</button>
                                    </form>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection