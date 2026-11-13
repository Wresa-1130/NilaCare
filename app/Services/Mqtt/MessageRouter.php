<?php

namespace App\Services\Mqtt;

use App\Models\Pond;
use App\Services\Mqtt\Handlers\EventHandler;
use App\Services\Mqtt\Handlers\StatusHandler;
use App\Services\Mqtt\Handlers\TelemetryHandler;
use Illuminate\Support\Facades\Log;

/**
 * Mengarahkan pesan MQTT ke handler berdasarkan topik nilacare/{kolam}/{jenis}.
 */
class MessageRouter
{
    public function __construct(
        private TelemetryHandler $telemetry,
        private EventHandler $event,
        private StatusHandler $status,
    ) {}

    /** Topik yang di-subscribe listener. */
    public static function subscriptions(): array
    {
        $prefix = config('nilacare.topic_prefix');

        return ["$prefix/+/telemetry", "$prefix/+/event", "$prefix/+/status"];
    }

    /**
     * Proses satu pesan. Mengembalikan jenis pesan yang diproses, atau null jika diabaikan.
     */
    public function route(string $topic, string $message): ?string
    {
        $parts = explode('/', $topic);
        if (count($parts) !== 3 || $parts[0] !== config('nilacare.topic_prefix')) {
            return null;
        }
        [, $code, $kind] = $parts;
        $pond = Pond::findOrCreateByCode($code);

        if ($kind === 'status') {
            $this->status->handle($pond, trim($message));

            return $kind;
        }

        $data = json_decode($message, true);
        if (! is_array($data)) {
            Log::warning('[MQTT] payload bukan JSON valid', ['topic' => $topic, 'message' => $message]);

            return null;
        }

        match ($kind) {
            'telemetry' => $this->telemetry->handle($pond, $data),
            'event' => $this->event->handle($pond, $data),
            default => null,
        };

        return in_array($kind, ['telemetry', 'event'], true) ? $kind : null;
    }
}
