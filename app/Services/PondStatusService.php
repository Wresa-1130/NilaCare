<?php

namespace App\Services;

use App\Models\Pond;

/**
 * Mengelola status online/offline perangkat dan memberi notifikasi saat berubah.
 */
class PondStatusService
{
    public function __construct(private TelegramNotifier $telegram) {}

    /** Catat bahwa perangkat baru saja mengirim pesan. */
    public function markSeen(Pond $pond, bool $isTelemetry = true): void
    {
        $wasOffline = ! $pond->is_online;
        $pond->forceFill(['last_seen_at' => now(), 'is_online' => true])->save();
        if ($wasOffline && $isTelemetry) {
            $this->telegram->send('✅ <b>'.e($pond->name).'</b>: perangkat kembali online.');
        }
    }

    public function markOnline(Pond $pond): void
    {
        $this->markSeen($pond);
    }

    public function markOffline(Pond $pond, string $reason): void
    {
        if (! $pond->is_online) {
            return;
        }
        $pond->forceFill(['is_online' => false])->save();
        $this->telegram->send('⚠️ <b>'.e($pond->name).'</b>: perangkat offline ('.e($reason).'). Kendali otomatis tetap berjalan di ESP32, tetapi data tidak terkirim.');
    }

    /** Tandai offline semua kolam yang tidak mengirim data lebih lama dari batas. */
    public function checkTimeouts(): void
    {
        $limit = now()->subSeconds(config('nilacare.offline_after'));
        Pond::where('is_online', true)->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $limit))
            ->get()->each(fn (Pond $p) => $this->markOffline($p, 'tidak ada data > '.config('nilacare.offline_after').' detik'));
    }
}
