<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialVideo extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'added_by', 'title', 'theme', 'is_collab', 'published_at', 'frozen_at', 'code'
    ];

    protected $casts = [
        'published_at' => 'date',
        'frozen_at'    => 'datetime',
        'is_collab'    => 'boolean',
    ];

    public function creators()
    {
        return $this->belongsToMany(User::class, 'social_video_user')->withPivot('is_pic');
    }

    /** Pembuat utama — satu-satunya yang dapat kredit KPI. */
    public function pic()
    {
        return $this->belongsToMany(User::class, 'social_video_user')
            ->withPivot('is_pic')->wherePivot('is_pic', true);
    }

    /** Anggota colab — dicatat sebagai info, nol kredit KPI. */
    public function members()
    {
        return $this->belongsToMany(User::class, 'social_video_user')
            ->withPivot('is_pic')->wherePivot('is_pic', false);
    }

    public function adder() { return $this->belongsTo(User::class, 'added_by'); }

    public function postings()
    {
        return $this->hasMany(SocialVideoPlatform::class)->with('platform');
    }

    /** Metrik gabungan semua platform (snapshot terakhir per posting). */
    public function metricTotal(string $field): int
    {
        return (int) $this->postings->sum(fn ($p) => $p->latestSnapshot?->{$field} ?? 0);
    }

    public const DUE_DAYS    = 14;  // update final jatuh tempo
    public const FORCE_DAYS  = 30;  // beku paksa tanpa update

    public function scopeActive($q)
    {
        return $q->whereNull('frozen_at');
    }

    /** Sudah waktunya update final? */
    public function isDue(): bool
    {
        return ! $this->frozen_at && $this->published_at->lte(now()->subDays(self::DUE_DAYS));
    }

    public function daysLive(): int
    {
        return (int) $this->published_at->diffInDays(now());
    }
}
