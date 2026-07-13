<?php

namespace App\Enums;

enum NotificationType: string
{
    case Dicek   = 'dicek';    // "kabari dicek" — unit mulai didiagnosa
    case Harga   = 'harga';    // "kabari harga" — estimasi biaya sudah disampaikan
    case Selesai = 'selesai';  // "kabari selesai" — unit siap diambil

    public function label(): string
    {
        return match ($this) {
            self::Dicek   => 'Kabari sedang dicek',
            self::Harga   => 'Kabari harga',
            self::Selesai => 'Kabari selesai',
        };
    }
}