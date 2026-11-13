<?php

namespace App\Console\Commands;

use App\Models\Pond;
use App\Services\TelegramNotifier;
use Illuminate\Console\Command;
use Throwable;

/**
 * Bot Telegram mode long-polling (tidak butuh URL publik/webhook, cocok untuk laptop).
 * Perintah: /start (tampilkan chat ID), /status, /alarm, /help.
 * Notifikasi alarm tetap dikirim oleh mqtt:listen walaupun perintah ini tidak dijalankan.
 */
class TelegramBot extends Command
{
    protected $signature = 'nilacare:telegram';

    protected $description = 'Jalankan bot Telegram NilaCare (perintah /start, /status, /alarm)';

    public function handle(TelegramNotifier $tg): int
    {
        if (! filled(config('nilacare.telegram.token'))) {
            $this->error('TELEGRAM_BOT_TOKEN belum diisi di .env');

            return self::FAILURE;
        }
        $me = $tg->api('getMe');
        if (! ($me['ok'] ?? false)) {
            $this->error('Token tidak valid: '.json_encode($me));

            return self::FAILURE;
        }
        $this->info('Bot @'.$me['result']['username'].' berjalan. Kirim /start ke bot. Ctrl+C untuk berhenti.');
        $tg->api('setMyCommands', ['commands' => json_encode([
            ['command' => 'status', 'description' => 'Kondisi air terkini'],
            ['command' => 'alarm', 'description' => 'Alarm yang masih aktif'],
            ['command' => 'start', 'description' => 'Tampilkan chat ID'],
        ])]);

        $offset = 0;
        while (true) {
            try {
                $res = $tg->api('getUpdates', ['offset' => $offset, 'timeout' => 25], 35);
            } catch (Throwable $e) {
                $this->warn('getUpdates gagal: '.$e->getMessage());
                sleep(3);

                continue;
            }
            foreach ($res['result'] ?? [] as $u) {
                $offset = $u['update_id'] + 1;
                $msg = $u['message'] ?? null;
                if (! $msg || ! isset($msg['text'])) {
                    continue;
                }
                $chatId = $msg['chat']['id'];
                $cmd = strtolower(strtok(trim($msg['text']), ' @'));
                $this->line(now()->format('H:i:s')." chat $chatId: {$msg['text']}");
                $tg->sendTo($chatId, $this->reply($cmd, $chatId));
            }
        }
    }

    private function reply(string $cmd, int|string $chatId): string
    {
        $allowed = in_array((string) $chatId, array_map('strval', config('nilacare.telegram.chat_ids')), true);

        if ($cmd === '/start' || $cmd === '/help') {
            return "Halo! Ini bot <b>NilaCare</b>.\n\nChat ID kamu: <code>$chatId</code>\n"
                .($allowed ? 'Chat ini sudah terdaftar untuk notifikasi alarm.' : 'Salin angka di atas ke <code>TELEGRAM_CHAT_ID</code> di file .env agar menerima notifikasi alarm, lalu restart <code>php artisan mqtt:listen</code>.')
                ."\n\nPerintah: /status · /alarm";
        }
        if (! $allowed) {
            return 'Chat ini belum terdaftar. Kirim /start untuk melihat chat ID.';
        }
        if ($cmd === '/status') {
            return Pond::with('latestReading')->get()->map(function (Pond $p) {
                $r = $p->latestReading;
                if (! $r) {
                    return '<b>'.e($p->name).'</b>: belum ada data.';
                }
                $z = fn ($zone) => ['optimal' => '🟢', 'waspada' => '🟡', 'bahaya' => '🔴'][$zone] ?? '⚪';
                $on = collect($r->actuators ?? [])->filter()->keys()->map(fn ($a) => config("nilacare.actuators.$a", $a))->implode(', ') ?: 'tidak ada';

                return '<b>'.e($p->name).'</b> · '.($p->is_online ? 'online' : 'offline')." · mode {$p->mode}\n"
                    ."{$z($r->zone_do)} DO {$r->do_mg_l} mg/L\n{$z($r->zone_temp)} Suhu {$r->temp_c} °C\n"
                    ."{$z($r->zone_ph)} pH {$r->ph}\n{$z($r->zone_ntu)} Kekeruhan {$r->ntu} NTU\n"
                    ."Level air {$r->level_cm} cm\nAktuator ON: $on\n<i>Data ".$r->received_at->diffForHumans().'</i>';
            })->implode("\n\n") ?: 'Belum ada kolam.';
        }
        if ($cmd === '/alarm') {
            $open = Pond::all()->map(fn (Pond $p) => [$p, $p->openAlarm()])->filter(fn ($x) => $x[1]);

            return $open->isEmpty() ? '✅ Tidak ada alarm aktif.' : $open->map(fn ($x) => "🚨 <b>".e($x[0]->name).'</b>: '.e($x[1]->message)." (sejak ".$x[1]->started_at->format('H:i').')')->implode("\n");
        }

        return 'Perintah tidak dikenal. Coba /status atau /alarm.';
    }
}
