<?php

namespace App\Enums;

enum LeaveStatus: string
{
    case Pending  = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired  = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending  => 'Menunggu',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Expired => 'Kedaluwarsa',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending  => 'bg-amber-100 text-amber-800',
            self::Approved => 'bg-emerald-100 text-emerald-800',
            self::Rejected => 'bg-rose-100 text-rose-800',
            self::Expired  => 'bg-slate-200 text-slate-600',
        };
    }
}