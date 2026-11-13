<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Perintah yang dikirim petugas ke ESP32 (topik cmd atau config). */
class CommandLog extends Model
{
    public $timestamps = false;

    // milidetik, agar latensi perintah→konfirmasi bisa diukur (Bab 4)
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = ['pond_id', 'user_id', 'topic', 'payload', 'summary', 'status', 'error', 'sent_at', 'confirmed_at'];

    protected $casts = [
        'payload' => 'array',
        'sent_at' => 'datetime',
        'confirmed_at' => 'datetime',
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
