<?php

namespace App\Models;

use App\Enums\NotificationType;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ServiceTicket extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'branch_id', 'customer_name', 'customer_phone', 'customer_phone_alt',
        'device_brand', 'device_model', 'imei', 'device_passcode', 'complaint',
        'physical_condition', 'accessories', 'created_by', 'technician_id', 'admin_id',
        'diagnosis', 'estimated_cost', 'approved_cost', 'final_cost',
        'checked_in_at', 'estimated_done_at', 'warranty_days', 'parent_ticket_id',
        'cancel_reason', 'notes',
    ];

    protected $casts = [
        'status'             => TicketStatus::class,
        'physical_condition' => 'array',
        'accessories'        => 'array',
        'device_passcode'    => 'encrypted',
        'checked_in_at'      => 'datetime',
        'estimated_done_at'  => 'date',
        'completed_at'       => 'datetime',
        'notified_at'        => 'datetime',
        'checked_out_at'     => 'datetime',
        'warranty_until'     => 'date',
    ];

    /* -------------------- Relasi -------------------- */

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TicketStatusHistory::class, 'ticket_id')->orderBy('created_at');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(TicketPhoto::class, 'ticket_id');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(TicketPart::class, 'ticket_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(TicketNotification::class, 'ticket_id');
    }

    public function warrantyClaims(): HasMany
    {
        return $this->hasMany(self::class, 'parent_ticket_id');
    }

    public function parentTicket(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_ticket_id');
    }

    /* -------------------- Pembuatan tiket -------------------- */

    /** Format: SV-{KODE_CABANG}-{YYMM}-{URUT 4 digit per cabang per bulan}. */
    public static function generateTicketNumber(Branch $branch): string
    {
        $ym = now()->format('ym');
        $prefix = "SV-{$branch->code}-{$ym}-";

        // lockForUpdate mencegah nomor ganda saat 2 staf input bersamaan
        $last = self::withTrashed()
            ->where('ticket_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('ticket_number')
            ->value('ticket_number');

        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /** Buat tiket baru + history awal. Panggil di dalam transaction (sudah dibungkus di sini). */
    public static function open(array $attributes, User $creator): self
    {
        return DB::transaction(function () use ($attributes, $creator) {
            $branch = Branch::findOrFail($attributes['branch_id']);

            $ticket = new self($attributes);
            $ticket->ticket_number  = self::generateTicketNumber($branch);
            $ticket->tracking_token = Str::random(32);
            $ticket->status         = TicketStatus::Diterima;
            $ticket->created_by     = $creator->id;
            $ticket->checked_in_at  = $attributes['checked_in_at'] ?? now();
            $ticket->customer_phone = self::normalizePhone($attributes['customer_phone']);
            $ticket->save();

            $ticket->histories()->create([
                'from_status' => null,
                'to_status'   => TicketStatus::Diterima->value,
                'user_id'     => $creator->id,
                'note'        => 'Tiket dibuat',
                'created_at'  => $ticket->checked_in_at,
            ]);

            return $ticket;
        });
    }

    /* -------------------- Mesin transisi status -------------------- */

    public function transitionTo(TicketStatus $to, ?User $by = null, ?string $note = null): void
    {
        $from = $this->status;

        if (! in_array($to, $from->allowedTransitions(), true)) {
            throw new InvalidArgumentException(
                "Transisi {$from->label()} → {$to->label()} tidak diizinkan."
            );
        }

        DB::transaction(function () use ($from, $to, $by, $note) {
            $this->status = $to;

            match ($to) {
                TicketStatus::SiapDiambil => $this->completed_at = $this->completed_at ?? now(),
                TicketStatus::Selesai     => $this->applyCheckout(),
                default                   => null,
            };

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

    protected function applyCheckout(): void
    {
        $this->checked_out_at = now();

        if ($this->approved_cost !== null && $this->warranty_days > 0) {
            $this->warranty_until = now()->addDays($this->warranty_days)->toDateString();
        }
    }

    /* -------------------- Checklist "kabari customer" -------------------- */

    /**
     * Pengganti notif WA otomatis. Dipanggil manual dari UI saat staf sudah
     * benar-benar mengabari customer lewat chat internal.
     * notified_at HANYA diisi saat type Selesai — kolom ini yang dipakai
     * KpiService untuk hitung "jeda kabari customer".
     */
    public function markNotified(NotificationType $type, ?User $by = null): TicketNotification
    {
        $notification = $this->notifications()->firstOrCreate(
            ['type' => $type->value],
            ['user_id' => $by?->id, 'created_at' => now()]
        );

        if ($type === NotificationType::Selesai && $this->notified_at === null) {
            $this->forceFill(['notified_at' => $notification->created_at])->saveQuietly();
        }

        return $notification;
    }

    public function hasBeenNotified(NotificationType $type): bool
    {
        return $this->relationLoaded('notifications')
            ? $this->notifications->contains('type', $type)
            : $this->notifications()->where('type', $type->value)->exists();
    }

    /* -------------------- Helper -------------------- */

    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        return $digits;
    }

    public function trackingUrl(): string
    {
        return route('track.show', ['ticket' => $this->ticket_number, 't' => $this->tracking_token]);
    }

    public function isOpen(): bool
    {
        return $this->status !== TicketStatus::Selesai;
    }

    public function ageDays(): int
    {
        $end = $this->checked_out_at ?? now();

        return (int) $this->checked_in_at->diffInDays($end);
    }

    public function partsCost(): int
    {
        return (int) $this->parts->sum(fn ($p) => $p->cost * $p->qty);
    }
}