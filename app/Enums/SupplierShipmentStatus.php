<?php

namespace App\Enums;

/**
 * Tahap PENGIRIMAN klaim ke supplier — jalur internal, tidak pernah muncul di
 * halaman lacak pelanggan.
 *
 * Selesai SENGAJA tidak ada di next(): pengiriman tidak ditutup manual, dia
 * menutup dirinya sendiri begitu semua unit di dalamnya kelar. Kalau boleh
 * ditutup manual, bakal ada pengiriman yang dinyatakan selesai padahal masih
 * ada unit yang belum jelas nasibnya — itu persis duit yang hilang diam-diam.
 */
enum SupplierShipmentStatus: string
{
    case Draft   = 'draft';
    case Dikirim = 'dikirim';
    case Dicek   = 'dicek';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::Draft   => 'Disusun',
            self::Dikirim => 'Dikirim ke Supplier',
            self::Dicek   => 'Dicek Supplier',
            self::Selesai => 'Selesai',
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::Draft   => self::Dikirim,
            self::Dikirim => self::Dicek,
            self::Dicek, self::Selesai => null,
        };
    }

    /** Isi pengiriman masih boleh diubah selama belum berangkat. */
    public function canEditItems(): bool
    {
        return $this === self::Draft;
    }

    public static function timeline(): array
    {
        return [self::Draft, self::Dikirim, self::Dicek, self::Selesai];
    }
}
