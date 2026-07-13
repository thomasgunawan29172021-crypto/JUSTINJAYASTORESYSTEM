<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = ['ticket_id', 'from_status', 'to_status', 'user_id', 'note', 'created_at'];

    protected $casts = [
        'from_status' => TicketStatus::class,
        'to_status'   => TicketStatus::class,
        'created_at'  => 'datetime',
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