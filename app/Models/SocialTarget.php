<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialTarget extends Model
{
    public $timestamps = false;

    protected $fillable = ['video_count', 'period', 'effective_from', 'created_by', 'created_at'];

    protected $casts = [
        'effective_from' => 'date',
        'created_at'     => 'datetime',
    ];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    /** Target yang berlaku pada 1 tanggal — riwayat dihormati, laporan lama tak berubah retroaktif. */
    public static function forDate(\Carbon\CarbonInterface $date): ?self
    {
        return static::where('effective_from', '<=', $date->toDateString())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }
}