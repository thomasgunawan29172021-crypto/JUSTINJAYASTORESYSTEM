<?php

namespace App\Enums;

enum LeaveType: string
{
    case Izin        = 'izin';
    case Sakit       = 'sakit';
    case Cuti        = 'cuti';
    case GantiJadwal = 'ganti_jadwal';

    public function label(): string
    {
        return match ($this) {
            self::Izin        => 'Izin Pribadi',
            self::Sakit       => 'Sakit (surat dokter)',
            self::Cuti        => 'Cuti Tahunan',
            self::GantiJadwal => 'Ganti Jadwal',
        };
    }

    /**
     * Default dibayar/tidak — kebijakan Thomas:
     * sakit bersurat = dibayar, cuti = dibayar, izin pribadi = dipotong
     * (CEO bisa override khusus izin saat approve).
     *
     * Ganti jadwal jatuh ke "dibayar" juga, tapi angka itu gak kepakai: resolver
     * absensi mem-filter ganti jadwal keluar dari jalur ketidakhadiran, jadi
     * is_paid-nya gak pernah dibaca buat orang yang cuma geser jam.
     */
    public function defaultPaid(): bool
    {
        return $this !== self::Izin;
    }

    /** Jenis yang berarti TIDAK HADIR. Ganti jadwal bukan — stafnya tetap masuk. */
    public function isAbsence(): bool
    {
        return $this !== self::GantiJadwal;
    }

    /** Butuh jam masuk/pulang — cuma ganti jadwal. */
    public function needsTime(): bool
    {
        return $this === self::GantiJadwal;
    }

    /** Nilai enum yang berarti TIDAK HADIR — dipakai resolver & cek bentrok. */
    public static function absenceValues(): array
    {
        return array_values(array_map(
            fn (self $c) => $c->value,
            array_filter(self::cases(), fn (self $c) => $c->isAbsence())
        ));
    }
}
