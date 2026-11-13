<?php

namespace App\Console\Commands;

use App\Services\Mqtt\MessageRouter;
use App\Services\PondStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Contracts\MqttClient;
use PhpMqtt\Client\Facades\MQTT;
use Throwable;

/**
 * Proses jangka panjang: berlangganan telemetry, event, dan status dari semua kolam,
 * lalu menyimpannya ke database. Jalankan di terminal terpisah selama sistem dipakai.
 */
class MqttListen extends Command
{
    protected $signature = 'mqtt:listen';

    protected $description = 'Dengarkan pesan MQTT dari ESP32 dan simpan ke database';

    public function handle(MessageRouter $router, PondStatusService $status): int
    {
        $host = config('mqtt-client.connections.default.host').':'.config('mqtt-client.connections.default.port');
        $this->info("Menghubungkan ke broker $host ...");

        try {
            $client = MQTT::connection();
        } catch (Throwable $e) {
            $this->error('Gagal terhubung: '.$e->getMessage());
            $this->line('Cek MQTT_HOST, MQTT_PORT, username/password, dan MQTT_TLS_* di .env');

            return self::FAILURE;
        }

        foreach (MessageRouter::subscriptions() as $topic) {
            $client->subscribe($topic, function (string $topic, string $message) use ($router) {
                try {
                    $kind = $router->route($topic, $message);
                    $this->line(sprintf('<fg=gray>%s</> %-26s %s', now()->format('H:i:s'), $topic, $kind ? mb_strimwidth($message, 0, 90, '…') : '(diabaikan)'));
                } catch (Throwable $e) {
                    // Satu pesan rusak tidak boleh menghentikan listener
                    Log::error('[MQTT] gagal memproses pesan', ['topic' => $topic, 'error' => $e->getMessage()]);
                    $this->error("Gagal memproses $topic: ".$e->getMessage());
                }
            }, \PhpMqtt\Client\MqttClient::QOS_AT_LEAST_ONCE);
            $this->line("  subscribe $topic");
        }

        // Cek perangkat yang berhenti mengirim data, tiap 30 detik
        $lastCheck = 0;
        $client->registerLoopEventHandler(function () use (&$lastCheck, $status) {
            if (time() - $lastCheck >= 30) {
                $lastCheck = time();
                try {
                    $status->checkTimeouts();
                } catch (Throwable $e) {
                    Log::error('[MQTT] cek offline gagal: '.$e->getMessage());
                }
            }
        });

        if (function_exists('pcntl_async_signals')) { // tidak tersedia di Windows
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, fn () => $client->interrupt());
            pcntl_signal(SIGTERM, fn () => $client->interrupt());
        }

        $this->info('Terhubung. Menunggu pesan... (Ctrl+C untuk berhenti)');
        $client->loop(true);
        MQTT::disconnect();

        return self::SUCCESS;
    }
}
