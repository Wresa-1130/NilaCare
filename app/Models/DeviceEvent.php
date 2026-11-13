<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Event selain aktuator: alarm, ganti_air, dosis, manual, mode, config, pengaman, ai. */
class DeviceEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['pond_id', 'type', 'detail', 'received_at'];

    protected $casts = ['received_at' => 'datetime'];

    public function pond(): BelongsTo
    {
        return $this->belongsTo(Pond::class);
    }
}
