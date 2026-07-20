<?php

namespace App\Enums;

enum UserRole: string
{
    case Ceo            = 'ceo';
    case KepalaToko     = 'kepala_toko';
    case KepalaKeuangan = 'kepala_keuangan';
    case Frontliner    = 'frontliner';
    case Teknisi       = 'teknisi';
    case AdminChat     = 'admin_chat';
    case AdminKeuangan = 'admin_keuangan';
    case Promotor      = 'promotor';
    case Posting       = 'posting';
    case Retur         = 'retur';
    case Gudang        = 'gudang';
    case Sosmed        = 'sosmed';

    public function label(): string
    {
        return match ($this) {
            self::Ceo            => 'CEO',
            self::KepalaToko     => 'Kepala Toko',
            self::KepalaKeuangan => 'Kepala Keuangan',
            self::Frontliner    => 'Frontliner',
            self::Teknisi       => 'Teknisi',
            self::AdminChat     => 'Admin Chat',
            self::AdminKeuangan => 'Admin Keuangan',
            self::Promotor      => 'Promotor',
            self::Posting       => 'Bagian Posting',
            self::Retur         => 'Bagian Returan',
            self::Gudang        => 'Bagian Gudang',
            self::Sosmed        => 'Bagian Sosmed',
        };
    }

    public function isCeo(): bool
    {
        return $this === self::Ceo;
    }

    /** Akses penuh lintas fungsi — dipakai nanti pas permission diketatin. */
    public function isManager(): bool
    {
        return in_array($this, [self::Ceo, self::KepalaToko], true);
    }

    /** Akses modul keuangan (payroll, dst) — CEO + Kepala Keuangan. */
    public function canAccessFinance(): bool
    {
        return in_array($this, [self::Ceo, self::KepalaKeuangan], true);
    }

    /** Kelola modul sosmed — CEO + PIC Sosmed. */
    public function canManageSosmed(): bool
    {
        return in_array($this, [self::Ceo, self::Sosmed], true);
    }

    /** Akses modul Service — role operasional toko + CEO (keputusan Thomas). */
    public function canAccessService(): bool
    {
        return in_array($this, [
            self::Ceo, self::KepalaToko, self::Frontliner, self::Teknisi, self::AdminChat,
        ], true);
    }

    /** Modul klaim garansi/retur: input klaim = frontliner dkk; proses = tim retur. */
    public function canCreateWarrantyClaim(): bool
    {
        // Frontliner nerima barang dari pelanggan (keputusan Thomas #10),
        // role servis lain + retur juga boleh input.
        return $this->canAccessService() || $this === self::Retur;
    }

    public function canProcessWarrantyClaim(): bool
    {
        // Majuin tahap, follow up, isi hasil vendor — cuma tim retur + CEO.
        return in_array($this, [self::Ceo, self::Retur], true);
    }
}