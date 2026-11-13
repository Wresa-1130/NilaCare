<?php

namespace App\Services\Mqtt;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

/**
 * Membuat klien MQTT mandiri dari config/mqtt-client.php (dipakai fake device & cek koneksi),
 * dengan client ID dan Last Will sendiri.
 */
class ClientFactory
{
    public static function make(string $clientId, ?string $willTopic = null, ?string $willMessage = null): array
    {
        $c = config('mqtt-client.connections.default');
        $s = $c['connection_settings'];
        $settings = (new ConnectionSettings)
            ->setConnectTimeout($s['connect_timeout'])
            ->setSocketTimeout($s['socket_timeout'])
            ->setKeepAliveInterval($s['keep_alive_interval'])
            ->setUsername($s['auth']['username'])
            ->setPassword($s['auth']['password'])
            ->setUseTls($s['tls']['enabled'])
            ->setTlsVerifyPeer($s['tls']['verify_peer'])
            ->setTlsVerifyPeerName($s['tls']['verify_peer_name'])
            ->setTlsSelfSignedAllowed($s['tls']['allow_self_signed_certificate'])
            ->setTlsCertificateAuthorityFile($s['tls']['ca_file'])
            ->setTlsCertificateAuthorityPath($s['tls']['ca_path']);
        if ($willTopic) {
            $settings = $settings->setLastWillTopic($willTopic)->setLastWillMessage($willMessage)
                ->setLastWillQualityOfService(1)->setRetainLastWill(true);
        }

        return [new MqttClient($c['host'], (int) $c['port'], $clientId, MqttClient::MQTT_3_1_1), $settings];
    }
}
