@extends('layouts.app')

@section('title', 'Update Metrik')

@php $n = fn ($x) => number_format((int) $x, 0, ',', '.'); @endphp

@section('content')
    <h1 class="text-xl font-bold mb-1">Update Metrik Video</h1>
    <p class="text-sm text-slate-500 mb-5">
        Satu baris = satu platform. Video multi-platform muncul beberapa baris.
        Kolom kosong = pakai angka pencatatan terakhir (tidak di-nol-kan). Nol harus diketik.
        Video berumur ≥ {{ \App\Models\SocialVideo::DUE_DAYS }} hari yang diupdate akan <b>dibekukan</b> (angka final).
    </p>

    @if($postings->isEmpty())
        <div class="bg-white rounded-xl border border-dashed border-slate-300 p-6 text-sm text-slate-400">
            Tidak ada posting aktif. Semua video sudah beku. 👍
        </div>
    @else
        <form method="POST" action="{{ route('sosmed.metrics.store') }}">
            @csrf
            <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-slate-500 uppercase tracking-wide bg-slate-50">
                        <tr>
                            <th class="px-4 py-3">Video</th>
                            <th class="px-2 py-3">Umur</th>
                            <th class="px-2 py-3">Terakhir</th>
                            <th class="px-2 py-3">Views</th>
                            <th class="px-2 py-3">Like</th>
                            <th class="px-2 py-3">Komen</th>
                            <th class="px-2 py-3">Save</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($postings as $p)
                            @php
                                $m    = $p->latestSnapshot;
                                $v    = $p->video;
                                $picU = $v->creators->firstWhere('pivot.is_pic', true);
                            @endphp
                            <tr class="{{ $v->isDue() ? 'bg-amber-50' : '' }}">
                                <td class="px-4 py-2">
                                    <a href="{{ $p->url }}" target="_blank" rel="noopener" class="font-semibold text-emerald-700 hover:underline">{{ $v->title }}</a>
                                    <p class="text-[11px] text-slate-400">
                                        {{ $p->platform->name }} · {{ $picU?->name ?? '—' }}@if($v->is_collab) · 🤝 colab @endif
                                    </p>
                                </td>
                                <td class="px-2 py-2 text-xs {{ $v->isDue() ? 'text-amber-700 font-bold' : 'text-slate-500' }}">
                                    {{ $v->daysLive() }} hr{{ $v->isDue() ? ' · due' : '' }}
                                </td>
                                <td class="px-2 py-2 text-[11px] text-slate-400">
                                    {{ $m ? $n($m->views).' v · '.$m->recorded_at->format('d/m') : '—' }}
                                </td>
                                @foreach(['views', 'likes', 'comments', 'saves'] as $f)
                                    <td class="px-2 py-2">
                                        <input type="number" name="metrics[{{ $p->id }}][{{ $f }}]" min="0"
                                               placeholder="{{ $m ? $n($m->{$f}) : '0' }}"
                                               class="w-24 rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between mt-3">
                <div>{{ $postings->links() }}</div>
                <button class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white font-bold px-6 py-2.5 text-sm">
                    💾 Simpan Semua
                </button>
            </div>
        </form>
    @endif

    <p class="text-[11px] text-slate-400 mt-2">
        Baris kuning = jatuh tempo update final. Video lewat {{ \App\Models\SocialVideo::FORCE_DAYS }} hari tanpa update dibekukan otomatis oleh sistem tiap malam.
    </p>
@endsection
