<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu periode alarm: dibuka saat kondisi bahaya, ditutup saat pulih. */
class Alarm extends Model
{
    public $timestamps = false;

    protected $fillable = ['pond_id', 'message', 'started_at', 'ended_at', 'acknowledged_by', 'acknowledged_at', 'notified_at'];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'notified_at' => 'datetime',
    ];

    public function pond(): BelongsTo
    {
        return $this->belongsTo(Pond::class);
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
