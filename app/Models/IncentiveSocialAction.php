<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncentiveSocialAction extends Model
{
    protected $fillable = ['beneficiary_id', 'platform', 'account_name'];
    public function submission(): BelongsTo { return $this->belongsTo(IncentiveSubmission::class, 'incentive_submission_id'); }
    public function beneficiary(): BelongsTo { return $this->belongsTo(User::class, 'beneficiary_id'); }
}
