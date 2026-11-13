<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Riwayat ambang yang pernah dikirim ke ESP32 (yang terbaru = yang berlaku). */
class ThresholdConfig extends Model
{
    public $timestamps = false;

    protected $fillable = ['pond_id', 'user_id', 'values', 'published_at'];

    protected $casts = [
        'values' => 'array',
        'published_at' => 'datetime',
    ];

    public function pond(): BelongsTo
    {
        return $this->belongsTo(Pond::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
