<?php

namespace App\Services\Mqtt;

use PhpMqtt\Client\Facades\MQTT;

/**
 * Mengirim pesan ke broker memakai koneksi "publisher" (client ID acak),
 * agar tidak memutus koneksi listener.
 */
class MqttPublisher
{
    public function publish(string $topic, array|string $payload, bool $retain = false, int $qos = 1): void
    {
        $message = is_array($payload) ? json_encode($payload, JSON_UNESCAPED_SLASHES) : $payload;
        $client = MQTT::connection('publisher');
        try {
            $client->publish($topic, $message, $qos, $retain);
            // Selesaikan handshake QoS 1 (PUBACK) sebelum memutus koneksi
            $client->loop(true, true);
        } finally {
            MQTT::disconnect('publisher');
        }
    }
}
