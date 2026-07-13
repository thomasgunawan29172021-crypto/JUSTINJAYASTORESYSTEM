@extends('layouts.app')

@section('title', $ticket->ticket_number)

@php
    use App\Enums\TicketStatus;
    use App\Enums\NotificationType;
    $rp = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.');
@endphp

@section('content')
    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-bold font-mono">{{ $ticket->ticket_number }}</h1>
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $ticket->status->color() }}">
                    {{ $ticket->status->label() }}
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-0.5">
                {{ $ticket->branch->name }} · masuk {{ $ticket->checked_in_at->translatedFormat('d M Y, H:i') }}
                · umur {{ $ticket->ageDays() }} hari
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('service.tickets.receipt', $ticket) }}"
               class="rounded-lg bg-white border border-slate-300 px-4 py-2 text-sm font-semibold hover:border-slate-400">🖨️ Nota</a>
            <a href="{{ route('service.tickets.index') }}"
               class="rounded-lg bg-white border border-slate-300 px-4 py-2 text-sm font-semibold hover:border-slate-400">← Daftar</a>
        </div>
    </div>

    @if($ticket->parentTicket)
        <div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm">
            ⚠️ Tiket ini adalah <b>klaim garansi</b> dari
            <a href="{{ route('service.tickets.show', $ticket->parentTicket) }}" class="font-mono font-bold underline">{{ $ticket->parentTicket->ticket_number }}</a>
        </div>
    @endif

    <div class="grid lg:grid-cols-2 gap-4">
        {{-- ================= KOLOM KIRI: INFO ================= --}}
        <div class="space-y-4">
            <section class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="text-xs font-semibold tracking-widest text-slate-500 uppercase mb-3">Customer & Unit</h2>
                <dl class="text-sm space-y-2">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Nama</dt><dd class="font-semibold">{{ $ticket->customer_name }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Nomor HP</dt><dd class="font-mono">{{ $ticket->customer_phone }}</dd></div>
                    @if($ticket->customer_phone_alt)
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">HP alternatif</dt><dd class="font-mono">{{ $ticket->customer_phone_alt }}</dd></div>
                    @endif
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Unit</dt><dd class="font-semibold">{{ $ticket->device_brand }} {{ $ticket->device_model }}</dd></div>
                    @if($ticket->imei)
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">IMEI</dt><dd class="font-mono">{{ $ticket->imei }}</dd></div>
                    @endif
                    @if($ticket->estimated_done_at)
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Estimasi selesai</dt><dd>{{ $ticket->estimated_done_at->translatedFormat('d M Y') }}</dd></div>
                    @endif
                </dl>

                <div class="mt-3 pt-3 border-t border-slate-100">
                    <p class="text-xs font-semibold text-slate-500 mb-1">Keluhan</p>
                    <p class="text-sm">{{ $ticket->complaint }}</p>
                </div>

                @if($ticket->physical_condition)
                    <div class="mt-3">
                        <p class="text-xs font-semibold text-slate-500 mb-1">Kondisi fisik masuk</p>
                        <p class="text-sm">{{ implode(', ', $ticket->physical_condition) }}</p>
                    </div>
                @endif

                @if($ticket->accessories)
                    <div class="mt-3">
                        <p class="text-xs font-semibold text-slate-500 mb-1">Kelengkapan</p>
                        <p class="text-sm">{{ implode(', ', $ticket->accessories) }}</p>
                    </div>
                @endif

                @if($ticket->device_passcode)
                    <details class="mt-3">
                        <summary class="text-xs font-semibold text-slate-500 cursor-pointer">🔒 Lihat passcode unit</summary>
                        <p class="text-sm font-mono mt-1 bg-slate-100 rounded px-2 py-1 inline-block">{{ $ticket->device_passcode }}</p>
                    </details>
                @endif

                @if($ticket->notes)
                    <div class="mt-3 pt-3 border-t border-slate-100">
                        <p class="text-xs font-semibold text-slate-500 mb-1">Catatan internal</p>
                        <p class="text-sm">{{ $ticket->notes }}</p>
                    </div>
                @endif
            </section>

            {{-- Biaya --}}
            <section class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="text-xs font-semibold tracking-widest text-slate-500 uppercase mb-3">Diagnosa & Biaya</h2>
                @if($ticket->diagnosis)
                    <p class="text-sm mb-3"><span class="text-slate-500">Diagnosa:</span> {{ $ticket->diagnosis }}</p>
                @endif
                <dl class="text-sm space-y-2">
                    <div class="flex justify-between"><dt class="text-slate-500">Estimasi biaya</dt><dd class="font-semibold">{{ $ticket->estimated_cost !== null ? $rp($ticket->estimated_cost) : '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Biaya disetujui</dt><dd class="font-semibold">{{ $ticket->approved_cost !== null ? $rp($ticket->approved_cost) : '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Biaya final</dt><dd class="font-semibold">{{ $ticket->final_cost !== null ? $rp($ticket->final_cost) : '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Modal sparepart</dt><dd>{{ $rp($ticket->partsCost()) }}</dd></div>
                </dl>
                @if($ticket->cancel_reason)
                    <p class="text-sm mt-3 text-rose-600"><b>Alasan batal:</b> {{ $ticket->cancel_reason }}</p>
                @endif
                @if($ticket->warranty_until)
                    <p class="text-sm mt-3 text-emerald-700">✅ Garansi s/d {{ $ticket->warranty_until->translatedFormat('d M Y') }} ({{ $ticket->warranty_days }} hari)</p>
                @endif
            </section>

            {{-- Foto --}}
            <section class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="text-xs font-semibold tracking-widest text-slate-500 uppercase mb-3">Foto ({{ $ticket->photos->count() }})</h2>
                @if($ticket->photos->isEmpty())
                    <p class="text-sm text-slate-400">Belum ada foto.</p>
                @else
                    <div class="grid grid-cols-3 gap-2">
                        @foreach($ticket->photos as $p)
                            <a href="{{ $p->url() }}" target="_blank">
                                <img src="{{ $p->url() }}" alt="Foto {{ $p->type }}" class="rounded-lg border border-slate-200 aspect-square object-cover">
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        {{-- ================= KOLOM KANAN: AKSI ================= --}}
        <div class="space-y-4">
            {{-- Transisi status --}}
            <section class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="text-xs font-semibold tracking-widest text-slate-500 uppercase mb-3">Proses Status</h2>

                @if(empty($ticket->status->allowedTransitions()))
                    <p class="text-sm text-slate-400">Tiket sudah selesai — tidak ada aksi lanjutan.</p>
                @endif

                <div class="space-y-2">
                    @foreach($ticket->status->allowedTransitions() as $to)
                        <details class="rounded-lg border border-slate-200">
                            <summary class="px-3 py-2 text-sm font-semibold cursor-pointer hover:bg-slate-50">
                                → {{ $to->label() }}
                            </summary>
                            <form method="POST" action="{{ route('service.tickets.transition', $ticket) }}" class="p-3 pt-1 space-y-2 border-t border-slate-100">
                                @csrf
                                <input type="hidden" name="status" value="{{ $to->value }}">

                                @if($to === TicketStatus::MenungguKonfirmasi)
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600 mb-1">Hasil diagnosa *</label>
                                        <textarea name="diagnosis" rows="2" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ $ticket->diagnosis }}</textarea>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600 mb-1">Estimasi biaya (Rp) *</label>
                                        <input type="text" name="estimated_cost" value="{{ $ticket->estimated_cost }}" min="0" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    </div>
                                @elseif($to === TicketStatus::Dikerjakan)
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600 mb-1">Biaya disetujui (Rp) <span class="font-normal text-slate-400">— kosongkan = pakai estimasi</span></label>
                                        <input type="text" name="approved_cost" value="{{ $ticket->approved_cost }}" min="0" placeholder="{{ $ticket->estimated_cost }}" class="money-input w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    </div>
                                @elseif($to === TicketStatus::Selesai)
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600 mb-1">Biaya final (Rp) <span class="font-normal text-slate-400">— kosongkan = pakai biaya disetujui</span></label>
                                        <input type="text" name="final_cost" value="{{ $ticket->final_cost }}" min="0" placeholder="{{ $ticket->approved_cost }}" class="money-input w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    </div>
                                @elseif($to === TicketStatus::Dibatalkan)
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600 mb-1">Alasan batal *</label>
                                        <input type="text" name="cancel_reason" required class="money-input w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    </div>
                                @endif

                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Catatan (opsional)</label>
                                    <input type="text" name="note" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                </div>

                                <button class="rounded-lg bg-slate-900 text-white px-4 py-2 text-sm font-semibold hover:bg-slate-800 w-full">
                                    Ubah ke: {{ $to->label() }}
                                </button>
                            </form>
                        </details>
                    @endforeach
                </div>
            </section>

            {{-- Checklist kabari customer (pengganti WA otomatis) --}}
            <section class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="text-xs font-semibold tracking-widest text-slate-500 uppercase mb-1">Checklist Kabari Customer</h2>
                <p class="text-xs text-slate-400 mb-3">Centang setelah customer benar-benar dikabari lewat chat.</p>
                <div class="space-y-2">
                    @foreach(NotificationType::cases() as $type)
                        @php $done = $ticket->notifications->firstWhere('type', $type); @endphp
                        @if($done)
                            <div class="flex items-center justify-between rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-2 text-sm">
                                <span class="font-semibold text-emerald-800">✓ {{ $type->label() }}</span>
                                <span class="text-xs text-emerald-700">{{ $done->user?->name ?? '—' }} · {{ $done->created_at->format('d/m H:i') }}</span>
                            </div>
                        @else
                            <form method="POST" action="{{ route('service.tickets.notify', $ticket) }}">
                                @csrf
                                <input type="hidden" name="type" value="{{ $type->value }}">
                                <button class="w-full flex items-center justify-between rounded-lg border border-slate-300 px-3 py-2 text-sm hover:border-emerald-400 hover:bg-emerald-50/50">
                                    <span>☐ {{ $type->label() }}</span>
                                    <span class="text-xs text-slate-400">klik jika sudah</span>
                                </button>
                            </form>
                        @endif
                    @endforeach
                </div>
            </section>

            {{-- Penugasan --}}
            <section class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="text-xs font-semibold tracking-widest text-slate-500 uppercase mb-3">Penugasan</h2>
                <form method="POST" action="{{ route('service.tickets.assign', $ticket) }}" class="grid sm:grid-cols-2 gap-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Teknisi</label>
                        <select name="technician_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
                            <option value="">— Belum ditentukan —</option>
                            @foreach($technicians as $u)
                                <option value="{{ $u->id }}" @selected($ticket->technician_id === $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Admin chat</label>
                        <select name="admin_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
                            <option value="">— Belum ditentukan —</option>
                            @foreach($admins as $u)
                                <option value="{{ $u->id }}" @selected($ticket->admin_id === $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <button class="rounded-lg bg-slate-900 text-white px-4 py-2 text-sm font-semibold hover:bg-slate-800">Simpan Penugasan</button>
                    </div>
                </form>
            </section>

            {{-- Sparepart --}}
            <section class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="text-xs font-semibold tracking-widest text-slate-500 uppercase mb-3">Sparepart</h2>

                @if($ticket->parts->isNotEmpty())
                    <table class="w-full text-sm mb-3">
                        <thead class="text-left text-xs text-slate-400">
                            <tr><th class="py-1">Nama</th><th>Qty</th><th>Modal</th><th>Jual</th><th></th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($ticket->parts as $p)
                                <tr>
                                    <td class="py-2">{{ $p->name }}</td>
                                    <td>{{ $p->qty }}</td>
                                    <td>{{ $rp($p->cost) }}</td>
                                    <td>{{ $rp($p->price) }}</td>
                                    <td class="text-right">
                                        <form method="POST" action="{{ route('service.tickets.parts.destroy', [$ticket, $p->id]) }}"
                                              onsubmit="return confirm('Hapus sparepart ini?')">
                                            @csrf @method('DELETE')
                                            <button class="text-rose-500 hover:text-rose-700 text-xs font-semibold">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                <form method="POST" action="{{ route('service.tickets.parts.store', $ticket) }}" class="grid grid-cols-2 sm:grid-cols-5 gap-2 items-end">
                    @csrf
                    <div class="col-span-2">
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Nama part</label>
                        <input type="text" name="name" required class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Qty</label>
                        <input type="text" name="qty" value="1" min="1" required class="money-input w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Modal</label>
                        <input type="text" name="cost" min="0" required class="money-input w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Jual</label>
                        <input type="text" name="price" min="0" required class="money-input w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                    </div>
                    <div class="col-span-2 sm:col-span-5">
                        <button class="rounded-lg bg-slate-900 text-white px-4 py-1.5 text-sm font-semibold hover:bg-slate-800">+ Catat Sparepart</button>
                    </div>
                </form>
            </section>
        </div>
    </div>

    {{-- Riwayat --}}
    <section class="bg-white rounded-xl border border-slate-200 p-5 mt-4">
        <h2 class="text-xs font-semibold tracking-widest text-slate-500 uppercase mb-3">Riwayat Status</h2>
        <ol class="space-y-2 text-sm">
            @foreach($ticket->histories as $h)
                <li class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
                    <span class="font-mono text-xs text-slate-400 shrink-0">{{ $h->created_at->format('d/m/y H:i') }}</span>
                    <span>
                        {{ $h->from_status?->label() ?? '🆕' }} → <b>{{ $h->to_status->label() }}</b>
                    </span>
                    <span class="text-xs text-slate-500">oleh {{ $h->user?->name ?? 'Sistem' }}</span>
                    @if($h->note)<span class="text-xs text-slate-400 italic">"{{ $h->note }}"</span>@endif
                </li>
            @endforeach
        </ol>
    </section>
@endsection