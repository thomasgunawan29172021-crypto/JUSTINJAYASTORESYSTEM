<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketPart extends Model
{
    protected $fillable = ['ticket_id', 'name', 'qty', 'cost', 'price', 'added_by'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ServiceTicket::class, 'ticket_id');
    }
}