<?php

namespace App\Services;

use App\Models\CommandLog;
use App\Models\Pond;
use App\Models\ThresholdConfig;
use App\Models\User;
use App\Services\Mqtt\MqttPublisher;
use Throwable;

/**
 * Semua perintah petugas ke ESP32 lewat sini: dicatat dulu, lalu dikirim ke topik cmd/config.
 */
class CommandService
{
    public function __construct(
        private MqttPublisher $publisher,
        private AlarmService $alarms,
    ) {}

    /** Kendali satu aktuator: state = on | off | auto. */
    public function actuator(Pond $pond, ?User $user, string $actuator, string $state): CommandLog
    {
        $name = config("nilacare.actuators.$actuator", $actuator);
        $label = ['on' => 'ON', 'off' => 'OFF', 'auto' => 'Otomatis'][$state];

        return $this->send($pond, $user, 'cmd', ['actuator' => $actuator, 'state' => $state], "$name → $label");
    }

    /** Mode semua aktuator: manual (kunci kondisi sekarang) | auto. */
    public function mode(Pond $pond, ?User $user, string $mode): CommandLog
    {
        return $this->send($pond, $user, 'cmd', ['mode' => $mode], $mode === 'manual' ? 'Mode Manual (kunci semua aktuator)' : 'Mode Otomatis');
    }

    /** Aksi: water_change | dose | ack | aerator_early. */
    public function action(Pond $pond, ?User $user, string $action, array $extra = []): CommandLog
    {
        if ($action === 'ack') {
            $this->alarms->acknowledge($pond, $user);
        }
        $labels = [
            'water_change' => 'Mulai ganti air',
            'dose' => 'Dosis kapur 3 detik',
            'ack' => 'Konfirmasi alarm',
            'aerator_early' => 'Aerator A dini',
        ];

        return $this->send($pond, $user, 'cmd', ['action' => $action] + $extra, $labels[$action] ?? $action);
    }

    /** Kirim ambang (retained, agar ESP32 menerimanya lagi setelah reconnect). */
    public function thresholds(Pond $pond, ?User $user, array $values): CommandLog
    {
        $log = $this->send($pond, $user, 'config', $values, 'Perbarui ambang ('.count($values).' nilai)', true);
        if ($log->status !== 'failed') {
            ThresholdConfig::create(['pond_id' => $pond->id, 'user_id' => $user?->id, 'values' => $values, 'published_at' => now()]);
        }

        return $log;
    }

    private function send(Pond $pond, ?User $user, string $leaf, array $payload, string $summary, bool $retain = false): CommandLog
    {
        $log = CommandLog::create([
            'pond_id' => $pond->id,
            'user_id' => $user?->id,
            'topic' => $leaf,
            'payload' => $payload,
            'summary' => $summary,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        try {
            $this->publisher->publish($pond->topic($leaf), $payload, $retain);
        } catch (Throwable $e) {
            $log->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 250)]);
        }

        return $log;
    }
}
