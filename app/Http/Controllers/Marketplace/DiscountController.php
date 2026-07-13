<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductDiscount;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    public function index()
    {
        return view('marketplace.discounts.index', [
            'discounts' => ProductDiscount::with('product')->orderBy('ends_at')->get(),
            'products'  => Product::whereNull('archived_at')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'name'       => ['required', 'string', 'max:150'],
            'starts_at'  => ['required', 'date'],
            'ends_at'    => ['required', 'date', 'after_or_equal:starts_at'],
            'note'       => ['nullable', 'string', 'max:300'],
        ]);

        ProductDiscount::create($data);

        return back()->with('ok', 'Pengingat diskon dicatat.');
    }

    public function destroy(ProductDiscount $discount)
    {
        $discount->delete(); // hard delete — ini pengingat, bukan data historis; M4 tidak memakainya

        return back()->with('ok', 'Pengingat diskon dihapus.');
    }
}
