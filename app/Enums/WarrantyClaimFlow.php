<?php

namespace App\Enums;

/**
 * Alur retur yang DILIHAT PELANGGAN. Tiga varian, ditentukan dari vendor
 * brand produknya (lihat WarrantyVendor::$requires_send_first).
 *
 * Yang membedakan cuma satu pertanyaan: pelanggan harus nunggu suppliernya
 * atau tidak?
 *
 *   KirimDulu    — Ugreen/Anker. Barang wajib dikirim ke supplier dulu, baru
 *                  pelanggan dapat hasilnya. 8 tahap, persis alur lama.
 *   GantiDulu    — Robot/Olike. Barang cukup dicek tim pusat, penggantinya
 *                  dikirim ke toko tanpa nunggu supplier. 6 tahap: sama
 *                  seperti KirimDulu tapi MELEWATI dua tahap supplier.
 *   TukarTempat  — Robot/Olike dan stok cabang ada. Ditukar langsung di depan
 *                  pelanggan, selesai hari itu juga. 2 tahap.
 *
 * Di GantiDulu dan TukarTempat, urusan klaim ke supplier jadi perkara internal
 * kita sendiri (WarrantySupplierShipment) dan SENGAJA tidak pernah kelihatan
 * di halaman lacak — pelanggannya sudah beres, bukan urusan dia lagi.
 */
enum WarrantyClaimFlow: string
{
    case KirimDulu   = 'kirim_dulu';
    case GantiDulu   = 'ganti_dulu';
    case TukarTempat = 'tukar_tempat';

    public function label(): string
    {
        return match ($this) {
            self::KirimDulu   => 'Kirim ke supplier dahulu',
            self::GantiDulu   => 'Ganti dulu, klaim supplier belakangan',
            self::TukarTempat => 'Tukar langsung di cabang',
        };
    }

    /** Keterangan singkat buat staf — muncul di form terima barang. */
    public function hint(): string
    {
        return match ($this) {
            self::KirimDulu   => 'Barang dikirim ke supplier dulu, pelanggan menunggu hasilnya.',
            self::GantiDulu   => 'Barang dicek tim pusat, pengganti dikirim ke toko tanpa menunggu supplier.',
            self::TukarTempat => 'Ditukar di tempat hari ini juga. Unit rusaknya diklaim ke supplier belakangan.',
        };
    }

    /**
     * Urutan tahap. INI SATU-SATUNYA sumber kebenaran urutan alur — next(),
     * progress bar detail, dan progress bar lacak publik semuanya baca dari
     * sini, jadi tidak mungkin ketiganya beda cerita.
     */
    public function timeline(): array
    {
        return match ($this) {
            self::KirimDulu => [
                WarrantyClaimStatus::DiterimaCabang,
                WarrantyClaimStatus::DicekPusat,
                WarrantyClaimStatus::DikirimVendor,
                WarrantyClaimStatus::DicekVendor,
                WarrantyClaimStatus::HasilVendor,
                WarrantyClaimStatus::DikirimBalik,
                WarrantyClaimStatus::SiapDiambil,
                WarrantyClaimStatus::Selesai,
            ],
            self::GantiDulu => [
                WarrantyClaimStatus::DiterimaCabang,
                WarrantyClaimStatus::DicekPusat,
                WarrantyClaimStatus::HasilVendor,
                WarrantyClaimStatus::DikirimBalik,
                WarrantyClaimStatus::SiapDiambil,
                WarrantyClaimStatus::Selesai,
            ],
            self::TukarTempat => [
                WarrantyClaimStatus::DiterimaCabang,
                WarrantyClaimStatus::Selesai,
            ],
        };
    }

    /** Tahap sesudah $status di alur ini. null = sudah ujung. */
    public function next(WarrantyClaimStatus $status): ?WarrantyClaimStatus
    {
        $timeline = $this->timeline();
        $i = array_search($status, $timeline, true);

        // false = status di luar alur (mis. Batal) → tidak ada lanjutannya.
        return $i === false ? null : ($timeline[$i + 1] ?? null);
    }

    /**
     * Label tahap yang menyesuaikan alur. "Selesai" di alur tukar-di-tempat
     * bukan "Sudah Diambil" — pelanggannya tidak mengambil apa pun, dia
     * langsung ditukar. Salah kata di sini bikin nota dan halaman lacak bohong.
     */
    public function statusLabel(WarrantyClaimStatus $status): string
    {
        if ($this === self::TukarTempat && $status === WarrantyClaimStatus::Selesai) {
            return 'Sudah Diganti';
        }

        return $status->label();
    }

    /** Alur yang klaim suppliernya berdiri sendiri (tidak nyangkut ke pelanggan). */
    public function supplierClaimIsInternal(): bool
    {
        return $this !== self::KirimDulu;
    }
}
