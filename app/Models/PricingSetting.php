<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan harga global — singleton, SELALU 1 baris.
 * Barisnya udah di-seed migration; gak ada create/delete di UI, cuma update.
 *
 * tax_percent    = PPh Final, % dari omzet (default 0.5)
 * margin_percent = target untung bersih Thomas — untung ÷ HARGA JUAL, bukan ÷ modal.
 *                  Sengaja di-seed null: kalau dikasih angka default, Thomas bisa
 *                  kelewat dan ngira itu angka dia sendiri.
 */
class PricingSetting extends Model
{
    protected $fillable = ['tax_percent', 'margin_percent'];

    protected $casts = [
        'tax_percent'    => 'float',
        'margin_percent' => 'float',
    ];

    /** Ambil baris satu-satunya. Bikin kalau entah kenapa hilang. */
    public static function current(): self
    {
        return static::first() ?? static::create([
            'tax_percent'    => 0.50,
            'margin_percent' => null,
        ]);
    }
}
