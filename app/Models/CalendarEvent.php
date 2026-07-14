<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarEvent extends Model
{
    public const COLORS = [
        'slate'   => 'bg-slate-600',
        'emerald' => 'bg-emerald-600',
        'amber'   => 'bg-amber-500',
        'rose'    => 'bg-rose-600',
        'violet'  => 'bg-violet-600',
        'sky'     => 'bg-sky-600',
    ];

    protected $fillable = ['title', 'date', 'date_end', 'color', 'note', 'created_by'];

    protected $casts = ['date' => 'date', 'date_end' => 'date'];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}