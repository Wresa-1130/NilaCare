<?php

namespace App\Services;

use App\Models\Alarm;
use App\Models\Pond;
use App\Models\SensorReading;
use App\Models\User;

/**
 * Membuka, menutup, dan mengonfirmasi alarm, serta mengirim notifikasi Telegram
 * (maksimal satu notifikasi saat alarm mulai dan satu saat pulih).
 */
class AlarmService
{
    private const RECOVERED = 'kondisi kembali normal';
    private const ACKED = 'dikonfirmasi petugas';

    public function __construct(private TelegramNotifier $telegram) {}

    /** Event "alarm" dari ESP32: pesan alarm baru, pulih, atau dikonfirmasi. */
    public function handleAlarmEvent(Pond $pond, string $detail): void
    {
        match ($detail) {
            self::RECOVERED => $this->close($pond),
            self::ACKED => $this->acknowledge($pond, null),
            default => $this->open($pond, $detail),
        };
    }

    /** Cadangan jika event terlewat: telemetry juga membawa pesan alarm. */
    public function syncFromTelemetry(Pond $pond, SensorReading $reading): void
    {
        $open = $pond->openAlarm();
        if ($reading->alarm && ! $open) {
            $this->open($pond, $reading->alarm);
        } elseif (! $reading->alarm && $open) {
            $this->close($pond);
        }
    }

    public function open(Pond $pond, string $message): Alarm
    {
        $open = $pond->openAlarm();
        if ($open && $open->message === $message) {
            return $open;
        }
        $open?->update(['ended_at' => now()]);

        $alarm = $pond->alarms()->create(['message' => $message, 'started_at' => now()]);
        $r = $pond->latestReading;
        $values = $r ? sprintf(
            "\nDO %.1f mg/L · Suhu %.1f °C · pH %.1f · NTU %.0f · Level %.0f cm",
            $r->do_mg_l, $r->temp_c, $r->ph, $r->ntu, $r->level_cm
        ) : '';
        $name = e($pond->name);
        if ($this->telegram->send("🚨 <b>{$name}</b>: ".e($message)."{$values}\nAksi otomatis sudah dijalankan ESP32. Cek dashboard untuk kendali manual.")) {
            $alarm->update(['notified_at' => now()]);
        }

        return $alarm;
    }

    public function close(Pond $pond): void
    {
        $open = $pond->openAlarm();
        if (! $open) {
            return;
        }
        $open->update(['ended_at' => now()]);
        $minutes = max(1, (int) $open->started_at->diffInMinutes(now()));
        $this->telegram->send('✅ <b>'.e($pond->name)."</b>: kondisi kembali normal setelah {$minutes} menit (".e($open->message).').');
    }

    public function acknowledge(Pond $pond, ?User $user): void
    {
        $open = $pond->openAlarm();
        if ($open && ! $open->acknowledged_at) {
            $open->update(['acknowledged_at' => now(), 'acknowledged_by' => $user?->id ?? $open->acknowledged_by]);
        }
    }
}
