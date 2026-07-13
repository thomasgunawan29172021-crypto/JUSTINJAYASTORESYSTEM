@extends('layouts.app')
@section('title', 'Edit '.$product->name)
@section('content')
    <a href="{{ route('marketplace.products.index') }}" class="text-sm text-slate-500 hover:underline">← Produk</a>
    <h1 class="text-xl font-bold mt-2 mb-5">Edit — {{ $product->name }}</h1>
    <form method="POST" action="{{ route('marketplace.products.update', $product) }}"
          class="bg-white rounded-xl border border-slate-200 p-5 max-w-2xl space-y-4">
        @csrf @method('PUT')
        @include('marketplace.products._fields')
        <button class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white px-5 py-2 text-sm font-bold">Simpan</button>
    </form>
@endsection
