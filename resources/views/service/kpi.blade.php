@extends('layouts.app')

@section('title', 'KPI Servis')

@php $rp = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.'); @endphp

@section('content')
    <h1 class="text-xl font-bold mb-4">KPI Divisi Servis</h1>

    <form method="GET" class="flex flex-wrap items-end gap-2 mb-5">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Dari</label>
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Sampai</label>
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Cabang</label>
            <select name="branch_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
                <option value="">Semua</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" @selected($branchId === $b->id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="rounded-lg bg-slate-900 text-white px-4 py-2 text-sm font-semibold">Terapkan</button>
    </form>

    @php $s = $data['summary']; @endphp
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-6">
        @foreach([
            ['Unit masuk', $s['tickets_in']],
            ['Unit keluar', $s['tickets_out']],
            ['Masih di toko', $s['open_now']],
            ['Rata-rata TAT', $s['avg_tat_days'].' hari'],
            ['Cancel rate', $s['cancel_rate'].'%'],
            ['Omzet servis', $rp($s['service_revenue'])],
            ['Modal sparepart', $rp($s['parts_cost'])],
            ['Jeda kabari customer', $s['avg_notify_lag_min'].' mnt'],
        ] as [$label, $value])
            <div class="bg-white rounded-xl border border-slate-200 px-4 py-3">
                <p class="text-xs text-slate-500">{{ $label }}</p>
                <p class="text-lg font-bold">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid lg:grid-cols-2 gap-4 mb-6">
        <section class="bg-white rounded-xl border border-slate-200 p-4">
            <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-3">Kinerja Teknisi</h2>
            @if($data['technicians']->isEmpty())
                <p class="text-sm text-slate-400">Belum ada data di periode ini.</p>
            @else
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-slate-400">
                        <tr>
                            <th class="py-1.5">Teknisi</th>
                            <th title="Unit selesai diperbaiki">Selesai</th>
                            <th title="Rata-rata lama diagnosa">Diagnosa</th>
                            <th title="Rata-rata lama pengerjaan">Kerja</th>
                            <th title="Berapa kali gagal QC (balik dikerjakan)">Gagal QC</th>
                            <th title="% unit yang tidak balik klaim garansi">FTF</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($data['technicians'] as $t)
                            <tr>
                                <td class="py-2 font-medium">{{ $t['name'] }}</td>
                                <td>{{ $t['done'] }}</td>
                                <td>{{ $t['avg_diagnosa_hours'] !== null ? $t['avg_diagnosa_hours'].' jam' : '—' }}</td>
                                <td>{{ $t['avg_work_hours'] !== null ? $t['avg_work_hours'].' jam' : '—' }}</td>
                                <td>{{ $t['qc_fail'] }}</td>
                                <td class="font-semibold {{ ($t['ftf_rate'] ?? 100) < 90 ? 'text-rose-600' : 'text-emerald-600' }}">
                                    {{ $t['ftf_rate'] !== null ? $t['ftf_rate'].'%' : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <section class="bg-white rounded-xl border border-slate-200 p-4">
            <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-3">Kinerja Admin Chat</h2>
            @if($data['admins']->isEmpty())
                <p class="text-sm text-slate-400">Belum ada data di periode ini.</p>
            @else
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-slate-400">
                        <tr>
                            <th class="py-1.5">Admin</th>
                            <th title="Tiket yang jadi tanggung jawabnya">Ditangani</th>
                            <th title="% estimasi yang berujung disetujui">Approval</th>
                            <th title="Jeda dari selesai QC sampai customer dikabari">Jeda kabari</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($data['admins'] as $a)
                            <tr>
                                <td class="py-2 font-medium">{{ $a['name'] }}</td>
                                <td>{{ $a['handled'] }}</td>
                                <td>{{ $a['approval_rate'] !== null ? $a['approval_rate'].'%' : '—' }}</td>
                                <td>{{ $a['avg_notify_min'] !== null ? $a['avg_notify_min'].' mnt' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>

    <div class="grid lg:grid-cols-2 gap-4">
        <section class="bg-white rounded-xl border border-rose-200 p-4">
            <h2 class="text-sm font-semibold text-rose-700 uppercase tracking-wide mb-3">⚠️ Backlog Macet (≥ 7 hari)</h2>
            @forelse($data['backlog'] as $t)
                <a href="{{ route('service.tickets.show', $t) }}" class="flex items-center justify-between py-1.5 border-b border-slate-100 text-sm hover:bg-slate-50">
                    <span><span class="font-mono text-xs text-slate-500">{{ $t->ticket_number }}</span> — {{ $t->device_brand }} {{ $t->device_model }}</span>
                    <span class="text-rose-600 font-semibold">{{ $t->ageDays() }} hari</span>
                </a>
            @empty
                <p class="text-sm text-slate-400">Bersih — tidak ada tiket macet. 👍</p>
            @endforelse
        </section>

        <section class="bg-white rounded-xl border border-amber-200 p-4">
            <h2 class="text-sm font-semibold text-amber-700 uppercase tracking-wide mb-3">📦 Selesai Tapi Belum Diambil (> 3 hari)</h2>
            @forelse($data['notPickedUp'] as $t)
                <a href="{{ route('service.tickets.show', $t) }}" class="flex items-center justify-between py-1.5 border-b border-slate-100 text-sm hover:bg-slate-50">
                    <span><span class="font-mono text-xs text-slate-500">{{ $t->ticket_number }}</span> — {{ $t->customer_name }}</span>
                    <span class="text-amber-700 font-semibold">sejak {{ $t->completed_at->translatedFormat('d M') }}</span>
                </a>
            @empty
                <p class="text-sm text-slate-400">Semua unit selesai sudah diambil. 👍</p>
            @endforelse
        </section>
    </div>
@endsection