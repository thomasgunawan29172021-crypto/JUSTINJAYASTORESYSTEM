<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function index()
    {
        return view('marketplace.stores.index', [
            'stores' => Store::orderBy('marketplace')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        Store::create($data);

        return back()->with('ok', "Toko {$data['name']} ditambahkan.");
    }

    public function edit(Store $store)
    {
        return view('marketplace.stores.edit', [
            'store' => $store,
        ]);
    }

    public function update(Request $request, Store $store)
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active');

        $store->update($data);

        return redirect()->route('marketplace.stores.index')->with('ok', "Toko {$store->name} diperbarui.");
    }

    public function destroy(Store $store)
    {
        $store->delete(); // soft — masuk sampah, auto-purge permanen setelah 7 hari

        return back()->with('ok', "Toko {$store->name} dipindah ke sampah.");
    }

    public function trash()
    {
        return view('marketplace.stores.index', [
            'stores'    => Store::onlyTrashed()->orderBy('deleted_at')->get(),
            'trashView' => true,
        ]);
    }

    public function restore(int $id)
    {
        Store::onlyTrashed()->findOrFail($id)->restore();

        return back()->with('ok', 'Toko dipulihkan.');
    }

    public function clearTrash()
    {
        $skipped = 0;
        foreach (Store::onlyTrashed()->get() as $store) {
            try {
                $store->forceDelete();
            } catch (\Illuminate\Database\QueryException) {
                $skipped++; // masih direferensikan data lain — jangan paksa
            }
        }

        $msg = 'Sampah toko dikosongkan.';
        if ($skipped) $msg .= " {$skipped} toko dilewati (masih dipakai data lain).";

        return back()->with('ok', $msg);
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name'             => ['required', 'string', 'max:100'],
            'marketplace'      => ['required', 'string', 'max:50'],
            'account_email'    => ['nullable', 'string', 'max:150'],
            'account_phone'    => ['nullable', 'string', 'max:20'],
            'account_password' => ['nullable', 'string', 'max:1000'],
        ]);

        // Normalisasi: "Shopee"/"SHOPEE"/"shopee" = satu grup di laporan
        $data['marketplace'] = strtolower(trim($data['marketplace']));
        $data['is_mall']     = $request->boolean('is_mall');

        return $data;
    }
}
