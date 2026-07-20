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
     */
    public function next(): ?self
    {
        return match ($this) {
            self::DiterimaCabang => self::DicekPusat,
            self::DicekPusat     => self::DikirimVendor,
            self::DikirimVendor  => self::DicekVendor,
            self::DicekVendor    => self::HasilVendor,
            self::HasilVendor    => self::DikirimBalik,
            self::DikirimBalik   => self::SiapDiambil,
            self::SiapDiambil    => self::Selesai,
            self::Selesai, self::Batal => null,
        };
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

    /** Urutan buat progress bar di halaman lacak publik (Batal gak masuk garis). */
    public static function timeline(): array
    {
        return [
            self::DiterimaCabang, self::DicekPusat, self::DikirimVendor,
            self::DicekVendor, self::HasilVendor, self::DikirimBalik,
            self::SiapDiambil, self::Selesai,
        ];
    }
}
