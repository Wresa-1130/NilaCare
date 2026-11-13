<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengirim pesan ke Telegram lewat Bot API. Diam (tidak error) jika token/chat ID belum diisi.
 */
class TelegramNotifier
{
    public function configured(): bool
    {
        return filled(config('nilacare.telegram.token')) && ! empty(config('nilacare.telegram.chat_ids'));
    }

    /** Kirim ke semua chat ID di TELEGRAM_CHAT_ID. Mengembalikan true jika minimal satu berhasil. */
    public function send(string $html): bool
    {
        if (! $this->configured()) {
            return false;
        }
        $ok = false;
        foreach (config('nilacare.telegram.chat_ids') as $chatId) {
            $ok = $this->sendTo($chatId, $html) || $ok;
        }

        return $ok;
    }

    public function sendTo(string|int $chatId, string $html): bool
    {
        try {
            $res = $this->api('sendMessage', ['chat_id' => $chatId, 'text' => $html, 'parse_mode' => 'HTML']);

            return (bool) ($res['ok'] ?? false);
        } catch (Throwable $e) {
            Log::warning('[Telegram] gagal kirim: '.$e->getMessage());

            return false;
        }
    }

    /** Panggil method Bot API apa pun, mis. getMe, getUpdates, sendMessage. */
    public function api(string $method, array $params = [], int $timeout = 10): array
    {
        $token = config('nilacare.telegram.token');

        return Http::timeout($timeout)->asForm()
            ->post("https://api.telegram.org/bot{$token}/{$method}", $params)
            ->json() ?? [];
    }
}
