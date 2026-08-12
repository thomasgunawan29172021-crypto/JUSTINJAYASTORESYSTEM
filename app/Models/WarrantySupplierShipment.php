<?php

namespace App\Models;

use App\Enums\SupplierShipmentStatus;
use App\Enums\WarrantyClaimFlow;
use App\Enums\WarrantyClaimStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Satu kali kirim ke satu vendor, isinya banyak unit sekaligus — karena
 * nyatanya unit dikumpulkan dulu baru dikirim borongan ke distributor.
 *
 * Ini jalur INTERNAL. Halaman lacak pelanggan tidak pernah menyentuh tabel ini.
 * Untuk klaim alur KirimDulu, pengiriman inilah yang menggerakkan tahap
 * klaimnya (dikirim → dicek → hasil), supaya tidak ada dua pembukuan.
 */
class WarrantySupplierShipment extends Model
{
    use SoftDeletes;

    /** SLA klaim ke supplier — sama takarannya dengan klaim retur biasa. */
    public const SLA_WARNING_DAYS  = 7;
    public const SLA_CRITICAL_DAYS = 14;

    protected $fillable = ['vendor_id', 'note'];

    protected $casts = [
        'status'              => SupplierShipmentStatus::class,
        'shipped_at'          => 'datetime',
        'checked_at'          => 'datetime',
        'completed_at'        => 'datetime',
        'last_followed_up_at' => 'datetime',
        'last_activity_at'    => 'datetime',
    ];

    /* -------------------- Relasi -------------------- */

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(WarrantyVendor::class, 'vendor_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WarrantySupplierShipmentItem::class, 'shipment_id');
    }

    /* -------------------- Pembuatan -------------------- */

