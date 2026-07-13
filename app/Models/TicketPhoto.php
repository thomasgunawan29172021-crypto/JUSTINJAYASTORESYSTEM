<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TicketPhoto extends Model
{
    public $timestamps = false;

    protected $fillable = ['ticket_id', 'type', 'path', 'uploaded_by', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ServiceTicket::class, 'ticket_id');
    }

    public function url(): string
    {
        return Storage::disk(config('filesystems.default'))->url($this->path);
    }
}