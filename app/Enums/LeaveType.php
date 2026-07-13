<?php

namespace App\Enums;

enum LeaveType: string
{
    case Izin  = 'izin';
    case Sakit = 'sakit';
    case Cuti  = 'cuti';

    public function label(): string
    {
        return match ($this) {
            self::Izin  => 'Izin Pribadi',
            self::Sakit => 'Sakit (surat dokter)',
            self::Cuti  => 'Cuti Tahunan',
        };
    }

    /**
     * Default dibayar/tidak — kebijakan Thomas:
     * sakit bersurat = dibayar, cuti = dibayar, izin pribadi = dipotong
     * (CEO bisa override khusus izin saat approve).
     */
    public function defaultPaid(): bool
    {
        return $this !== self::Izin;
    }
}