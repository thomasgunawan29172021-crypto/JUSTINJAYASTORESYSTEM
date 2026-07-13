<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Diterima           = 'diterima';
    case Diagnosa           = 'diagnosa';
    case MenungguKonfirmasi = 'menunggu_konfirmasi';
    case MenungguSparepart  = 'menunggu_sparepart';
    case Dikerjakan         = 'dikerjakan';
    case Qc                 = 'qc';
    case SiapDiambil        = 'siap_diambil';
    case Selesai            = 'selesai';
    case Dibatalkan         = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::Diterima           => 'Diterima',
            self::Diagnosa           => 'Diagnosa',
            self::MenungguKonfirmasi => 'Menunggu Konfirmasi',
            self::MenungguSparepart  => 'Menunggu Sparepart',
            self::Dikerjakan         => 'Dikerjakan',
            self::Qc                 => 'QC / Testing',
            self::SiapDiambil        => 'Siap Diambil',
            self::Selesai            => 'Selesai',
            self::Dibatalkan         => 'Dibatalkan',
        };
    }

    /** Dipakai nanti di halaman tracking publik (Fase 4). */
    public function publicLabel(): string
    {
        return match ($this) {
            self::Diterima           => 'Unit diterima',
            self::Diagnosa           => 'Sedang dicek teknisi',
            self::MenungguKonfirmasi => 'Menunggu konfirmasi Anda',
            self::MenungguSparepart  => 'Menunggu sparepart',
            self::Dikerjakan         => 'Sedang dikerjakan',
            self::Qc                 => 'Pengecekan akhir (QC)',
            self::SiapDiambil        => 'Selesai — siap diambil',
            self::Selesai            => 'Sudah diambil',
            self::Dibatalkan         => 'Servis dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Diterima           => 'bg-sky-100 text-sky-800',
            self::Diagnosa           => 'bg-indigo-100 text-indigo-800',
            self::MenungguKonfirmasi => 'bg-amber-100 text-amber-800',
            self::MenungguSparepart  => 'bg-orange-100 text-orange-800',
            self::Dikerjakan         => 'bg-blue-100 text-blue-800',
            self::Qc                 => 'bg-violet-100 text-violet-800',
            self::SiapDiambil        => 'bg-emerald-100 text-emerald-800',
            self::Selesai            => 'bg-slate-200 text-slate-700',
            self::Dibatalkan         => 'bg-rose-100 text-rose-800',
        };
    }

    /** State machine inti — jangan diubah tanpa mikirin efek ke KPI durasi per-status. */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Diterima           => [self::Diagnosa, self::Dibatalkan],
            self::Diagnosa           => [self::MenungguKonfirmasi, self::Dibatalkan],
            self::MenungguKonfirmasi => [self::Dikerjakan, self::MenungguSparepart, self::Dibatalkan],
            self::MenungguSparepart  => [self::Dikerjakan, self::Dibatalkan],
            self::Dikerjakan         => [self::Qc, self::MenungguSparepart],
            self::Qc                 => [self::SiapDiambil, self::Dikerjakan], // gagal QC → balik dikerjakan
            self::SiapDiambil        => [self::Selesai],
            self::Selesai            => [],
            self::Dibatalkan         => [self::SiapDiambil], // unit batal tetap harus diambil balik
        };
    }

    public static function openStatuses(): array
    {
        return [
            self::Diterima, self::Diagnosa, self::MenungguKonfirmasi,
            self::MenungguSparepart, self::Dikerjakan, self::Qc,
            self::SiapDiambil, self::Dibatalkan,
        ];
    }
}