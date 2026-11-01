<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncentiveParticipant extends Model
{
    protected $fillable = ['user_id', 'incentive_type', 'cooperation_role', 'share_percent'];
    protected function casts(): array { return ['share_percent' => 'decimal:2']; }
    public function submission(): BelongsTo { return $this->belongsTo(IncentiveSubmission::class, 'incentive_submission_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
