<?php

namespace App\Models;

use App\Enums\WarrantyClaimStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WarrantyClaim extends Model
{
    use SoftDeletes;

    /** Checklist kelengkapan (keputusan #7) — pola accessories di servis. */
    public const COMPLETENESS_ITEMS = [
        'dus', 'charger', 'kabel', 'headset', 'kartu_garansi', 'nota_pembelian',
    ];

    /** SLA (keputusan #4): kuning 7 hari, merah 14 — dihitung dari aktivitas terakhir. */
    public const SLA_WARNING_DAYS  = 7;
    public const SLA_CRITICAL_DAYS = 14;

    public const OUTCOME_DITERIMA = 'diterima';
    public const OUTCOME_DITOLAK  = 'ditolak';

    protected $fillable = [
        'branch_id', 'customer_name', 'customer_phone',
        'product_id', 'imei', 'order_number', 'purchased_at',
        'completeness', 'reason', 'vendor_id',
    ];

    protected $casts = [
        'status'              => WarrantyClaimStatus::class,
        'completeness'        => 'array',
        'purchased_at'        => 'date',
        'last_followed_up_at' => 'datetime',
        'last_activity_at'    => 'datetime',
        'completed_at'        => 'datetime',
        'picked_up_at'        => 'datetime',
    ];

    /* -------------------- Relasi -------------------- */

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** withTrashed: klaim harus tetap kebaca walau produknya diarsip/dihapus. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(WarrantyVendor::class, 'vendor_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(WarrantyClaimHistory::class, 'claim_id')->orderBy('created_at');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(WarrantyClaimPhoto::class, 'claim_id');
    }

    /* -------------------- Pembuatan -------------------- */

    /** Format RT-{CABANG}-{YYMM}-{urut} — beda prefix dari servis (keputusan #15). */
    public static function generateClaimNumber(Branch $branch): string
    {
        $ym = now()->format('ym');
        $prefix = "RT-{$branch->code}-{$ym}-";

        // lockForUpdate mencegah nomor ganda saat 2 staf input bersamaan — pola servis.
        $last = self::withTrashed()
            ->where('claim_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('claim_number')
            ->value('claim_number');

        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public static function open(array $attributes, User $creator): self
    {
        return DB::transaction(function () use ($attributes, $creator) {
            $branch = Branch::findOrFail($attributes['branch_id']);

            $claim = new self($attributes);
            $claim->claim_number   = self::generateClaimNumber($branch);
            $claim->tracking_token = Str::random(32);
            $claim->status         = WarrantyClaimStatus::DiterimaCabang;
            $claim->created_by     = $creator->id;
            $claim->customer_phone = ServiceTicket::normalizePhone($attributes['customer_phone']);
            $claim->last_activity_at = now();
            $claim->save();

            $claim->histories()->create([
                'from_status' => null,
                'to_status'   => WarrantyClaimStatus::DiterimaCabang->value,
                'user_id'     => $creator->id,
                'note'        => 'Barang diterima dari pelanggan',
                'created_at'  => now(),
            ]);

            return $claim;
        });
    }

    /* -------------------- Mesin transisi (URUT KETAT) -------------------- */

    /**
     * Maju SATU tahap. Gak ada loncat, gak ada mundur (keputusan Thomas).
     *
     * $context nampung syarat per tahap:
     *   - dikirim_vendor : WAJIB vendor_id (barang dikirim ke siapa?)
     *   - hasil_vendor   : WAJIB outcome diterima/ditolak (+ outcome_note opsional)
     */
    public function advance(?User $by = null, ?string $note = null, array $context = []): void
    {
        $from = $this->status;
        $to   = $from->next();

        if ($to === null) {
            throw new InvalidArgumentException("Klaim {$this->claim_number} sudah {$from->label()} — tidak bisa maju lagi.");
        }

        if ($to === WarrantyClaimStatus::DikirimVendor && empty($context['vendor_id'])) {
            throw new InvalidArgumentException('Pilih supplier/service center tujuan sebelum menandai barang dikirim.');
        }

        if ($to === WarrantyClaimStatus::HasilVendor) {
            $outcome = $context['outcome'] ?? null;

            if (! in_array($outcome, [self::OUTCOME_DITERIMA, self::OUTCOME_DITOLAK], true)) {
                throw new InvalidArgumentException('Hasil pengecekan wajib diisi: diterima atau ditolak.');
            }
        }

        DB::transaction(function () use ($from, $to, $by, $note, $context) {
            $this->status = $to;

            if ($to === WarrantyClaimStatus::DikirimVendor) {
                $this->vendor_id = (int) $context['vendor_id'];
            }

            if ($to === WarrantyClaimStatus::HasilVendor) {
                $this->outcome      = $context['outcome'];
                $this->outcome_note = $context['outcome_note'] ?? null;
            }

            match ($to) {
                WarrantyClaimStatus::SiapDiambil => $this->completed_at = $this->completed_at ?? now(),
                WarrantyClaimStatus::Selesai     => $this->picked_up_at = now(),
                default                          => null,
            };

            $this->last_activity_at = now();
            $this->save();

            $this->histories()->create([
                'from_status' => $from->value,
                'to_status'   => $to->value,
                'user_id'     => $by?->id,
                'note'        => $note,
                'created_at'  => now(),
            ]);
        });
    }

    /** Batal — cuma sebelum barang di tangan vendor (canCancel). Alasan wajib. */
    public function cancel(User $by, string $reason): void
    {
        if (! $this->status->canCancel()) {
            throw new InvalidArgumentException(
                "Klaim {$this->claim_number} sudah {$this->status->label()} — tidak bisa dibatalkan, tunggu hasil supplier."
            );
        }

        DB::transaction(function () use ($by, $reason) {
            $from = $this->status;
            $this->status        = WarrantyClaimStatus::Batal;
            $this->cancel_reason = $reason;
            $this->save();

            $this->histories()->create([
                'from_status' => $from->value,
                'to_status'   => WarrantyClaimStatus::Batal->value,
                'user_id'     => $by->id,
                'note'        => $reason,
                'created_at'  => now(),
            ]);
        });
    }

    /**
     * Follow-up (keputusan #4): catatan kejar ke supplier/ekspedisi TANPA majuin
     * tahap. Muncul di riwayat publik ("Telah di-follow up oleh {cabang}") dan
     * me-reset timer SLA.
     */
    public function followUp(User $by, ?string $note = null): void
    {
        if ($this->status->isFinal()) {
            throw new InvalidArgumentException('Klaim sudah selesai/batal — tidak ada yang perlu di-follow up.');
        }

        DB::transaction(function () use ($by, $note) {
            $this->last_followed_up_at = now();
            $this->last_activity_at    = now();
            $this->save();

            $this->histories()->create([
                'from_status' => $this->status->value,
                'to_status'   => null,
                'is_followup' => true,
                'user_id'     => $by->id,
                'note'        => $note,
                'created_at'  => now(),
            ]);
        });
    }

    /* -------------------- SLA -------------------- */

    /**
     * Umur macet = hari sejak AKTIVITAS PROSES terakhir (buka klaim, maju tahap,
     * atau follow-up) — BUKAN updated_at, yang berubah tiap edit data apa pun.
     * Fallback created_at cuma jaga-jaga buat baris lama tanpa stamp.
     */
    public function idleDays(): int
    {
        if ($this->status->isFinal()) {
            return 0;
        }

        return (int) ($this->last_activity_at ?? $this->created_at)->diffInDays(now());
    }

    /** 'ok' | 'warning' (≥7 hari) | 'critical' (≥14) — buat badge RAG di daftar. */
    public function slaLevel(): string
    {
        $days = $this->idleDays();

        return match (true) {
            $days >= self::SLA_CRITICAL_DAYS => 'critical',
            $days >= self::SLA_WARNING_DAYS  => 'warning',
            default                          => 'ok',
        };
    }

    /* -------------------- Publik -------------------- */

    public function trackingUrl(): string
    {
        return route('warranty.track.show', ['claim' => $this->claim_number, 't' => $this->tracking_token]);
    }
}
