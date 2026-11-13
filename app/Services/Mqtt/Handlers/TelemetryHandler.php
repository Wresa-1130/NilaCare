<?php

namespace App\Services\Mqtt\Handlers;

use App\Models\Pond;
use App\Models\SensorReading;
use App\Services\AlarmService;
use App\Services\PondStatusService;

/**
 * Menyimpan telemetry (tiap 30 detik) dan memperbarui status kolam.
 * Waktu data = waktu server saat pesan diterima (field ts dari ESP32 adalah uptime).
 */
class TelemetryHandler
{
    public function __construct(
        private AlarmService $alarms,
        private PondStatusService $status,
    ) {}

    public function handle(Pond $pond, array $d): SensorReading
    {
        $reading = $pond->readings()->create([
            'do_mg_l' => $d['do'] ?? null,
            'temp_c' => $d['temp'] ?? null,
            'temp_tank_c' => $d['temp_tank'] ?? null,
            'ph' => $d['ph'] ?? null,
            'ntu' => $d['ntu'] ?? null,
            'level_cm' => $d['level_cm'] ?? null,
            'float_min' => (bool) ($d['float_min'] ?? false),
            'zone_do' => $d['zone']['do'] ?? null,
            'zone_temp' => $d['zone']['temp'] ?? null,
            'zone_ph' => $d['zone']['ph'] ?? null,
            'zone_ntu' => $d['zone']['ntu'] ?? null,
            'actuators' => $d['act'] ?? [],
            'mode' => $d['mode'] ?? null,
            'water_change_state' => $d['water_change'] ?? null,
            'alarm' => ($d['alarm'] ?? '') ?: null,
            'received_at' => now(),
        ]);

        $pond->forceFill(['mode' => $d['mode'] ?? $pond->mode])->save();
        $this->status->markSeen($pond);
        $this->alarms->syncFromTelemetry($pond, $reading);

        return $reading;
    }
}
