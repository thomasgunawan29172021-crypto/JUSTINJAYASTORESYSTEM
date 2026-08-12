<?php

namespace App\Http\Controllers\Warranty;

use App\Enums\WarrantyClaimStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Product;
use App\Models\WarrantyClaim;
use App\Models\WarrantyClaimPhoto;
use App\Models\WarrantyVendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WarrantyClaimController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->canCreateWarrantyClaim()
            || $request->user()->canProcessWarrantyClaim(), 403);

        $claims = WarrantyClaim::query()
            ->with(['branch', 'product', 'vendor'])
            // ->toString() bukan gaya-gayaan: Stringable yang masuk binding query
            // bisa gagal dikonversi di PDO. Pola yang sama dipakai TaskController.
            ->when($request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('q'), function ($q) use ($request) {
                $s = trim($request->string('q')->toString());
                $q->where(fn ($qq) => $qq
                    ->where('claim_number', 'like', "%{$s}%")
                    ->orWhere('customer_name', 'like', "%{$s}%")
                    ->orWhere('customer_phone', 'like', '%'.preg_replace('/\D+/', '', $s).'%')
                    ->orWhere('imei', 'like', "%{$s}%"));
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('warranty.claims.index', [
            'claims'   => $claims,
            'statuses' => WarrantyClaimStatus::cases(),
        ]);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->canCreateWarrantyClaim(), 403);

        return view('warranty.claims.create', [
            'branches' => Branch::all(),
            // brand.warrantyVendor WAJIB ikut eager load: blade baca alur retur
            // tiap produk buat nentuin apakah pilihan "tukar di tempat" boleh
            // muncul. Tanpa ini, daftar produk = satu query per baris.
            'products' => Product::with('brand.warrantyVendor')
                ->whereNull('archived_at')
                // Bundle ikut tampil — bundle juga barang yang bisa diretur.
                ->orderBy('name')
                ->get(['id', 'name', 'brand_id']),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->canCreateWarrantyClaim(), 403);

        $data = $request->validate([
            'branch_id'      => ['required', 'exists:branches,id'],
            'customer_name'  => ['required', 'string', 'max:100'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'product_id'     => ['required', 'exists:products,id'],
            'imei'           => ['nullable', 'string', 'max:50'],
            'order_number'   => ['nullable', 'string', 'max:100'],
            'purchased_at'   => ['nullable', 'date', 'before_or_equal:today'],
            'completeness'   => ['nullable', 'array'],
            'completeness.*' => ['string', 'in:'.implode(',', WarrantyClaim::COMPLETENESS_ITEMS)],
            // Kelengkapan di luar checklist — diketik bebas, dipisah koma.
            'completeness_other' => ['nullable', 'string', 'max:255'],
            // Tukar langsung di cabang. Cuma sah kalau brand produknya memang
            // boleh diganti duluan — divalidasi ulang di bawah, jangan percaya
            // centang dari browser.
            'swap_on_spot'   => ['nullable', 'boolean'],
            'reason'         => ['required', 'string', 'max:1000'],
            // Foto barang segala sisi (keputusan #9) — minimal 1 biar ada bukti kondisi awal.
            'photos'         => ['required', 'array', 'min:1', 'max:8'],
            'photos.*'       => ['image', 'max:4096'],
        ], [
            'photos.required'   => 'Minimal 1 foto barang wajib diunggah sebagai bukti kondisi awal.',
            // 'uploaded' = ditolak PHP sebelum validasi, hampir selalu upload_max_filesize.
            'photos.*.uploaded' => 'Foto terlalu besar buat server (batas '.ini_get('upload_max_filesize').' per file). Pilih ulang fotonya — sistem otomatis mengecilkan.',
            'photos.*.max'      => 'Ukuran foto maksimal 4 MB per file.',
        ]);

        // Checklist + isian manual disimpan bareng di satu kolom json.
        $data['completeness'] = array_merge(
            $data['completeness'] ?? [],
            WarrantyClaim::parseCompletenessOther($data['completeness_other'] ?? null),
        );
        unset($data['completeness_other']);

        // Alur ditentukan SERVER dari brand produknya, bukan dari kiriman form.
        // flowFor() sendiri yang mengabaikan swap_on_spot kalau brand-nya
        // ternyata wajib kirim dahulu.
        $product = Product::with('brand.warrantyVendor')->findOrFail($data['product_id']);
        $flow    = WarrantyClaim::flowFor($product, (bool) ($data['swap_on_spot'] ?? false));
        unset($data['swap_on_spot']);
        $data['flow'] = $flow->value;

        $claim = WarrantyClaim::open($data, $request->user());

        foreach ($request->file('photos', []) as $photo) {
            $claim->photos()->create([
                'type'        => WarrantyClaimPhoto::TYPE_INTAKE,
                'path'        => $photo->store("warranty/{$claim->id}", config('filesystems.default')),
                'uploaded_by' => $request->user()->id,
            ]);
        }

        return redirect()
            ->route('warranty.claims.show', $claim)
            ->with('ok', "Klaim {$claim->claim_number} dibuat. Nota siap dicetak.");
    }

    public function show(Request $request, WarrantyClaim $claim)
    {
        abort_unless($request->user()->canCreateWarrantyClaim()
            || $request->user()->canProcessWarrantyClaim(), 403);

        $claim->load([
            'branch', 'product', 'vendor', 'creator', 'histories.user', 'photos',
            'supplierItems.shipment.vendor',
        ]);

        return view('warranty.claims.show', [
            'claim'   => $claim,
            'vendors' => WarrantyVendor::orderBy('name')->get(),
        ]);
    }

    /**
     * Maju SATU tahap — mesin di model yang jaga urutan, controller cuma nyalurin.
     *
     * Izinnya dua lapis: tahap yang terjadi di cabang (dikirim balik ke toko,
     * siap diambil, sudah diambil) boleh frontliner, sisanya tim retur.
     */
    public function advance(Request $request, WarrantyClaim $claim)
    {
        $next = $claim->nextStatus();

        abort_unless($next && $next->isBranchStage()
            ? $request->user()->canHandoverWarrantyClaim()
            : $request->user()->canProcessWarrantyClaim(), 403);

        $data = $request->validate([
            'note'            => ['nullable', 'string', 'max:500'],
            'vendor_id'       => ['nullable', 'exists:warranty_vendors,id'],
            'outcome'         => ['nullable', 'in:diterima,ditolak'],
            'outcome_note'    => ['nullable', 'string', 'max:1000'],
            // Bukti pengiriman (resi) — opsional, relevan pas kirim ke vendor / kirim balik.
            'shipping_photos'   => ['nullable', 'array', 'max:4'],
            'shipping_photos.*' => ['image', 'max:4096'],
        ]);

        try {
            $claim->advance($request->user(), $data['note'] ?? null, [
                'vendor_id'    => $data['vendor_id'] ?? null,
                'outcome'      => $data['outcome'] ?? null,
                'outcome_note' => $data['outcome_note'] ?? null,
            ]);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        foreach ($request->file('shipping_photos', []) as $photo) {
            $claim->photos()->create([
                'type'        => WarrantyClaimPhoto::TYPE_SHIPPING,
                'path'        => $photo->store("warranty/{$claim->id}", config('filesystems.default')),
                'uploaded_by' => $request->user()->id,
            ]);
        }

        return back()->with('ok', 'Status maju ke: '.$claim->fresh()->statusLabel());
    }

    /**
     * Tim retur memutuskan nasib klaim ke supplier: masuk antrean atau
     * direlakan. Dipakai unit tukar-di-tempat (yang belum pernah dicek) dan
     * unit yang hasil pengecekannya ditolak tapi tetap mau dicoba klaim.
     */
    public function supplierDecision(Request $request, WarrantyClaim $claim)
    {
        abort_unless($request->user()->canManageSupplierClaim(), 403);

        $data = $request->validate([
            'decision' => ['required', 'in:klaim,lepas'],
            'note'     => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['decision'] === 'lepas') {
            if (blank($data['note'] ?? null)) {
                return back()->withErrors(['supplier' => 'Alasan tidak diklaim wajib diisi — ini rugi yang kita tanggung sendiri.']);
            }

            $claim->skipSupplierClaim($request->user(), $data['note']);

            return back()->with('ok', 'Unit ditandai tidak diklaim ke supplier.');
        }

        $claim->queueForSupplier($request->user(), $data['note'] ?? null);

        return back()->with('ok', 'Unit masuk antrean klaim ke supplier.');
    }

    /** Admin chat menandai pelanggan sudah dikabari — tidak memajukan tahap. */
    public function notify(Request $request, WarrantyClaim $claim)
    {
        abort_unless($request->user()->canNotifyWarrantyCustomer(), 403);

        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        $claim->markNotified($request->user(), $data['note'] ?? null);

        return back()->with('ok', 'Ditandai sudah dikabari ke pelanggan.');
    }

    public function cancel(Request $request, WarrantyClaim $claim)
    {
        abort_unless($request->user()->canProcessWarrantyClaim(), 403);

        $data = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:500'],
        ], [
            'cancel_reason.required' => 'Alasan pembatalan wajib diisi.',
        ]);

        try {
            $claim->cancel($request->user(), $data['cancel_reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('ok', 'Klaim dibatalkan.');
    }

    public function followUp(Request $request, WarrantyClaim $claim)
    {
        abort_unless($request->user()->canProcessWarrantyClaim(), 403);

        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        try {
            $claim->followUp($request->user(), $data['note'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('ok', 'Follow-up dicatat — pelanggan bisa lihat di halaman lacak.');
    }

    /** Nota tanda terima (keputusan #13) — berisi QR ke halaman lacak, kembaran servis. */
    public function receipt(Request $request, WarrantyClaim $claim)
    {
        abort_unless($request->user()->canCreateWarrantyClaim()
            || $request->user()->canProcessWarrantyClaim(), 403);

        $claim->load(['branch', 'product', 'creator', 'photos']);

        return view('warranty.claims.receipt', ['claim' => $claim]);
    }

    /** Serve foto lewat controller — aman apa pun FILESYSTEM_DISK-nya, dan tetap di balik login. */
    public function photo(Request $request, WarrantyClaim $claim, WarrantyClaimPhoto $photo)
    {
        abort_unless($request->user()->canCreateWarrantyClaim()
            || $request->user()->canProcessWarrantyClaim(), 403);
        // Cast int: foreign key gak otomatis di-cast Eloquent, dan driver DB bisa
        // balikin string. "5" === 5 itu false → foto sah malah 404. Fail-safe, tapi rusak.
        abort_unless((int) $photo->claim_id === (int) $claim->id, 404);

        return Storage::disk(config('filesystems.default'))->response($photo->path);
    }
}
