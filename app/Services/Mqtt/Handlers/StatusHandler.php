<?php

namespace App\Services\Mqtt\Handlers;

use App\Models\Pond;
use App\Services\PondStatusService;

/**
 * Topik status berisi "online" (saat ESP32 terhubung) atau "offline" (Last Will dari broker).
 */
class StatusHandler
{
    public function __construct(private PondStatusService $status) {}

    public function handle(Pond $pond, string $message): void
    {
        if ($message === 'online') {
            $this->status->markOnline($pond);
        } elseif ($message === 'offline') {
            $this->status->markOffline($pond, 'Last Will dari broker');
        }
    }
}
