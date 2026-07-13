<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketNotification extends Model
{
    public $timestamps = false;

    protected $fillable = ['ticket_id', 'type', 'user_id', 'created_at'];

    protected $casts = [
        'type'       => NotificationType::class,
        'created_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ServiceTicket::class, 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}