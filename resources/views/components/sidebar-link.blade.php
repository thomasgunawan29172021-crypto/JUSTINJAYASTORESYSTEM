@props(['href', 'active' => false])

<a href="{{ $href }}"
   {{ $attributes->class([
        'flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition',
        'bg-emerald-500 text-white font-semibold shadow' => $active,
        'text-slate-400 hover:bg-slate-800 hover:text-white' => ! $active,
   ]) }}>
    {{ $slot }}
</a>