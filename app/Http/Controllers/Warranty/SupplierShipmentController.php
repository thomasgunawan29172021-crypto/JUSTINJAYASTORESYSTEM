<?php

namespace App\Http\Controllers\Warranty;

use App\Enums\SupplierShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\WarrantyClaim;
use App\Models\WarrantySupplierShipment;
use App\Models\WarrantySupplierShipmentItem;
use App\Models\WarrantyVendor;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Klaim ke supplier — jalur INTERNAL, tidak pernah muncul di lacak pelanggan.
 * Semua aksi di sini menyangkut uang, jadi terkunci ke tim retur + CEO.
 */
class SupplierShipmentController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->canManageSupplierClaim(), 403);

        return view('warranty.supplier.index', [
            // Antrean = unit yang sudah dinyatakan siap tapi belum masuk
            // pengiriman mana pun. Ini yang menjaga klaim tidak menguap:
            // selama masih di sini, artinya duitnya belum dikejar.
            'queue' => WarrantyClaim::awaitingSupplierClaim()
                ->with(['product.brand.warrantyVendor', 'branch'])
                ->orderBy('supplier_queued_at')
                ->get(),

            'shipments' => WarrantySupplierShipment::with(['vendor', 'items'])
                ->withCount('items')
                ->when($request->filled('status'),
                    fn ($q) => $q->where('status', $request->string('status')->toString()))
                ->orderByRaw("status = 'selesai'")   // yang masih jalan di atas
                ->orderByDesc('created_at')
                ->paginate(20)
                ->withQueryString(),

            'vendors'  => WarrantyVendor::orderBy('name')->get(),
            'statuses' => SupplierShipmentStatus::cases(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->canManageSupplierClaim(), 403);

        $data = $request->validate([
            'vendor_id' => ['required', 'exists:warranty_vendors,id'],
            'claim_ids' => ['required', 'array', 'min:1'],
            'claim_ids.*' => ['integer', 'exists:warranty_claims,id'],
            'note'      => ['nullable', 'string', 'max:500'],
        ], [
            'claim_ids.required' => 'Centang dulu unit mana saja yang mau diklaim.',
        ]);

        try {
            $shipment = WarrantySupplierShipment::open(
                WarrantyVendor::findOrFail($data['vendor_id']),
                $data['claim_ids'],
                $request->user(),
                $data['note'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['shipment' => $e->getMessage()]);
        }

        return redirect()
            ->route('warranty.supplier.show', $shipment)
            ->with('ok', "Pengiriman {$shipment->shipment_number} dibuat. Surat tanda terima siap dicetak.");
    }

    public function show(Request $request, WarrantySupplierShipment $shipment)
    {
        abort_unless($request->user()->canManageSupplierClaim(), 403);

        $shipment->load(['vendor', 'creator', 'items.claim.product', 'items.claim.branch']);

        return view('warranty.supplier.show', ['shipment' => $shipment]);
    }

    /** Surat tanda terima buat diserahkan ke supplier bareng barangnya. */
    public function receipt(Request $request, WarrantySupplierShipment $shipment)
    {
        abort_unless($request->user()->canManageSupplierClaim(), 403);

        $shipment->load(['vendor', 'items.claim.product', 'items.claim.branch']);

        return view('warranty.supplier.receipt', ['shipment' => $shipment]);
    }

    public function advance(Request $request, WarrantySupplierShipment $shipment)
    {
        abort_unless($request->user()->canManageSupplierClaim(), 403);

        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        try {
            $shipment->advance($request->user(), $data['note'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['shipment' => $e->getMessage()]);
        }

        return back()->with('ok', 'Pengiriman maju ke: '.$shipment->fresh()->status->label());
    }

    public function followUp(Request $request, WarrantySupplierShipment $shipment)
    {
        abort_unless($request->user()->canManageSupplierClaim(), 403);

        $shipment->followUp($request->user());

        return back()->with('ok', 'Follow-up dicatat — timer SLA disetel ulang.');
    }

    /** Keluarkan unit dari pengiriman yang masih disusun (salah centang). */
    public function removeItem(Request $request, WarrantySupplierShipment $shipment, WarrantySupplierShipmentItem $item)
    {
        abort_unless($request->user()->canManageSupplierClaim(), 403);
        abort_unless($item->shipment_id === $shipment->id, 404);

        if (! $shipment->status->canEditItems()) {
            return back()->withErrors(['shipment' => 'Pengiriman sudah berangkat — isinya tidak bisa diubah lagi.']);
        }

        $item->delete();

        return back()->with('ok', 'Unit dikeluarkan dari pengiriman.');
    }

    /** Hasil supplier PER UNIT: ganti produk / ganti uang / ditolak. */
    public function resolveItem(Request $request, WarrantySupplierShipment $shipment, WarrantySupplierShipmentItem $item)
    {
        abort_unless($request->user()->canManageSupplierClaim(), 403);
        abort_unless($item->shipment_id === $shipment->id, 404);

        $data = $request->validate([
            'outcome'       => ['required', 'in:ganti_produk,ganti_uang,ditolak'],
            // required_if, bukan sekadar nullable: penggantian uang tanpa nominal
            // bikin rekap "duit belum cair" jadi bohong.
            'refund_amount' => ['nullable', 'required_if:outcome,ganti_uang', 'integer', 'min:1'],
            'outcome_note'  => ['nullable', 'string', 'max:1000'],
        ], [
            'refund_amount.required_if' => 'Nominal penggantian uang wajib diisi.',
        ]);

        try {
            $item->resolve(
                $request->user(),
                $data['outcome'],
                $data['refund_amount'] ?? null,
                $data['outcome_note'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['shipment' => $e->getMessage()]);
        }

        return back()->with('ok', 'Hasil supplier dicatat: '.WarrantySupplierShipmentItem::outcomeLabel($data['outcome']));
    }

    /** Unit pengganti sudah sampai gudang pusat — ujung jalur internal. */
    public function itemArrived(Request $request, WarrantySupplierShipment $shipment, WarrantySupplierShipmentItem $item)
    {
        abort_unless($request->user()->canManageSupplierClaim(), 403);
        abort_unless($item->shipment_id === $shipment->id, 404);

        try {
            $item->markArrivedAtWarehouse();
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['shipment' => $e->getMessage()]);
        }

        return back()->with('ok', 'Unit pengganti tercatat sampai gudang pusat.');
    }
}
