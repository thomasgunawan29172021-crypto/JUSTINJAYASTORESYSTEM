<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialVideoPlatform extends Model
{
    protected $table = 'social_video_platform';

    protected $fillable = ['social_video_id', 'platform_id', 'url'];

    public function video()    { return $this->belongsTo(SocialVideo::class, 'social_video_id'); }
    public function platform() { return $this->belongsTo(Platform::class); }
    public function snapshots()
    {
        return $this->hasMany(VideoMetricSnapshot::class, 'social_video_platform_id')->orderByDesc('recorded_at');
    }
    public function latestSnapshot()
    {
        return $this->hasOne(VideoMetricSnapshot::class, 'social_video_platform_id')->latestOfMany('recorded_at');
    }
}