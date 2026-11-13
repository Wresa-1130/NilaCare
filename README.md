# NilaCare Web

Dashboard monitoring dan kendali kualitas air kolam nila merah. Dibuat dengan Laravel 12 dan berkomunikasi dengan ESP32 (Wokwi) lewat MQTT (EMQX Serverless). Notifikasi alarm dikirim ke Telegram.

```
ESP32 (Wokwi) ──MQTT/TLS──► EMQX ◄──MQTT/TLS── php artisan mqtt:listen ──► database ──► dashboard
                                   ◄────────── dashboard (perintah manual / ambang)
                                                  mqtt:listen ──► Telegram (alarm, offline)
```

## Kebutuhan

- PHP 8.2+ dengan ekstensi `openssl`, `pdo_mysql` (atau `pdo_sqlite`), `mbstring`, `curl`. XAMPP 8.2+ atau Laragon sudah cukup.
- Composer 2
- MySQL/MariaDB (atau SQLite)
- Tidak perlu Node/npm. Chart.js dimuat dari CDN.

## Instalasi

```bash
composer install
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate
# buat database "nilacare" di phpMyAdmin, lalu isi bagian MQTT & Telegram di .env
php artisan migrate --seed
php artisan nilacare:check    # cek database, broker MQTT, dan Telegram
```

Mau pakai SQLite tanpa MySQL? Set `DB_CONNECTION=sqlite` di `.env`, hapus baris `DB_DATABASE`, buat file kosong `database/database.sqlite`, lalu jalankan `migrate --seed`.

## Menjalankan (3 terminal)

| Terminal | Perintah | Fungsi |
| --- | --- | --- |
| 1 | `php artisan serve` | Web di http://127.0.0.1:8000 |
| 2 | `php artisan mqtt:listen` | Terima data ESP32, simpan, buka/tutup alarm, kirim Telegram. **Wajib jalan.** |
| 3 | `php artisan nilacare:telegram` | Bot menjawab /start, /status, /alarm (opsional) |

Login: `petugas@nilacare.test` / `nilacare123` (ubah di `.env` sebelum `db:seed`).

## Uji tanpa Wokwi

```bash
php artisan nilacare:fake-device --scenario=do-drop
```

Skenario yang tersedia: `normal`, `do-drop`, `do-kritis`, `keruh`, `panas`, `dingin`, `asam`. Perangkat palsu ini menjalankan aturan yang sama dengan firmware (1 detik = 1 menit) dan menjawab perintah dari dashboard. Matikan dulu simulasi Wokwi kalau ID kolamnya sama.

## Perintah artisan

| Perintah | Keterangan |
| --- | --- |
| `mqtt:listen` | Listener MQTT (subscribe `nilacare/+/telemetry\|event\|status`) |
| `nilacare:telegram` | Bot Telegram mode long-polling (tidak perlu webhook/URL publik) |
| `nilacare:check [--telegram]` | Cek koneksi; `--telegram` mengirim pesan uji |
| `nilacare:fake-device` | Simulator ESP32 untuk testing |

## Struktur penting

```
app/Console/Commands/      mqtt:listen, nilacare:telegram, nilacare:check, nilacare:fake-device
app/Services/Mqtt/         MessageRouter + handler telemetry/event/status, MqttPublisher
app/Services/              AlarmService, CommandService, ThresholdService, PondStatusService, TelegramNotifier
app/Http/Controllers/      Dashboard, Control (aktuator/mode/aksi), Threshold, Log (riwayat + CSV)
config/nilacare.php        Nama aktuator dan ambang default (sama dengan firmware)
config/mqtt-client.php     Koneksi "default" (listener) dan "publisher" (web)
public/js/dashboard.js     Polling data 5 detik + grafik
```

Kontrak topik dan payload MQTT ada di `docs/mqtt-contract.md`.
