<?php

namespace App\Services\Mqtt\Handlers;

use App\Models\CommandLog;
use App\Models\Pond;
use App\Services\AlarmService;
use App\Services\PondStatusService;

/**
 * Menyimpan event dari ESP32 dan mencocokkan konfirmasi perintah manual.
 */
class EventHandler
{
    public function __construct(
        private AlarmService $alarms,
        private PondStatusService $status,
    ) {}

    public function handle(Pond $pond, array $d): void
    {
        $type = (string) ($d['type'] ?? 'unknown');
        $now = now();
        $this->status->markSeen($pond, false);

        if ($type === 'actuator') {
            $pond->actuatorEvents()->create([
                'actuator' => (string) ($d['name'] ?? '?'),
                'state' => (string) ($d['state'] ?? '?'),
                'reason' => $d['reason'] ?? null,
                'received_at' => $now,
            ]);

            return;
        }

        $detail = (string) ($d['detail'] ?? '');
        $pond->deviceEvents()->create(['type' => $type, 'detail' => $detail, 'received_at' => $now]);

        match ($type) {
            'alarm' => $this->alarms->handleAlarmEvent($pond, $detail),
            'mode' => $pond->forceFill(['mode' => $detail === 'manual' ? 'manual' : 'auto'])->save(),
            default => null,
        };

        $this->confirmCommand($pond, $type, $detail);
    }

    /**
     * Tandai perintah petugas sebagai terkonfirmasi saat ESP32 melaporkan sudah menerimanya.
     */
    private function confirmCommand(Pond $pond, string $type, string $detail): void
    {
        $match = match (true) {
            $type === 'manual' && preg_match('/^(\w+) -> (\w+)$/', $detail, $m) === 1 => fn ($p) => ($p['actuator'] ?? null) === $m[1] && ($p['state'] ?? null) === $m[2],
            $type === 'mode' => fn ($p) => ($p['mode'] ?? null) === $detail,
            $type === 'ganti_air' && $detail === 'manual' => fn ($p) => ($p['action'] ?? null) === 'water_change',
            $type === 'dosis' && str_contains($detail, 'manual') => fn ($p) => ($p['action'] ?? null) === 'dose',
            $type === 'alarm' && $detail === 'dikonfirmasi petugas' => fn ($p) => ($p['action'] ?? null) === 'ack',
            $type === 'ai' => fn ($p) => ($p['action'] ?? null) === 'aerator_early',
            $type === 'config' => fn ($p) => true,
            default => null,
        };
        if (! $match) {
            return;
        }

        $topic = $type === 'config' ? 'config' : 'cmd';
        $log = CommandLog::where('pond_id', $pond->id)->where('topic', $topic)->where('status', 'sent')
            ->where('sent_at', '>=', now()->subMinutes(10))->latest('sent_at')->get()
            ->first(fn (CommandLog $l) => $match($l->payload));

        $log?->update(['status' => 'confirmed', 'confirmed_at' => now()]);
    }
}
