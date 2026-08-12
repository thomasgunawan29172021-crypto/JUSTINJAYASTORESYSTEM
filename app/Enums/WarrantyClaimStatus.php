<?php

namespace App\Enums;

enum WarrantyClaimStatus: string
{
    case DiterimaCabang = 'diterima_cabang';
    case DicekPusat     = 'dicek_pusat';
    case DikirimVendor  = 'dikirim_vendor';
    case DicekVendor    = 'dicek_vendor';
    case HasilVendor    = 'hasil_vendor';
    case DikirimBalik   = 'dikirim_balik';
    case SiapDiambil    = 'siap_diambil';
    case Selesai        = 'selesai';
    case Batal          = 'batal';

    public function label(): string
    {
        return match ($this) {
            self::DiterimaCabang => 'Diterima Cabang',
            self::DicekPusat     => 'Dicek Tim Pusat',
            self::DikirimVendor  => 'Dikirim ke Supplier/Service Center',
            self::DicekVendor    => 'Dicek Supplier/Service Center',
            self::HasilVendor    => 'Hasil Pengecekan Keluar',
            self::DikirimBalik   => 'Dikirim Kembali ke Toko',
            self::SiapDiambil    => 'Siap Diambil',
            self::Selesai        => 'Sudah Diambil',
            self::Batal          => 'Dibatalkan',
        };
    }

    /**
     * URUT KETAT (keputusan Thomas): cuma maju +1, gak boleh loncat/mundur.
     * Beda dari TicketStatus servis yang bebas. Batal ditangani terpisah
     * (canCancel) karena aturannya beda: boleh dari beberapa tahap sekaligus.
     *
     * Urutannya SEKARANG tergantung alur (Agustus 2026): Robot/Olike melewati
     * dua tahap supplier, tukar-di-tempat cuma dua tahap. Karena itu urutan
     * pindah ke WarrantyClaimFlow::timeline() — satu tempat, dipakai bareng
     * oleh mesin transisi, progress bar internal, dan lacak publik.
     */
    public function next(WarrantyClaimFlow $flow): ?self
    {
        return $flow->next($this);
    }

    /**
     * Batal HANYA sebelum barang sampai supplier (keputusan #1) — begitu udah di
     * tangan vendor, satu-satunya jalan keluar adalah hasil vendor (diterima/ditolak).
     * DikirimVendor masih boleh batal: barangnya di jalan, belum diterima vendor.
     */
    public function canCancel(): bool
    {
        return in_array($this, [
            self::DiterimaCabang, self::DicekPusat, self::DikirimVendor,
        ], true);
    }

    public function isFinal(): bool
    {
        return $this === self::Selesai || $this === self::Batal;
    }

    /**
     * Tahap yang boleh dijalankan orang cabang (keputusan Thomas): frontliner
     * yang berhadapan langsung sama pelanggan, jadi dia yang paling tahu kapan
     * barang benar-benar sampai toko dan benar-benar diambil. Tahap tengah
     * (kirim supplier, hasil pengecekan) tetap milik tim retur.
     */
    public function isBranchStage(): bool
    {
        return in_array($this, [self::DikirimBalik, self::SiapDiambil, self::Selesai], true);
    }
}
