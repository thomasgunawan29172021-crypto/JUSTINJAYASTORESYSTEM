@extends('layouts.app')

@section('title', 'Video Sosmed')

@php $n = fn ($x) => number_format((int) $x, 0, ',', '.'); @endphp

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
        <h1 class="text-xl font-bold">Video Sosmed</h1>
        <a href="{{ route('sosmed.videos.create') }}"
           class="rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold px-4 py-2">+ Catat Video</a>
    </div>

    <form method="GET" class="flex flex-wrap items-end gap-2 mb-5">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Cari</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="judul / kode…"
                   class="rounded-lg border border-slate-300 px-3 py-2 text-sm w-44">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Platform</label>
            <select name="platform_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
                <option value="">Semua</option>
                @foreach($platforms as $p)
                    <option value="{{ $p->id }}" @selected(request('platform_id') == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Pembuat</label>
            <select name="user_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
                <option value="">Semua</option>
                @foreach($staff as $s)
                    <option value="{{ $s->id }}" @selected(request('user_id') == $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="rounded-lg bg-slate-900 text-white px-4 py-2 text-sm font-semibold">Terapkan</button>
    </form>

    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-500 uppercase tracking-wide bg-slate-50">
                <tr>
                    <th class="px-4 py-3">Video</th>
                    <th class="px-2 py-3">Pembuat</th>
                    <th class="px-2 py-3">Tayang</th>
                    <th class="px-2 py-3">Views</th>
                    <th class="px-2 py-3">Like</th>
                    <th class="px-2 py-3">Komen</th>
                    <th class="px-2 py-3">Save</th>
                    <th class="px-2 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($videos as $v)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                @if($v->code)
                                    <span class="shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-mono">{{ $v->code }}</span>
                                @endif
                                <a href="{{ $v->postings->first()?->url ?? '#' }}" target="_blank" rel="noopener" class="font-semibold text-emerald-700 hover:underline">{{ $v->title }}</a>
                                @foreach($v->postings as $p)
                                    <a href="{{ $p->url }}" target="_blank" rel="noopener" title="Buka di {{ $p->platform->name }}"
                                       class="shrink-0 text-[11px] font-semibold text-emerald-700 border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 rounded-md px-1.5 py-0.5">▶ {{ $p->platform->name }}</a>
                                @endforeach
                                @if($v->is_collab)<span class="shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded bg-sky-100 text-sky-700">🤝 colab</span>@endif
                            </div>
                            <p class="text-[11px] text-slate-400">@if($v->theme){{ $v->theme }}@endif</p>
                        </td>
                        <td class="px-2 py-2.5">
                            @php $picU = $v->creators->firstWhere('pivot.is_pic', true); $others = $v->creators->where('pivot.is_pic', false); @endphp
                            <span class="font-semibold">{{ $picU?->name ?? '—' }}</span>
                            @if($others->isNotEmpty())
                                <span class="text-[11px] text-slate-400" title="{{ $others->pluck('name')->join(', ') }}">+{{ $others->count() }}</span>
                            @endif
                        </td>
                        <td class="px-2 py-2.5">{{ $v->published_at->translatedFormat('d M') }}</td>
                        <td class="px-2 py-2.5">{{ $n($v->metricTotal('views')) }}</td>
                        <td class="px-2 py-2.5">{{ $n($v->metricTotal('likes')) }}</td>
                        <td class="px-2 py-2.5">{{ $n($v->metricTotal('comments')) }}</td>
                        <td class="px-2 py-2.5">{{ $n($v->metricTotal('saves')) }}</td>
                        <td class="px-2 py-2.5 text-right whitespace-nowrap">
                            <a href="{{ route('sosmed.videos.edit', $v) }}" class="text-xs font-semibold text-emerald-700 hover:underline mr-2">Edit</a>
                            <form method="POST" action="{{ route('sosmed.videos.destroy', $v) }}" class="inline"
                                  onsubmit="return confirm('Pindahkan video ini ke sampah?')">
                                @csrf @method('DELETE')
                                <button class="text-xs font-semibold text-rose-600 hover:underline">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">Belum ada video tercatat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $videos->links() }}</div>

    <p class="text-[11px] text-slate-400 mt-2">Metrik = gabungan semua platform, pencatatan terakhir per platform.</p>
@endsection
