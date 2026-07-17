<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        return view('marketplace.brands.index', [
            // pics WAJIB ikut eager load: blade memanggilnya per baris (sumber N+1/lag).
            'brands' => Brand::with(['stores', 'pics'])
                ->when($request->filled('q'),
                    fn ($q) => $q->where('name', 'like', '%'.trim($request->string('q')).'%'))
                ->orderBy('name')
                ->paginate(10)
                ->withQueryString(),
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
        $brand->load(['stores', 'storePics']);

        // Dropdown PIC: hanya role Posting (permintaan Thomas) + user yang
        // terlanjur jadi PIC di brand ini (biar assignment lama tak hilang diam-diam).
        $users = \App\Models\User::where('is_active', true)
            ->where(function ($q) use ($brand) {
                $q->where('role', \App\Enums\UserRole::Posting)
                  ->orWhereIn('id', $brand->storePics->pluck('user_id'));
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('marketplace.brands.edit', [
            'brand'  => $brand,
            'stores' => Store::where('is_active', true)->orderBy('marketplace')->orderBy('name')->get(),
            'users'  => $users,
            'picMap' => $brand->storePics->pluck('user_id', 'store_id'),
        ]);
    }

    public function update(Request $request, Brand $brand)
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:100', Rule::unique('brands', 'name')->ignore($brand->id)],
            'stores'       => ['nullable', 'array'],
            'stores.*'     => ['exists:stores,id'],
            'store_pics'   => ['nullable', 'array'],
            'store_pics.*' => ['nullable', 'exists:users,id'],
        ]);

        $storeIds = array_map('intval', $data['stores'] ?? []);

        \Illuminate\Support\Facades\DB::transaction(function () use ($brand, $data, $storeIds) {
            // Program brand pindah ke halaman Program Brand (Pengaturan) —
            // 2 tingkat (depan/belakang), gak bisa diwakili satu field di sini.
            $brand->update(['name' => $data['name']]);
            $brand->stores()->sync($storeIds);

            // Rebuild PIC per toko — hanya toko yang dicentang; dropdown kosong = toko tanpa PIC.
            $brand->storePics()->delete();
            $picUserIds = [];
            foreach ($storeIds as $sid) {
                $uid = (int) ($data['store_pics'][$sid] ?? 0);
                if ($uid > 0) {
                    $brand->storePics()->create(['store_id' => $sid, 'user_id' => $uid]);
                    $picUserIds[$uid] = true;
                }
            }

            // KOMPATIBILITAS TRANSISI: brand_user diisi turunan (semua user yang pegang
            // minimal 1 toko) — task engine lama masih baca dari sini sampai Tahap B.
            $brand->pics()->sync(array_keys($picUserIds));
        });

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
            'brands'    => Brand::onlyTrashed()->with(['stores', 'pics'])
                ->orderBy('deleted_at')->paginate(10),
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
