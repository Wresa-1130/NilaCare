<?php

namespace App\Console\Commands;

use App\Services\Mqtt\ClientFactory;
use App\Services\TelegramNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cek cepat semua koneksi: database, broker MQTT, dan Telegram.
 */
class NilacareCheck extends Command
{
    protected $signature = 'nilacare:check {--telegram : kirim pesan uji ke Telegram}';

    protected $description = 'Cek koneksi database, broker MQTT, dan Telegram';

    public function handle(TelegramNotifier $tg): int
    {
        $ok = true;

        try {
            DB::connection()->getPdo();
            $this->info('✔ Database: '.config('database.default').' terhubung');
        } catch (Throwable $e) {
            $ok = false;
            $this->error('✘ Database: '.$e->getMessage());
        }

        $c = config('mqtt-client.connections.default');
        $tls = $c['connection_settings']['tls']['enabled'] ? 'TLS' : 'tanpa TLS';
        try {
            [$client, $settings] = ClientFactory::make('nilacare-check-'.substr(md5(uniqid()), 0, 6));
            $client->connect($settings, true);
            $client->disconnect();
            $this->info("✔ MQTT: terhubung ke {$c['host']}:{$c['port']} ($tls)");
        } catch (Throwable $e) {
            $ok = false;
            $this->error("✘ MQTT {$c['host']}:{$c['port']} ($tls): ".$e->getMessage());
        }

        if (! filled(config('nilacare.telegram.token'))) {
            $this->warn('• Telegram: TELEGRAM_BOT_TOKEN belum diisi (notifikasi dinonaktifkan)');
        } else {
            $me = $tg->api('getMe');
            if ($me['ok'] ?? false) {
                $this->info('✔ Telegram: bot @'.$me['result']['username']);
                if (empty(config('nilacare.telegram.chat_ids'))) {
                    $this->warn('• TELEGRAM_CHAT_ID kosong. Jalankan php artisan nilacare:telegram lalu kirim /start ke bot.');
                } elseif ($this->option('telegram')) {
                    $sent = $tg->send('🔔 Pesan uji dari <b>NilaCare</b>. Notifikasi berfungsi.');
                    $sent ? $this->info('✔ Pesan uji terkirim') : $this->error('✘ Pesan uji gagal (cek TELEGRAM_CHAT_ID)');
                }
            } else {
                $ok = false;
                $this->error('✘ Telegram: token tidak valid');
            }
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
