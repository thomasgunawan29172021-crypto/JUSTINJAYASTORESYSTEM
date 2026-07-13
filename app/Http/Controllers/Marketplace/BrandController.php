<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BrandController extends Controller
{
    public function index()
    {
        return view('marketplace.brands.index', [
            'brands' => Brand::with('stores')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'unique:brands,name']]);

        Brand::create($data);

        return back()->with('ok', "Brand {$data['name']} ditambahkan.");
    }

    public function edit(Brand $brand)
    {
        return view('marketplace.brands.edit', [
            'brand'  => $brand->load('stores'),
            'stores' => Store::where('is_active', true)->orderBy('marketplace')->orderBy('name')->get(),
            'users' => \App\Models\User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Brand $brand)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:100', Rule::unique('brands', 'name')->ignore($brand->id)],
            'stores'   => ['nullable', 'array'],
            'stores.*' => ['exists:stores,id'],
            'pics' => ['array'], 'pics.*' => ['exists:users,id']
        ]);

        $brand->update(['name' => $data['name']]);
        $brand->stores()->sync($data['stores'] ?? []);
        $brand->pics()->sync(array_map('intval', (array) $request->input('pics', [])));

        return redirect()->route('marketplace.brands.index')->with('ok', "Brand {$brand->name} diperbarui.");
    }

    public function destroy(Brand $brand)
    {
        if ($brand->products()->exists()) {
            return back()->withErrors([
                'brand' => "Brand {$brand->name} masih punya produk aktif — pindahkan/hapus produknya dulu.",
            ]);
        }

        $brand->delete();

        return back()->with('ok', "Brand {$brand->name} dipindah ke sampah.");
    }

    public function trash()
    {
        return view('marketplace.brands.index', [
            'brands'    => Brand::onlyTrashed()->with('stores')->orderBy('deleted_at')->get(),
            'trashView' => true,
        ]);
    }

    public function restore(int $id)
    {
        Brand::onlyTrashed()->findOrFail($id)->restore();

        return back()->with('ok', 'Brand dipulihkan.');
    }

    public function clearTrash()
    {
        $skipped = 0;
        foreach (Brand::onlyTrashed()->get() as $brand) {
            try {
                $brand->forceDelete();
            } catch (\Illuminate\Database\QueryException) {
                $skipped++; // masih direferensikan data lain — jangan paksa
            }
        }

        $msg = 'Sampah brand dikosongkan.';
        if ($skipped) $msg .= " {$skipped} brand dilewati (masih dipakai data lain).";

        return back()->with('ok', $msg);
    }
}
