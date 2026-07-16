<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductDiscount extends Model
{
    public const TYPES = [
        'promo_toko'   => 'Promo Toko',
        'paket_diskon' => 'Paket Diskon',
        'kombo_hemat'  => 'Kombo Hemat',
    ];

    protected $fillable = ['name', 'type', 'starts_at', 'ends_at', 'note'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    /** withTrashed: toko yang dihapus tak boleh bikin tampilan diskon crash. */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'discount_store')->withTrashed();
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Guard isToday() SENGAJA dibuang — dulu perlu karena kolomnya date (tengah malam).
     * Sekarang datetime: berakhir 18:00 memang sudah lewat pada 19:00 hari yang sama.
     */
    public function hasEnded(): bool
    {
        return $this->ends_at->isPast();
    }

    public function endsSoon(int $days = 30): bool
    {
        return ! $this->hasEnded() && $this->ends_at->lte(now()->addDays($days));
    }

    public function isRunning(): bool
    {
        return ! $this->hasEnded() && $this->starts_at->isPast();
    }
}
