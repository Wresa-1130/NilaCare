<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu pesan telemetry dari ESP32 (dikirim tiap 30 detik). */
class SensorReading extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'pond_id', 'do_mg_l', 'temp_c', 'temp_tank_c', 'ph', 'ntu', 'level_cm', 'float_min',
        'zone_do', 'zone_temp', 'zone_ph', 'zone_ntu', 'actuators', 'mode', 'water_change_state',
        'alarm', 'received_at',
    ];

    protected $casts = [
        'do_mg_l' => 'float',
        'temp_c' => 'float',
        'temp_tank_c' => 'float',
        'ph' => 'float',
        'ntu' => 'float',
        'level_cm' => 'float',
        'float_min' => 'boolean',
        'actuators' => 'array',
        'received_at' => 'datetime',
    ];

    public function pond(): BelongsTo
    {
        return $this->belongsTo(Pond::class);
    }
}
