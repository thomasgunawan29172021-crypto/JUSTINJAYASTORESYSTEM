@extends('layouts.app')

@section('title', $customer->name)

@section('content')
    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
        <div>
            <a href="{{ route('crm.customers.index') }}" class="text-sm text-slate-400 hover:text-slate-700">← Daftar Pelanggan</a>
            <h1 class="text-xl font-bold mt-1">{{ $customer->name }}</h1>
            <p class="text-xs text-slate-400 mt-0.5">
                Terdaftar {{ $customer->created_at->format('d M Y') }} · {{ $customer->branch->name ?? '—' }}
                @if($customer->source)
                    · via {{ $customer->source }}
                @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if(auth()->user()->canManageSales())
                <a href="{{ route('crm.sales.create', ['customer_id' => $customer->id]) }}" class="rounded-xl bg-sky-600 text-white text-sm font-semibold px-4 py-2">+ Penjualan</a>
            @endif
            @if(auth()->user()->canManageWaitingList())
                <a href="{{ route('crm.waiting-list.create', ['customer_id' => $customer->id]) }}" class="rounded-xl bg-emerald-600 text-white text-sm font-semibold px-4 py-2">+ Waiting List</a>
            @endif
            <a href="{{ route('crm.customers.edit', $customer) }}"
               class="rounded-xl border border-slate-300 text-slate-700 text-sm font-semibold px-4 py-2 hover:bg-slate-50">
                Edit
            </a>
            <form method="POST" action="{{ route('crm.customers.destroy', $customer) }}"
                  onsubmit="return confirm('Hapus pelanggan ini?')">
                @csrf @method('DELETE')
                <button class="rounded-xl border border-rose-200 text-rose-600 text-sm font-semibold px-4 py-2 hover:bg-rose-50">
                    Hapus
                </button>
            </form>
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-3">

        {{-- Kolom kiri: info + kontak --}}
        <div class="space-y-4">

            {{-- Info dasar --}}
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="font-bold text-slate-700 mb-3">Informasi</h2>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="text-xs text-slate-400 uppercase">Alamat</dt>
                        <dd class="font-medium">{{ $customer->address ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-400 uppercase">Kota / area</dt>
                        <dd class="font-medium">{{ $customer->city ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-400 uppercase">Catatan</dt>
                        <dd class="text-slate-600">{{ $customer->notes ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-400 uppercase">Dicatat oleh</dt>
                        <dd class="font-medium">{{ $customer->creator->name ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Kontak --}}
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="font-bold text-slate-700 mb-3">Kontak</h2>
                @forelse($customer->contacts as $c)
                    <div class="flex items-center justify-between py-1.5 border-b border-slate-100 last:border-0">
                        <div>
                            <span class="inline-block text-[10px] font-bold uppercase tracking-wide
                                {{ $c->type === 'whatsapp' ? 'text-emerald-600' : 'text-slate-400' }} mr-1">
                                {{ $c->type }}
                            </span>
                            <span class="text-sm font-mono">{{ $c->value }}</span>
                        </div>
                        @if($c->is_primary)
                            <span class="text-[10px] font-bold text-sky-600 bg-sky-50 rounded-full px-2 py-0.5">Utama</span>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-400">Belum ada kontak.</p>
                @endforelse
            </div>
        </div>

        {{-- Kolom tengah + kanan: transaksi, reminder, histori --}}
        <div class="lg:col-span-2 space-y-5">

            @if(auth()->user()->canManageSales())
            {{-- Riwayat Transaksi --}}
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100">
                    <h2 class="font-bold text-slate-700">Riwayat Transaksi</h2>
                    @if(auth()->user()->canManageSales())<a href="{{ route('crm.sales.create', ['customer_id' => $customer->id]) }}" class="text-sm font-semibold text-emerald-700">+ Tambah</a>@endif
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[680px] text-sm">
                        <thead class="text-xs text-slate-500 uppercase bg-slate-50">
                            <tr>
                                <th class="px-4 py-2 text-left">Tanggal</th>
                                <th class="px-4 py-2 text-left">Produk</th>
                                <th class="px-4 py-2 text-right">Total</th>
                                <th class="px-4 py-2 text-left">Pembayaran</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($customer->purchases as $p)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-2 whitespace-nowrap text-xs text-slate-500">
                                        {{ $p->purchased_at->format('d M Y') }}
                                    </td>
                                    <td class="px-4 py-2">{{ $p->items->pluck('product_name')->join(', ') }}</td>
                                    <td class="px-4 py-2 text-right font-mono text-xs">
                                        Rp {{ number_format($p->total_amount, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-2 text-xs">{{ \App\Models\Purchase::PAYMENT_METHODS[$p->payment_method] ?? $p->payment_method }}</td>
                                    <td class="px-4 py-2 text-right">
                                        @if(auth()->user()->canManageSales())<a href="{{ route('crm.sales.show', $p) }}" class="text-xs text-sky-500 hover:underline">Detail</a>@endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-slate-400 text-sm">
                                        Belum ada transaksi.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Reminder --}}
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-3 border-b border-slate-100">
                    <h2 class="font-bold text-slate-700">Reminder</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[620px] text-sm">
                        <thead class="text-xs text-slate-500 uppercase bg-slate-50">
                            <tr>
                                <th class="px-4 py-2 text-left">Jadwal</th>
                                <th class="px-4 py-2 text-left">Jenis</th>
                                <th class="px-4 py-2 text-left">Status</th>
                                <th class="px-4 py-2 text-left">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($customer->reminders as $r)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-2 whitespace-nowrap text-xs text-slate-500">
                                        {{ $r->scheduled_at->format('d M Y') }}
                                    </td>
                                    <td class="px-4 py-2 text-xs">{{ \App\Models\Reminder::TYPES[$r->type] ?? $r->type }}</td>
                                    <td class="px-4 py-2">
                                        @php
                                            $badge = match($r->status) {
                                                'completed' => 'bg-emerald-100 text-emerald-700',
                                                'skipped' => 'bg-amber-100 text-amber-700',
                                                default      => 'bg-slate-100 text-slate-500',
                                            };
                                        @endphp
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-medium {{ $badge }}">
                                            {{ \App\Models\Reminder::STATUSES[$r->status] ?? $r->status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-slate-500">{{ $r->note ?: 'Manual' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-slate-400 text-sm">
                                        Belum ada reminder.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- Histori Audit --}}
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-3 border-b border-slate-100">
                    <h2 class="font-bold text-slate-700">Histori Perubahan</h2>
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse($customer->histories as $h)
                        <li class="px-5 py-3 text-sm flex items-start gap-3">
                            <span class="mt-0.5 w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center text-[11px] font-bold text-slate-500 flex-shrink-0">
                                {{ strtoupper(mb_substr($h->user->name ?? '?', 0, 1)) }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-slate-700">
                                    {{ $h->user->name ?? 'Sistem' }}
                                    <span class="font-normal text-slate-400">·
                                        @php
                                            $actionLabel = match($h->action) {
                                                'created' => 'mendaftarkan pelanggan',
                                                'updated' => 'mengubah data',
                                                'deleted' => 'menghapus pelanggan',
                                                'waiting_created' => 'mencatat waiting list',
                                                'waiting_status' => 'mengubah status waiting list',
                                                'purchase_created' => 'mencatat penjualan',
                                                'purchase_updated' => 'mengubah penjualan',
                                                'purchase_deleted' => 'menghapus penjualan',
                                                'followup_completed' => 'menyelesaikan follow-up',
                                                default   => $h->action,
                                            };
                                        @endphp
                                        {{ $actionLabel }}
                                    </span>
                                </p>
                                @if($h->note)
                                    <p class="text-xs text-slate-500">{{ $h->note }}</p>
                                @endif
                                @if($h->changes && $h->action === 'updated')
                                    <ul class="mt-1 space-y-0.5">
                                        @foreach($h->changes['before'] ?? [] as $field => $before)
                                            <li class="text-xs text-slate-400">
                                                <span class="font-medium text-slate-600">{{ $field }}:</span>
                                                <span class="line-through text-rose-400">{{ $before ?: '—' }}</span>
                                                → <span class="text-emerald-600">{{ $h->changes['after'][$field] ?? '—' }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if($h->changes && $h->action === 'waiting_status')
                                    <p class="text-xs text-slate-500">{{ \App\Models\WaitingItem::STATUSES[$h->changes['before']] ?? '' }} → {{ \App\Models\WaitingItem::STATUSES[$h->changes['after']] ?? '' }}</p>
                                @endif
                            </div>
                            <span class="text-[11px] text-slate-400 whitespace-nowrap flex-shrink-0">
                                {{ \Carbon\Carbon::parse($h->created_at)->format('d M Y H:i') }}
                            </span>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-slate-400 text-sm">Belum ada histori.</li>
                    @endforelse
                </ul>
            </div>

        </div>
    </div>
@endsection
