<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoMetricSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = ['social_video_id', 'social_video_platform_id', 'views', 'likes', 'comments', 'saves', 'recorded_by', 'recorded_at'];

    protected $casts = ['recorded_at' => 'datetime'];

    public function video()    { return $this->belongsTo(SocialVideo::class, 'social_video_id'); }
    public function posting()  { return $this->belongsTo(SocialVideoPlatform::class, 'social_video_platform_id'); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by'); }
}
