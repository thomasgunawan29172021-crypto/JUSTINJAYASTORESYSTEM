<?php

namespace App\Http\Controllers\Warranty;

use App\Http\Controllers\Controller;
use App\Models\WarrantyVendor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Master vendor (distributor/supplier/SC) — dikelola tim retur + CEO. */
class WarrantyVendorController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->role->canProcessWarrantyClaim(), 403);

        return view('warranty.vendors.index', [
            'vendors' => WarrantyVendor::withCount('claims')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->role->canProcessWarrantyClaim(), 403);

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:100', 'unique:warranty_vendors,name'],
            'phone' => ['nullable', 'string', 'max:30'],
            'note'  => ['nullable', 'string', 'max:500'],
        ]);

        WarrantyVendor::create($data);

        return back()->with('ok', "Vendor {$data['name']} ditambahkan.");
    }

    public function update(Request $request, WarrantyVendor $vendor)
    {
        abort_unless($request->user()->role->canProcessWarrantyClaim(), 403);

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:100', Rule::unique('warranty_vendors', 'name')->ignore($vendor->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'note'  => ['nullable', 'string', 'max:500'],
        ]);

        $vendor->update($data);

        return back()->with('ok', 'Vendor diperbarui.');
    }

    public function destroy(Request $request, WarrantyVendor $vendor)
    {
        abort_unless($request->user()->role->canProcessWarrantyClaim(), 403);

        // Soft delete — klaim lama tetap bisa nampilin nama vendor (relasi withTrashed).
        // Vendor dengan klaim AKTIF jangan dihapus: tim bakal kehilangan tujuan kirim.
        $active = $vendor->claims()
            ->whereNotIn('status', ['selesai', 'batal'])
            ->count();

        if ($active > 0) {
            return back()->withErrors(['vendor' => "Vendor masih punya {$active} klaim berjalan — selesaikan dulu."]);
        }

        $vendor->delete();

        return back()->with('ok', 'Vendor dihapus.');
    }
}