    /** KS-{YYMM}-{urut} — "KS" = Klaim Supplier, sengaja beda dari RT-nya retur. */
    public static function generateNumber(): string
    {
        $prefix = 'KS-'.now()->format('ym').'-';

        // lockForUpdate: pola yang sama dengan nomor klaim & tiket servis.
        $last = self::withTrashed()
            ->where('shipment_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('shipment_number')
            ->value('shipment_number');

        return $prefix.str_pad((string) (($last ? (int) substr($last, -3) : 0) + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Bikin pengiriman berisi klaim-klaim terpilih. Klaim yang tidak layak
     * (sudah masuk pengiriman lain, dibatalkan, belum masuk antrean) ditolak
     * di sini — bukan disaring diam-diam, karena kalau unit hilang dari daftar
     * tanpa penjelasan orang bakal mengira sudah terkirim.
     */
    public static function open(WarrantyVendor $vendor, array $claimIds, User $creator, ?string $note = null): self
    {
        if (empty($claimIds)) {
            throw new InvalidArgumentException('Pilih minimal satu unit yang mau diklaim.');
        }

        return DB::transaction(function () use ($vendor, $claimIds, $creator, $note) {
            $claims = WarrantyClaim::awaitingSupplierClaim()->whereIn('id', $claimIds)->get();

            if ($claims->count() !== count(array_unique($claimIds))) {
                throw new InvalidArgumentException(
                    'Ada unit yang sudah tidak bisa diklaim — mungkin baru saja dimasukkan ke pengiriman lain. Muat ulang halamannya.'
                );
            }

            $shipment = new self(['vendor_id' => $vendor->id, 'note' => $note]);
            $shipment->shipment_number  = self::generateNumber();
            $shipment->status           = SupplierShipmentStatus::Draft;
            $shipment->created_by       = $creator->id;
            $shipment->last_activity_at = now();
            $shipment->save();

            foreach ($claims as $claim) {
                $shipment->items()->create(['claim_id' => $claim->id]);
            }

            return $shipment;
        });
    }

    /* -------------------- Mesin transisi -------------------- */

    /**
     * Maju satu tahap. Klaim alur KirimDulu di dalamnya ikut maju — itu inti
     * dari "tidak ada input dua kali": tandai sekali di pengiriman, tahap
     * pelanggannya bergerak sendiri.
     */
    public function advance(User $by, ?string $note = null): void
    {
        $from = $this->status;
        $to   = $from->next();

        if ($to === null) {
            throw new InvalidArgumentException("Pengiriman {$this->shipment_number} sudah {$from->label()}.");
        }

        if ($to === SupplierShipmentStatus::Dikirim && $this->items()->count() === 0) {
            throw new InvalidArgumentException('Pengiriman masih kosong — masukkan unitnya dulu.');
        }

        DB::transaction(function () use ($to, $by, $note) {
            $this->status = $to;

            match ($to) {
                SupplierShipmentStatus::Dikirim => $this->shipped_at = now(),
                SupplierShipmentStatus::Dicek   => $this->checked_at = now(),
                default                         => null,
            };

            $this->last_activity_at = now();
            $this->save();

            // Tahap klaim yang setara di sisi pelanggan.
            $claimStatus = match ($to) {
                SupplierShipmentStatus::Dikirim => WarrantyClaimStatus::DikirimVendor,
                SupplierShipmentStatus::Dicek   => WarrantyClaimStatus::DicekVendor,
                default                         => null,
            };

            if ($claimStatus) {
                $this->pushClaims($claimStatus, $by, $note);
            }
        });
    }

    /**
     * Majukan klaim KirimDulu di pengiriman ini ke $target.
     *
     * Klaim alur lain SENGAJA dilewat: pelanggannya sudah pegang barang
     * pengganti, urusan supplier ini bukan lagi bagian dari perjalanan dia.
     */
    private function pushClaims(WarrantyClaimStatus $target, User $by, ?string $note): void
    {
        $claims = $this->items()->with('claim')->get()
            ->pluck('claim')
            ->filter(fn ($c) => $c && $c->flow === WarrantyClaimFlow::KirimDulu);

        foreach ($claims as $claim) {
            // Sudah di tahap itu atau lebih jauh → lewati, jangan bikin error
            // yang menggagalkan seluruh pengiriman gara-gara satu unit.
            if ($claim->nextStatus() !== $target) {
                continue;
            }

            $claim->advance($by, $note, [
                'via_shipment' => true,
                'vendor_id'    => $this->vendor_id,
            ]);
        }
    }

    /**
     * Tutup pengiriman kalau semua unitnya sudah kelar. Dipanggil tiap kali
     * satu unit selesai — pengiriman tidak pernah ditutup manual, supaya tidak
     * ada yang dinyatakan beres padahal masih ada unit menggantung.
     */
    public function closeIfAllResolved(): void
    {
        if ($this->status === SupplierShipmentStatus::Selesai) {
            return;
        }

        if ($this->items()->where('status', '!=', WarrantySupplierShipmentItem::STATUS_SELESAI)->exists()) {
            return;
        }

        $this->status           = SupplierShipmentStatus::Selesai;
        $this->completed_at     = now();
        $this->last_activity_at = now();
        $this->save();
    }

    public function followUp(User $by, ?string $note = null): void
    {
        $this->last_followed_up_at = now();
        $this->last_activity_at    = now();
        $this->save();
    }

    /* -------------------- SLA & rekap -------------------- */

    public function idleDays(): int
    {
        if ($this->status === SupplierShipmentStatus::Selesai) {
            return 0;
        }

        return (int) ($this->last_activity_at ?? $this->created_at)->diffInDays(now());
    }

    public function slaLevel(): string
    {
        return match (true) {
            $this->idleDays() >= self::SLA_CRITICAL_DAYS => 'critical',
            $this->idleDays() >= self::SLA_WARNING_DAYS  => 'warning',
            default                                      => 'ok',
        };
    }

    /** Total rupiah yang dijanjikan supplier tapi belum tentu sudah cair. */
    public function refundTotal(): int
    {
        return (int) $this->items->sum('refund_amount');
    }
}
