<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Satu kolam = satu perangkat ESP32 (kode kolam = POND_ID di firmware).
 */
class Pond extends Model
{
    protected $fillable = ['code', 'name', 'is_online', 'last_seen_at', 'mode'];

    protected $casts = [
        'is_online' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    /** Cari kolam berdasarkan kode; buat otomatis jika belum ada. */
    public static function findOrCreateByCode(string $code): self
    {
        return static::firstOrCreate(['code' => $code], ['name' => ucfirst($code)]);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(SensorReading::class);
    }

    public function latestReading(): HasOne
    {
        return $this->hasOne(SensorReading::class)->latestOfMany('received_at');
    }

    public function actuatorEvents(): HasMany
    {
        return $this->hasMany(ActuatorEvent::class);
    }

    public function deviceEvents(): HasMany
    {
        return $this->hasMany(DeviceEvent::class);
    }

    public function alarms(): HasMany
    {
        return $this->hasMany(Alarm::class);
    }

    public function commandLogs(): HasMany
    {
        return $this->hasMany(CommandLog::class);
    }

    public function thresholdConfigs(): HasMany
    {
        return $this->hasMany(ThresholdConfig::class);
    }

    /** Alarm yang masih aktif (belum ditutup). */
    public function openAlarm(): ?Alarm
    {
        return $this->alarms()->whereNull('ended_at')->latest('started_at')->first();
    }

    /** Topik MQTT untuk kolam ini, mis. nilacare/kolam1/cmd. */
    public function topic(string $leaf): string
    {
        return config('nilacare.topic_prefix').'/'.$this->code.'/'.$leaf;
    }
}
