<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Perubahan status aktuator (event type "actuator"). */
class ActuatorEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['pond_id', 'actuator', 'state', 'reason', 'received_at'];

    protected $casts = ['received_at' => 'datetime'];

    public function pond(): BelongsTo
    {
        return $this->belongsTo(Pond::class);
    }
}
