<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\ProductDiscount;
use App\Models\Store;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    public function index(Request $request)
    {
        $storeId = (int) $request->input('store_id');

        return view('marketplace.discounts.index', [
            'discounts' => ProductDiscount::with('stores')
                ->when($storeId, fn ($q) => $q->whereHas('stores',
                    fn ($s) => $s->where('stores.id', $storeId)))
                ->orderBy('ends_at')->get(),
            'stores'    => Store::where('is_active', true)->orderBy('marketplace')->orderBy('name')->get(),
            'storeId'   => $storeId,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:150'],
            'type'      => ['required', 'in:'.implode(',', array_keys(ProductDiscount::TYPES))],
            'starts_at' => ['required', 'date'],
            'ends_at'   => ['required', 'date', 'after:starts_at'],
            'note'      => ['nullable', 'string', 'max:300'],
            'stores'    => ['required', 'array', 'min:1'],
            'stores.*'  => ['exists:stores,id'],
        ], [
            'stores.required' => 'Pilih minimal satu toko — pengingat diskon harus tahu toko mana.',
            'ends_at.after'   => 'Waktu berakhir harus setelah waktu mulai.',
        ]);

        $discount = ProductDiscount::create(collect($data)->except('stores')->all());
        $discount->stores()->sync($data['stores']);

        return back()->with('ok', 'Pengingat diskon dicatat.');
    }

    public function destroy(ProductDiscount $discount)
    {
        $discount->delete(); // pivot ikut terhapus via cascade

        return back()->with('ok', 'Pengingat diskon dihapus.');
    }
}
