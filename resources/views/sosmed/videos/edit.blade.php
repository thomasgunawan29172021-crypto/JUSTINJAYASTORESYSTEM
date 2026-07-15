@extends('layouts.app')

@section('title', 'Edit Video')

@section('content')
    <a href="{{ route('sosmed.videos.index') }}" class="text-sm text-slate-500 hover:underline">← Video Sosmed</a>
    <h1 class="text-xl font-bold mt-2 mb-4">Edit Video</h1>

    <form method="POST" action="{{ route('sosmed.videos.update', $video) }}"
          class="bg-white rounded-xl border border-slate-200 p-5 max-w-2xl space-y-4">
        @csrf
        @method('PUT')

        <div class="grid sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-xs font-medium text-slate-600">Pembuat Video (PIC) *</label>
                    <label class="inline-flex items-center gap-1.5 text-xs font-medium cursor-pointer select-none">
                        <input type="checkbox" name="is_collab" value="1" id="collabToggle" class="accent-emerald-500"
                               @checked(old('is_collab', $video->is_collab))>
                        🤝 Video Colab
                    </label>
                </div>
                <select name="pic_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
                    <option value="">— pilih PIC —</option>
                    @foreach($staff as $s)
                        <option value="{{ $s->id }}" @selected(old('pic_id', $picId ?? null) == $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>

                <div id="collabMembers" class="hidden mt-2">
                    <p class="text-xs text-slate-500 mb-1">Anggota colab <span class="text-slate-400">(info saja — target & metrik hanya masuk ke PIC)</span>:</p>
                    <div class="border border-slate-300 rounded-lg p-2 max-h-36 overflow-y-auto grid grid-cols-2 gap-1">
                        @foreach($staff as $s)
                            <label class="inline-flex items-center gap-1.5 text-sm px-1.5 py-1 rounded hover:bg-slate-50 cursor-pointer">
                                <input type="checkbox" name="member_ids[]" value="{{ $s->id }}" class="accent-emerald-500"
                                       @checked(in_array($s->id, old('member_ids', $memberIds ?? [])))>
                                {{ $s->name }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="grid sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Judul Video *</label>
                <input type="text" name="title" required maxlength="200" value="{{ old('title', $video->title) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Kode video <span class="font-normal text-slate-400">(opsional)</span></label>
                <input type="text" name="code" maxlength="50" value="{{ old('code', $video->code) }}"
                       placeholder="mis. VD-0715-01"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>

        <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Platform &amp; Link * <span class="font-normal text-slate-400">(centang semua tempat video ini tayang)</span></label>
            <div class="space-y-2">
                @foreach($platforms as $p)
                    @php $checked = in_array($p->id, old('platform_ids', array_keys($postingUrls ?? []))); @endphp
                    <div class="border border-slate-200 rounded-lg p-2.5">
                        <label class="inline-flex items-center gap-1.5 text-sm font-semibold cursor-pointer select-none">
                            <input type="checkbox" name="platform_ids[]" value="{{ $p->id }}"
                                   class="accent-emerald-500 platform-check" data-target="url-{{ $p->id }}"
                                   data-name="{{ $p->name }}"
                                   data-existing="{{ isset($postingUrls[$p->id]) ? 1 : 0 }}"
                                   @checked($checked)>
                            {{ $p->name }}
                        </label>
                        <input type="url" name="urls[{{ $p->id }}]" id="url-{{ $p->id }}"
                               value="{{ old('urls.'.$p->id, $postingUrls[$p->id] ?? '') }}"
                               placeholder="https://…" {{ $checked ? '' : 'disabled' }}
                               class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-50 disabled:text-slate-300">
                    </div>
                @endforeach
            </div>
            <p class="text-[11px] text-amber-600 mt-1">Platform yang di-uncheck akan dihapus beserta riwayat metrik platform tersebut.</p>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Tema / Format</label>
                <input type="text" name="theme" maxlength="100" value="{{ old('theme', $video->theme) }}"
                       placeholder="unboxing / review / promo…"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Tanggal Tayang *</label>
                <input type="date" name="published_at" required max="{{ now()->toDateString() }}"
                       value="{{ old('published_at', $video->published_at->toDateString()) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>

        <button class="w-full rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-2.5 text-sm">Simpan Perubahan</button>
    </form>

    <script>
    (function () {
        var t = document.getElementById('collabToggle');
        var box = document.getElementById('collabMembers');
        if (t && box) {
            function apply() { box.classList.toggle('hidden', !t.checked); }
            t.addEventListener('change', apply);
            apply();
        }

        // Input URL disabled = tidak ikut ter-submit → urls[] hanya berisi platform tercentang.
        // Nilainya TIDAK dihapus: uncheck tak sengaja lalu check lagi = link kembali utuh.
        document.querySelectorAll('.platform-check').forEach(function (c) {
            var inp = document.getElementById(c.getAttribute('data-target'));
            if (!inp) return;
            c.addEventListener('change', function () { inp.disabled = !c.checked; });
        });

        // Konfirmasi kalau ada platform LAMA (punya data) yang di-uncheck.
        // Form diambil dari checkbox-nya, BUKAN querySelector('form[method=POST]') —
        // form pertama di halaman adalah form Logout di rail layout.
        var firstCheck = document.querySelector('.platform-check');
        var form = firstCheck ? firstCheck.closest('form') : null;

        form && form.addEventListener('submit', function (e) {
            var removed = [];
            document.querySelectorAll('.platform-check').forEach(function (c) {
                if (c.getAttribute('data-existing') === '1' && !c.checked) {
                    removed.push(c.getAttribute('data-name'));
                }
            });
            if (removed.length > 0) {
                var ok = confirm(
                    '⚠️ Platform berikut akan DIHAPUS dari video ini beserta SELURUH riwayat metriknya:\n\n' +
                    '• ' + removed.join('\n• ') +
                    '\n\nLink & metrik platform lain tidak terpengaruh. Lanjutkan simpan?'
                );
                if (!ok) e.preventDefault();
            }
        });
    })();
    </script>

    @php $n = fn ($x) => number_format((int) $x, 0, ',', '.'); @endphp

    @forelse($video->postings as $posting)
        @php $m = $posting->latestSnapshot; @endphp
        <div class="bg-white rounded-xl border border-slate-200 p-5 max-w-2xl mt-4">
            <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-1">
                Koreksi / Refresh Metrik — {{ $posting->platform->name }}
            </h2>
            <p class="text-xs text-slate-500 mb-3">
                Berlaku juga untuk video yang sudah <b>beku</b> — pencatatan baru ditambahkan, riwayat lama tetap tersimpan.
                @if($video->frozen_at) <span class="text-amber-600 font-semibold">Video ini beku sejak {{ $video->frozen_at->format('d/m') }}.</span> @endif
            </p>

            <form method="POST" action="{{ route('sosmed.postings.refresh', $posting) }}" class="grid grid-cols-2 sm:grid-cols-5 gap-3 items-end">
                @csrf
                @foreach(['views' => 'Views', 'likes' => 'Like', 'comments' => 'Komen', 'saves' => 'Save'] as $f => $l)
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">{{ $l }}</label>
                        <input type="number" name="{{ $f }}" min="0" required value="{{ old($f, $m?->{$f} ?? 0) }}"
                               class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                    </div>
                @endforeach
                <button class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-2 text-sm">Simpan</button>
            </form>

            @if($posting->snapshots->isNotEmpty())
                <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mt-5 mb-2">Riwayat Pencatatan</h3>
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-slate-400">
                        <tr><th class="py-1.5">Waktu</th><th>Views</th><th>Like</th><th>Komen</th><th>Save</th><th>Oleh</th><th></th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($posting->snapshots as $s)
                            <tr>
                                <td class="py-2 text-xs">{{ $s->recorded_at->format('d/m H:i') }}</td>
                                <td>{{ $n($s->views) }}</td>
                                <td>{{ $n($s->likes) }}</td>
                                <td>{{ $n($s->comments) }}</td>
                                <td>{{ $n($s->saves) }}</td>
                                <td class="text-xs text-slate-400">{{ $s->recorder?->name ?? '—' }}</td>
                                <td class="text-right">
                                    @if(auth()->user()->role->isCeo())
                                        <form method="POST" action="{{ route('sosmed.snapshots.destroy', $s) }}" class="inline"
                                              onsubmit="return confirm('Hapus pencatatan ini? Angka kembali ke pencatatan sebelumnya.')">
                                            @csrf @method('DELETE')
                                            <button class="text-xs font-semibold text-rose-600 hover:underline">Hapus</button>
                                        </form>
                                    @else
                                        <span class="text-[11px] text-slate-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @empty
        <div class="bg-white rounded-xl border border-dashed border-slate-300 p-5 max-w-2xl mt-4 text-sm text-slate-400">
            Video ini belum punya posting platform — centang platform di form atas lalu simpan.
        </div>
    @endforelse
@endsection
