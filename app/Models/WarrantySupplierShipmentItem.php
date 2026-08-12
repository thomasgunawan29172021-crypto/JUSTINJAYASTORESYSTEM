<?php

namespace App\Models;

use App\Enums\WarrantyClaimFlow;
use App\Enums\WarrantyClaimStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Satu unit di dalam satu pengiriman ke supplier.
 *
 * Hasil diputuskan PER UNIT, bukan per pengiriman: satu kiriman berisi 8 unit
 * bisa saja 5 diganti barang, 2 diganti uang, 1 ditolak. Itu sebabnya tabel ini
 * ada, bukan sekadar kolom di pengirimannya.
 */
class WarrantySupplierShipmentItem extends Model
{
    public const OUTCOME_GANTI_PRODUK = 'ganti_produk';
    public const OUTCOME_GANTI_UANG   = 'ganti_uang';
    public const OUTCOME_DITOLAK      = 'ditolak';

    public const STATUS_MENUNGGU      = 'menunggu';
    public const STATUS_DIKIRIM_GUDANG = 'dikirim_gudang';
    public const STATUS_SELESAI       = 'selesai';

    protected $fillable = ['shipment_id', 'claim_id', 'outcome', 'refund_amount', 'outcome_note', 'status'];

    protected $casts = [
        'refund_amount' => 'integer',
        'resolved_at'   => 'datetime',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(WarrantySupplierShipment::class, 'shipment_id');
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(WarrantyClaim::class, 'claim_id');
    }

    public static function outcomeLabel(?string $outcome): string
    {
        return match ($outcome) {
            self::OUTCOME_GANTI_PRODUK => 'Penggantian Produk',
            self::OUTCOME_GANTI_UANG   => 'Penggantian Uang',
            self::OUTCOME_DITOLAK      => 'Ditolak Supplier',
            default                    => 'Menunggu hasil',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DIKIRIM_GUDANG => 'Menunggu sampai gudang pusat',
            self::STATUS_SELESAI        => 'Selesai',
            default                     => 'Menunggu hasil supplier',
        };
    }

    /**
     * Catat keputusan supplier untuk unit ini.
     *
     * Ekornya beda-beda, dan bukan cuma soal rapi — barangnya memang pergi ke
     * tempat berbeda:
     *   - ganti uang / ditolak → tidak ada barang bergerak, langsung selesai.
     *   - ganti produk, alur GantiDulu/TukarTempat → unit pengganti masuk ke
     *     GUDANG PUSAT (mengganti stok yang tadi dipakai menalangi pelanggan),
     *     jadi masih ada satu tahap lagi.
     *   - ganti produk, alur KirimDulu → unit pengganti pulang ke TOKO buat
     *     pelanggan yang masih menunggu, dan itu diurus oleh alur klaimnya
     *     sendiri (Dikirim Kembali ke Toko), jadi di sini langsung selesai.
     */
    public function resolve(User $by, string $outcome, ?int $refundAmount = null, ?string $note = null): void
    {
        if (! in_array($outcome, [self::OUTCOME_GANTI_PRODUK, self::OUTCOME_GANTI_UANG, self::OUTCOME_DITOLAK], true)) {
            throw new InvalidArgumentException('Hasil supplier tidak dikenal.');
        }

        if ($outcome === self::OUTCOME_GANTI_UANG && ! $refundAmount) {
            throw new InvalidArgumentException('Nominal penggantian uang wajib diisi.');
        }

        $claim = $this->claim;

        DB::transaction(function () use ($by, $outcome, $refundAmount, $note, $claim) {
            $this->outcome       = $outcome;
            $this->refund_amount = $outcome === self::OUTCOME_GANTI_UANG ? $refundAmount : null;
            $this->outcome_note  = $note;

            $needsWarehouse = $outcome === self::OUTCOME_GANTI_PRODUK
                && $claim?->flow?->supplierClaimIsInternal();

            $this->status = $needsWarehouse ? self::STATUS_DIKIRIM_GUDANG : self::STATUS_SELESAI;

            if (! $needsWarehouse) {
                $this->resolved_at = now();
            }

            $this->save();

            // Alur KirimDulu: hasil supplier ini SEKALIGUS hasil buat pelanggan
            // yang masih menunggu. Ganti barang maupun ganti uang dua-duanya
            // berarti klaimnya diakui; cuma penolakan yang jadi 'ditolak'.
            if ($claim && $claim->flow === WarrantyClaimFlow::KirimDulu
                && $claim->nextStatus() === WarrantyClaimStatus::HasilVendor) {
                $claim->advance($by, $note, [
                    'via_shipment' => true,
                    'outcome'      => $outcome === self::OUTCOME_DITOLAK
                        ? WarrantyClaim::OUTCOME_DITOLAK
                        : WarrantyClaim::OUTCOME_DITERIMA,
                    'outcome_note' => trim(self::outcomeLabel($outcome).'. '.$note),
                ]);
            }

            $this->shipment->closeIfAllResolved();
        });
    }

    /** Unit pengganti sudah sampai gudang pusat — ujung terakhir jalur internal. */
    public function markArrivedAtWarehouse(): void
    {
        if ($this->status !== self::STATUS_DIKIRIM_GUDANG) {
            throw new InvalidArgumentException('Unit ini tidak sedang menunggu kiriman ke gudang pusat.');
        }

        DB::transaction(function () {
            $this->status      = self::STATUS_SELESAI;
            $this->resolved_at = now();
            $this->save();

            $this->shipment->closeIfAllResolved();
        });
    }
}
