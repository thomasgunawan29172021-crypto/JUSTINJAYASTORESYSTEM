@extends('layouts.app')

@section('title', 'Edit Brand '.$brand->name)

@section('content')
    <a href="{{ route('marketplace.brands.index') }}" class="text-sm text-slate-500 hover:underline">← Brand</a>
    <h1 class="text-xl font-bold mt-2 mb-5">Edit Brand — {{ $brand->name }}</h1>

    <form method="POST" action="{{ route('marketplace.brands.update', $brand) }}"
          class="bg-white rounded-xl border border-slate-200 p-5 max-w-lg space-y-4">
        @csrf @method('PUT')

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Nama brand *</label>
            <input type="text" name="name" value="{{ old('name', $brand->name) }}" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div>
            <p class="text-xs font-semibold text-slate-600 mb-1">Toko target & PIC per toko:</p>
            <p class="text-[11px] text-slate-400 mb-2">
                Centang toko tempat brand ini diposting, lalu pilih SATU penanggung jawab per toko.
                Satu toko satu PIC — tidak bisa dobel. Dropdown kosong = toko belum ada yang pegang (muncul di alert).
            </p>
            <div class="space-y-1.5">
                @foreach($stores as $s)
                    @php $on = $brand->stores->contains($s->id); @endphp
                    <div class="flex items-center gap-2">
                        <label class="flex items-center gap-2 text-sm cursor-pointer flex-1 min-w-0">
                            <input type="checkbox" name="stores[]" value="{{ $s->id }}" class="rounded store-check"
                                   data-pic="pic-{{ $s->id }}" @checked($on)>
                            <span class="truncate">{{ $s->label() }}</span>
                        </label>
                        <select name="store_pics[{{ $s->id }}]" id="pic-{{ $s->id }}"
                                class="w-44 rounded-lg border border-slate-300 px-2 py-1.5 text-xs bg-white disabled:bg-slate-50 disabled:text-slate-300"
                                {{ $on ? '' : 'disabled' }}>
                            <option value="">— PIC toko ini —</option>
                            @foreach($users as $usr)
                                <option value="{{ $usr->id }}" @selected(($picMap[$s->id] ?? null) == $usr->id)>{{ $usr->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>

            @if($stores->isEmpty())
                <p class="text-xs text-amber-600">Belum ada toko aktif — tambahkan toko dulu.</p>
            @endif
        </div>

        <div class="flex items-center gap-3 pt-1">
            <button class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white px-5 py-2 text-sm font-bold">Simpan</button>
            <a href="{{ route('marketplace.brands.index') }}" class="text-sm text-slate-500 hover:underline">Batal</a>
        </div>
    </form>

    <script>
    document.querySelectorAll('.store-check').forEach(function (c) {
        var sel = document.getElementById(c.getAttribute('data-pic'));
        function apply() { sel.disabled = !c.checked; if (!c.checked) sel.value = ''; }
        c.addEventListener('change', apply);
    });
    </script>
@endsection
