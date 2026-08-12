<?php

namespace App\Models;

use App\Enums\WarrantyClaimFlow;
use App\Enums\WarrantyClaimStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    /** Batas isian manual "lainnya" biar kolom json gak dijadiin tempat curhat. */
    public const COMPLETENESS_OTHER_MAX = 6;

    /** SLA (keputusan #4): kuning 7 hari, merah 14 — dihitung dari aktivitas terakhir. */
    public const SLA_WARNING_DAYS  = 7;
    public const SLA_CRITICAL_DAYS = 14;

    public const OUTCOME_DITERIMA = 'diterima';
    public const OUTCOME_DITOLAK  = 'ditolak';

    protected $fillable = [
        'branch_id', 'customer_name', 'customer_phone',
        'product_id', 'imei', 'order_number', 'purchased_at',
        'completeness', 'reason', 'vendor_id', 'flow',
    ];

    protected $casts = [
        'status'              => WarrantyClaimStatus::class,
        'flow'                => WarrantyClaimFlow::class,
        'completeness'        => 'array',
        'purchased_at'        => 'date',
        'last_followed_up_at' => 'datetime',
        'last_activity_at'    => 'datetime',
        'completed_at'        => 'datetime',
        'picked_up_at'        => 'datetime',
        'supplier_queued_at'  => 'datetime',
        'supplier_skipped_at' => 'datetime',
        'last_notified_at'    => 'datetime',
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

    /** Riwayat unit ini di pengiriman ke supplier — bisa lebih dari satu kalau diklaim ulang. */
    public function supplierItems(): HasMany
    {
        return $this->hasMany(WarrantySupplierShipmentItem::class, 'claim_id');
    }

    public function latestSupplierItem(): HasOne
    {
        return $this->hasOne(WarrantySupplierShipmentItem::class, 'claim_id')->latestOfMany();
    }

    /* -------------------- Alur -------------------- */

    /** Tahap berikutnya menurut alur klaim ini. null = sudah ujung. */
    public function nextStatus(): ?WarrantyClaimStatus
    {
        return $this->status->next($this->flow);
    }

    /** Label tahap yang sudah disesuaikan alur (mis. "Sudah Diganti" utk tukar di tempat). */
    public function statusLabel(?WarrantyClaimStatus $status = null): string
    {
        return $this->flow->statusLabel($status ?? $this->status);
    }

    /**
     * Alur KirimDulu: tiga tahap supplier digerakkan oleh pengiriman, bukan
     * diketik ulang di sini. Kalau boleh dua-duanya, status klaim dan status
     * pengiriman bakal beda cerita dan tidak ada yang tahu mana yang benar.
     */
    public function isShipmentDriven(WarrantyClaimStatus $status): bool
    {
        return $this->flow === WarrantyClaimFlow::KirimDulu && in_array($status, [
            WarrantyClaimStatus::DikirimVendor,
            WarrantyClaimStatus::DicekVendor,
            WarrantyClaimStatus::HasilVendor,
        ], true);
    }

    /* -------------------- Kelengkapan -------------------- */

    /**
     * Isian manual "lainnya" → array, dipisah koma. Dibersihin dari spasi
     * ganda + duplikat, dibatasi COMPLETENESS_OTHER_MAX item.
     */
    public static function parseCompletenessOther(?string $raw): array
    {
        return collect(explode(',', (string) $raw))
            ->map(fn ($item) => Str::limit(trim(preg_replace('/\s+/', ' ', $item)), 40, ''))
            ->filter()
            ->unique()
            ->take(self::COMPLETENESS_OTHER_MAX)
            ->values()
            ->all();
    }

    /** Label siap tampil: item checklist di-humanize, isian manual apa adanya. */
    public function completenessLabels(): array
    {
        return collect($this->completeness)
            ->map(fn ($item) => in_array($item, self::COMPLETENESS_ITEMS, true)
                ? ucwords(str_replace('_', ' ', $item))
                : $item)
            ->all();
    }

    /**
     * Alur retur untuk sebuah produk — dibaca dari vendor brand-nya.
     *
     * Brand yang belum dipetakan ke vendor jatuh ke KirimDulu. Ini pilihan
     * sadar: lebih baik pelanggan menunggu daripada kita menalangi barang
     * pengganti untuk klaim yang ternyata tidak diakui supplier.
     */
    public static function flowFor(Product $product, bool $swapOnSpot = false): WarrantyClaimFlow
    {
        $vendor = $product->brand?->warrantyVendor;

        if (! $vendor || $vendor->requires_send_first) {
            return WarrantyClaimFlow::KirimDulu;
        }

        return $swapOnSpot ? WarrantyClaimFlow::TukarTempat : WarrantyClaimFlow::GantiDulu;
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
            // Alur di-SNAPSHOT di sini. Setelan vendor boleh berubah kapan saja,
            // tapi klaim yang sudah jalan tidak boleh ganti aturan main di tengah.
            $claim->flow           = $claim->flow ?? WarrantyClaimFlow::KirimDulu;
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
        $to   = $from->next($this->flow);

        if ($to === null) {
            throw new InvalidArgumentException("Klaim {$this->claim_number} sudah {$this->statusLabel()} — tidak bisa maju lagi.");
        }

        // Tahap yang digerakkan pengiriman cuma boleh lewat pengiriman.
        if ($this->isShipmentDriven($to) && empty($context['via_shipment'])) {
            throw new InvalidArgumentException(
                "Tahap {$to->label()} untuk klaim ini digerakkan dari Pengiriman Klaim Supplier — "
                .'masukkan unitnya ke pengiriman, jangan dimajukan dari sini.'
            );
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

            // Selesai lewat tukar-di-tempat tidak pernah singgah di SiapDiambil,
            // jadi completed_at diisi di sini juga — kalau tidak, rekap lama
            // proses bakal kosong buat seluruh alur itu.
            match ($to) {
                WarrantyClaimStatus::SiapDiambil => $this->completed_at = $this->completed_at ?? now(),
                WarrantyClaimStatus::Selesai     => [
                    $this->completed_at = $this->completed_at ?? now(),
                    $this->picked_up_at = now(),
                ],
                default => null,
            };

            $this->applySupplierQueueRule($to);

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

    /**
     * Kapan sebuah unit dianggap siap diklaim ke supplier — beda per alur:
     *
     *   KirimDulu   : begitu tim pusat selesai cek. Unitnya memang harus
     *                 berangkat ke supplier, itu bagian dari alur pelanggan.
     *   GantiDulu   : begitu hasil pengecekan keluar DAN hasilnya diterima.
     *                 Yang ditolak sengaja tidak masuk antrean — tapi masih
     *                 bisa dipaksa lewat queueForSupplier() kalau tim retur
     *                 menilai tetap layak diklaim.
     *   TukarTempat : TIDAK otomatis. Barangnya belum dicek siapa pun waktu
     *                 ditukar di depan pelanggan, jadi harus lewat pemeriksaan
     *                 tim pusat dulu (keputusan Thomas).
     */
    private function applySupplierQueueRule(WarrantyClaimStatus $to): void
    {
        if ($this->supplier_queued_at || $this->supplier_skipped_at) {
            return;
        }

        $ready = match ($this->flow) {
            WarrantyClaimFlow::KirimDulu => $to === WarrantyClaimStatus::DicekPusat,
            WarrantyClaimFlow::GantiDulu => $to === WarrantyClaimStatus::HasilVendor
                                            && ($this->outcome === self::OUTCOME_DITERIMA),
            WarrantyClaimFlow::TukarTempat => false,
        };

        if ($ready) {
            $this->supplier_queued_at = now();
        }
    }

    /* -------------------- Antrean klaim ke supplier -------------------- */

    /**
     * Nyatakan unit siap diklaim ke supplier. Dipakai dua keadaan: unit
     * tukar-di-tempat yang baru selesai dicek tim pusat, dan unit yang hasil
     * pengecekannya ditolak tapi tetap mau dicoba klaim.
     *
     * Stamp-nya di-set ulang tiap kali dipanggil — itu yang bikin unit yang
     * sudah pernah ditolak supplier bisa balik masuk antrean buat dicoba lagi.
     */
    public function queueForSupplier(User $by, ?string $note = null): void
    {
        DB::transaction(function () use ($by, $note) {
            $this->supplier_queued_at  = now();
            $this->supplier_skipped_at = null;
            $this->supplier_note       = $note;
            $this->last_activity_at    = now();
            $this->save();

            $this->histories()->create([
                'from_status' => $this->status->value,
                'to_status'   => null,
                'user_id'     => $by->id,
                'note'        => trim('Masuk antrean klaim ke supplier. '.$note),
                'created_at'  => now(),
            ]);
        });
    }

    /** Diputuskan TIDAK diklaim — ruginya ditanggung sendiri. Alasan wajib. */
    public function skipSupplierClaim(User $by, string $reason): void
    {
        DB::transaction(function () use ($by, $reason) {
            $this->supplier_skipped_at = now();
            $this->supplier_queued_at  = null;
            $this->supplier_note       = $reason;
            $this->last_activity_at    = now();
            $this->save();

            $this->histories()->create([
                'from_status' => $this->status->value,
                'to_status'   => null,
                'user_id'     => $by->id,
                'note'        => "Tidak diklaim ke supplier: {$reason}",
                'created_at'  => now(),
            ]);
        });
    }

    /**
     * Unit yang sudah siap diklaim tapi belum masuk pengiriman mana pun.
     *
     * Perbandingannya ke supplier_queued_at, bukan sekadar "belum punya item":
     * unit yang ditolak supplier lalu di-antre-kan ulang harus muncul lagi di
     * sini, sedangkan yang sudah masuk pengiriman jangan sampai dobel.
     */
    public function scopeAwaitingSupplierClaim(Builder $q): Builder
    {
        return $q->whereNotNull('supplier_queued_at')
            ->whereNull('supplier_skipped_at')
            ->whereNot('status', WarrantyClaimStatus::Batal)
            ->whereDoesntHave('supplierItems', fn ($i) => $i
                ->whereColumn('warranty_supplier_shipment_items.created_at', '>=', 'warranty_claims.supplier_queued_at'));
    }

    /**
     * Perlu diputuskan tim retur: mau diklaim ke supplier atau direlakan.
     * Muncul buat unit tukar-di-tempat (belum pernah dicek) dan unit yang
     * hasil pengecekannya ditolak.
     */
    public function needsSupplierDecision(): bool
    {
        if ($this->supplier_queued_at || $this->supplier_skipped_at || $this->status === WarrantyClaimStatus::Batal) {
            return false;
        }

        return match ($this->flow) {
            WarrantyClaimFlow::TukarTempat => true,
            WarrantyClaimFlow::GantiDulu   => $this->outcome === self::OUTCOME_DITOLAK,
            WarrantyClaimFlow::KirimDulu   => false,
        };
    }

    /* -------------------- Kabar ke pelanggan -------------------- */

    /**
     * Admin chat menandai pelanggan sudah dihubungi. Sengaja TIDAK memajukan
     * tahap dan TIDAK menyentuh SLA: mengabari itu bukan kemajuan barang, dan
     * kalau ikut me-reset timer, klaim mangkrak bisa disamarkan cuma dengan
     * rajin chat.
     */
    public function markNotified(User $by, ?string $note = null): void
    {
        DB::transaction(function () use ($by, $note) {
            $this->last_notified_at = now();
            $this->save();

            $this->histories()->create([
                'from_status' => $this->status->value,
                'to_status'   => null,
                'is_notify'   => true,
                'user_id'     => $by->id,
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
