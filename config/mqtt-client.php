<?php

declare(strict_types=1);

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\Repositories\MemoryRepository;

/*
|--------------------------------------------------------------------------
| Koneksi MQTT NilaCare
|--------------------------------------------------------------------------
| Dua koneksi ke broker yang sama, dengan client ID berbeda:
|  - "default"   dipakai `php artisan mqtt:listen` (proses jangka panjang).
|  - "publisher" dipakai request web untuk mengirim cmd/config. Client ID
|                dibiarkan kosong agar dibuat acak setiap koneksi, sehingga
|                tidak memutus listener.
*/

$settings = static fn (bool $autoReconnect) => [
    'tls' => [
        'enabled' => (bool) env('MQTT_TLS_ENABLED', false),
        'allow_self_signed_certificate' => (bool) env('MQTT_TLS_ALLOW_SELF_SIGNED_CERT', false),
        'verify_peer' => (bool) env('MQTT_TLS_VERIFY_PEER', true),
        'verify_peer_name' => (bool) env('MQTT_TLS_VERIFY_PEER_NAME', true),
        'ca_file' => env('MQTT_TLS_CA_FILE') ?: null,
        'ca_path' => env('MQTT_TLS_CA_PATH') ?: null,
        'client_certificate_file' => null,
        'client_certificate_key_file' => null,
        'client_certificate_key_passphrase' => null,
        'alpn' => null,
    ],
    'auth' => [
        'username' => env('MQTT_AUTH_USERNAME') ?: null,
        'password' => env('MQTT_AUTH_PASSWORD') ?: null,
    ],
    'last_will' => [
        'topic' => null,
        'message' => null,
        'quality_of_service' => 0,
        'retain' => false,
    ],
    'connect_timeout' => (int) env('MQTT_CONNECT_TIMEOUT', 15),
    'socket_timeout' => (int) env('MQTT_SOCKET_TIMEOUT', 5),
    'resend_timeout' => 10,
    'keep_alive_interval' => (int) env('MQTT_KEEP_ALIVE_INTERVAL', 20),
    'auto_reconnect' => [
        'enabled' => $autoReconnect,
        'max_reconnect_attempts' => (int) env('MQTT_AUTO_RECONNECT_MAX_RECONNECT_ATTEMPTS', 1000),
        'delay_between_reconnect_attempts' => (int) env('MQTT_AUTO_RECONNECT_DELAY_BETWEEN_RECONNECT_ATTEMPTS', 5),
    ],
];

return [

    'default_connection' => 'default',

    'connections' => [

        'default' => [
            'host' => env('MQTT_HOST', '127.0.0.1'),
            'port' => (int) env('MQTT_PORT', 1883),
            'protocol' => MqttClient::MQTT_3_1_1,
            'client_id' => env('MQTT_CLIENT_ID', 'nilacare-listener'),
            'use_clean_session' => false, // wajib false agar auto-reconnect aktif
            'enable_logging' => (bool) env('MQTT_ENABLE_LOGGING', false),
            'log_channel' => env('MQTT_LOG_CHANNEL'),
            'repository' => MemoryRepository::class,
            'connection_settings' => $settings(true),
        ],

        'publisher' => [
            'host' => env('MQTT_HOST', '127.0.0.1'),
            'port' => (int) env('MQTT_PORT', 1883),
            'protocol' => MqttClient::MQTT_3_1_1,
            'client_id' => null,
            'use_clean_session' => true,
            'enable_logging' => (bool) env('MQTT_ENABLE_LOGGING', false),
            'log_channel' => env('MQTT_LOG_CHANNEL'),
            'repository' => MemoryRepository::class,
            'connection_settings' => $settings(false),
        ],

    ],

];
