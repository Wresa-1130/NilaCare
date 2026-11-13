# NilaCare Web: catatan untuk Claude Code

Lapisan aplikasi sistem IoT NilaCare (kualitas air kolam nila merah). Web menerima data ESP32 lewat MQTT, menyimpannya, menampilkan dashboard, mengirim notifikasi Telegram, dan meneruskan perintah petugas ke perangkat. Aplikasi ini sudah jadi dan teruji. Ubah seperlunya saja.

## Prinsip yang tidak boleh dilanggar

- **Keputusan kendali dibuat di ESP32.** Web hanya memantau, mencatat, memberi notifikasi, dan mengirim perintah manual atau ambang.
- **Tidak ada mode darurat otomatis.** Petugas harus selalu bisa intervensi manual. Satu-satunya pengaman ada di firmware: pompa buang dan heater dimatikan saat level air minimum.
- **Tidak ada feeder.** Fokus proyek ini kualitas air.
- `docs/mqtt-contract.md` adalah sumber kebenaran payload. Jangan ubah format tanpa mengubah firmware (`nilacare-wokwi/comms.cpp`) dan `FakeDevice`.
- Ambang default di `config/nilacare.php` harus sama dengan `nilacare-wokwi/config.h`.
- Kredensial hanya disimpan di `.env`, jangan pernah di kode.

## Stack

- Laravel 12, PHP 8.2+, MySQL atau SQLite
- Blade + CSS biasa (`public/css/nilacare.css`) + JS vanilla (`public/js/dashboard.js`) + Chart.js dari CDN. Tidak ada build npm.
- `php-mqtt/laravel-client`
- Telegram lewat `Http::`
- Zona waktu Asia/Makassar, UI berbahasa Indonesia.

## Alur

- `mqtt:listen`: subscribe ke `MessageRouter`, yang meneruskan ke `TelemetryHandler`, `EventHandler`, atau `StatusHandler`. Dari sana data diproses oleh `AlarmService`/`PondStatusService`, lalu `TelegramNotifier`.
- Request web masuk ke `ControlController`/`ThresholdController`, lalu `CommandService` mencatat `CommandLog` (status `sent`) dan `MqttPublisher` mengirimnya. Begitu event konfirmasi dari ESP32 diterima, `EventHandler::confirmCommand()` mengubah status menjadi `confirmed` dan latensinya terekam dalam milidetik.
- Pond dibuat otomatis dari segmen topik `nilacare/{pond}/...`.

## Koneksi MQTT

- `default` dipakai oleh listener: client ID tetap, clean session `false` (wajib untuk auto-reconnect).
- `publisher` dipakai oleh web: client ID acak, supaya tidak memutus listener.

## Perintah

```
php artisan serve | mqtt:listen | nilacare:telegram | nilacare:check [--telegram]
php artisan nilacare:fake-device --scenario=normal|do-drop|do-kritis|keruh|panas|dingin|asam
```

## Gaya kerja

Kerjakan bertahap dan modular. Komentar singkat ditulis dalam Bahasa Indonesia. Setelah mengubah alur MQTT, uji dengan `nilacare:fake-device` sambil `mqtt:listen` berjalan.
