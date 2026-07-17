<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Potongan marketplace untuk satu kombinasi (marketplace × tier toko × kategori).
 * SEMUANYA persen dari harga jual — ongkir nominal udah dibuang (Fase 3.5).
 *
 * marketplace & tier disimpan sebagai string, nilainya DISALIN dari kolom yang sama
 * di `stores` — bukan diketik ulang. Sekali beda huruf, lookup meleset diam-diam.
 */
class MarketplaceCategoryFee extends Model
{
    /** field => label buat pesan error. Urutannya = urutan kolom di form. */
    public const PERCENT_FIELDS = [
        'admin_percent'          => 'biaya admin',
        'program_ongkir_percent' => 'program gratis ongkir',
        'program_diskon_percent' => 'program diskon',
        'program_ekstra_percent' => 'program ekstra diskon',
    ];

    protected $fillable = [
        'marketplace', 'tier', 'category_id',
        'admin_percent', 'program_ongkir_percent', 'program_diskon_percent', 'program_ekstra_percent',
    ];

    /**
     * 'float', BUKAN 'decimal:2' — cast decimal Laravel ngembaliin STRING dan itu
     * bikin pengecekan di calculator rawan. null tetap null (cast di-skip untuk null),
     * dan itu WAJIB: null = "belum diisi", 0 = "gak ikut program / gratis".
     */
    protected $casts = [
        'admin_percent'          => 'float',
        'program_ongkir_percent' => 'float',
        'program_diskon_percent' => 'float',
        'program_ekstra_percent' => 'float',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Field yang belum diisi — dipakai calculator buat nyebut persis apa yang kurang. */
    public function missingFields(): array
    {
        return array_values(array_filter(
            self::PERCENT_FIELDS,
            fn (string $field) => $this->$field === null,
            ARRAY_FILTER_USE_KEY
        ));
    }

    public function isComplete(): bool
    {
        return $this->missingFields() === [];
    }

    /** Total potongan marketplace (%) — admin + 3 program. */
    public function totalPercent(): float
    {
        return array_sum(array_map(
            fn (string $field) => (float) $this->$field,
            array_keys(self::PERCENT_FIELDS)
        ));
    }
}
