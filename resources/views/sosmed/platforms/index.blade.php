@extends('layouts.app')

@section('title', 'Platform Sosmed')

@section('content')
    <h1 class="text-xl font-bold mb-1">Platform Sosmed</h1>
    <p class="text-sm text-slate-500 mb-5">Kelola daftar platform tempat video tayang. Platform yang masih dipakai video tidak bisa dihapus.</p>

    {{-- ===== ALERT: HAPUS DIBLOKIR (dengan link langsung ke tiap video) ===== --}}
    @if(session('blockedDelete'))
        @php $b = session('blockedDelete'); @endphp
        <div class="mb-5 rounded-xl border border-rose-300 bg-rose-50 px-4 py-3">
            <p class="text-sm font-bold text-rose-800 mb-1">
                🔴 Platform "{{ $b['platform'] }}" tidak bisa dihapus — masih dipakai {{ count($b['videos']) }} video:
            </p>
            <ul class="text-sm text-rose-700 space-y-1 mt-2">
                @foreach($b['videos'] as $v)
                    <li class="flex items-center gap-2">
                        <span class="text-[11px] text-rose-400">{{ $v['date'] }}</span>
                        <a href="{{ $v['url'] }}" class="font-semibold underline hover:text-rose-900">{{ $v['title'] }}</a>
                        <span class="text-[11px] text-rose-400">→ klik untuk ubah platform / hapus videonya</span>
                    </li>
                @endforeach
            </ul>
            <p class="text-[11px] text-rose-500 mt-2">Setelah semua video di atas tidak memakai platform ini lagi, hapus bisa dilakukan.</p>
        </div>
    @endif

    <div class="grid lg:grid-cols-3 gap-4">
        {{-- ===== FORM TAMBAH ===== --}}
        <section class="bg-white rounded-xl border border-slate-200 p-4 h-fit">
            <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-3">+ Platform Baru</h2>
            <form method="POST" action="{{ route('sosmed.platforms.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Nama *</label>
                    <input type="text" name="name" required maxlength="50" value="{{ old('name') }}"
                           placeholder="Threads / X / Snack Video…"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Domain link (opsional)</label>
                    <input type="text" name="domains" maxlength="300" value="{{ old('domains') }}"
                           placeholder="threads.net"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <p class="text-[11px] text-slate-400 mt-1">Dipisah koma kalau lebih dari satu. Kosongkan = link tidak divalidasi domainnya.</p>
                </div>
                <button class="w-full rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-2 text-sm">Tambah</button>
            </form>
        </section>

        {{-- ===== DAFTAR ===== --}}
        <section class="lg:col-span-2 bg-white rounded-xl border border-slate-200">
            {{-- header kolom (desktop) --}}
            <div class="hidden sm:grid grid-cols-[10rem_1fr_6rem_9rem] gap-3 px-4 py-3 bg-slate-50 rounded-t-xl
                        text-xs text-slate-500 uppercase tracking-wide font-semibold">
                <span>Platform</span>
                <span>Domain</span>
                <span title="Jumlah posting video yang memakai platform ini">Dipakai</span>
                <span class="text-right">Aksi</span>
            </div>

            <div class="divide-y divide-slate-100">
                @foreach($platforms as $p)
                    <form method="POST" action="{{ route('sosmed.platforms.update', $p) }}"
                          id="pf-{{ $p->id }}"
                          class="grid grid-cols-1 sm:grid-cols-[10rem_1fr_6rem_9rem] gap-2 sm:gap-3 items-center px-4 py-3 hover:bg-slate-50">
                        @csrf @method('PUT')

                        <input type="text" name="name" required maxlength="50" value="{{ $p->name }}"
                               class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm font-semibold">

                        <input type="text" name="domains" maxlength="300" value="{{ $p->domains }}"
                               placeholder="tanpa validasi domain"
                               class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs text-slate-600">

                        <span class="text-xs {{ $p->postings_count > 0 ? 'font-semibold text-slate-700' : 'text-slate-300' }}">
                            <span class="sm:hidden text-slate-400 font-normal">Dipakai: </span>{{ $p->postings_count }} posting
                        </span>

                        <div class="flex items-center sm:justify-end gap-1.5">
                            <button class="rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold px-3 py-1.5">
                                Simpan
                            </button>
                            <button type="submit" form="pfdel-{{ $p->id }}"
                                    class="rounded-lg border {{ $p->postings_count > 0 ? 'border-slate-200 text-slate-300 cursor-not-allowed' : 'border-rose-200 text-rose-600 hover:bg-rose-50' }} text-xs font-semibold px-3 py-1.5"
                                    title="{{ $p->postings_count > 0 ? 'Masih dipakai '.$p->postings_count.' posting — klik untuk lihat videonya' : 'Hapus platform' }}">
                                Hapus
                            </button>
                        </div>
                    </form>

                    {{-- form delete terpisah di luar form update (form nested itu invalid HTML) --}}
                    <form method="POST" action="{{ route('sosmed.platforms.destroy', $p) }}" id="pfdel-{{ $p->id }}" class="hidden"
                          onsubmit="return confirm('Hapus platform {{ $p->name }}?{{ $p->postings_count > 0 ? ' Platform ini masih dipakai '.$p->postings_count.' posting — penghapusan akan DITOLAK dan daftar videonya ditampilkan.' : '' }}')">
                        @csrf @method('DELETE')
                    </form>
                @endforeach
            </div>
        </section>
    </div>

    <p class="text-[11px] text-slate-400 mt-2">
        Mengubah domain hanya memengaruhi validasi link video BARU — link lama tidak dicek ulang.
        Mengganti nama platform langsung berlaku di semua tampilan.
    </p>
@endsection
