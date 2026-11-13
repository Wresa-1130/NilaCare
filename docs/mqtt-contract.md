# Kontrak MQTT NilaCare

Sumber: firmware `comms.cpp` di proyek Wokwi. Broker: EMQX Serverless, MQTT over TLS port 8883
(WebSocket TLS 8084, path `/mqtt`). Prefix topik: `nilacare/{pond}` dengan `{pond}` = `POND_ID`
di firmware (default `kolam1`).

## Ringkasan topik

| Topik | Arah | QoS | Retain | Frekuensi |
| --- | --- | --- | --- | --- |
| `nilacare/{pond}/telemetry` | ESP32 → server | 0 | tidak | tiap 30 detik |
| `nilacare/{pond}/event` | ESP32 → server | 0 | tidak | saat terjadi |
| `nilacare/{pond}/status` | ESP32 → server | 1 (LWT) | ya | saat connect / putus |
| `nilacare/{pond}/cmd` | server → ESP32 | 1 | tidak | saat petugas memberi perintah |
| `nilacare/{pond}/config` | server → ESP32 | 1 | **ya** | saat ambang diubah |

ESP32 memakai PubSubClient, sehingga pesan yang dikirim ESP32 berupa QoS 0.

## telemetry

```jsonc
{
  "do": 6.0,              // oksigen terlarut, mg/L (1 desimal)
  "temp": 28.0,           // suhu kolam, °C
  "temp_tank": 28.0,      // suhu tandon, °C
  "ph": 7.5,
  "ntu": 20,              // kekeruhan
  "level_cm": 100,        // tinggi air dari dasar kolam (normal 100)
  "float_min": false,     // true = air di batas minimum
  "zone": { "do": "optimal", "temp": "optimal", "ph": "optimal", "ntu": "optimal" },
  "act": { "aer_a": false, "aer_b": false, "heater": false, "drain": false,
           "fill": false, "dose": false, "shade": false },
  "mode": "auto",         // "auto" | "manual" (manual = ada override aktif)
  "water_change": "idle", // idle | cek | buang | tunggu suhu | isi | verifikasi
  "alarm": ""             // kosong = tidak ada alarm, selain itu pesan alarm
}
```

Nilai zona: `optimal` | `waspada` | `bahaya`.

## event

Semua event memiliki `type` dan `ts` (uptime ESP32 dalam detik — **bukan** waktu nyata).

Perubahan aktuator:

```jsonc
{ "type": "actuator", "name": "aer_a", "state": "on", "reason": "DO < 5", "ts": 1234 }
```

Event lain memakai `detail`:

```jsonc
{ "type": "alarm", "detail": "DO kritis < 3 mg/L", "ts": 1240 }
```

| type | Contoh detail | Arti |
| --- | --- | --- |
| `alarm` | `DO kritis < 3 mg/L`, `kondisi kembali normal`, `dikonfirmasi petugas` | alarm mulai / selesai / di-ack |
| `ganti_air` | `kekeruhan > 60 NTU`, `manual`, `selesai, NTU 20 pH 7.2`, `batas siklus harian tercapai` | siklus ganti air |
| `tahap_ganti_air` | `cek`, `buang`, `tunggu suhu`, `isi`, `verifikasi`, `idle` | perpindahan tahap |
| `dosis` | `pompa dosis 3 detik`, `dosis manual 3 detik` | dosis kapur |
| `pengaman` | `level minimum saat buang`, `timeout pompa buang`, `timeout valve isi` | pengaman terpicu |
| `manual` | `aer_a -> on` | perintah manual diterima |
| `mode` | `manual`, `auto` | mode berubah |
| `config` | `ambang diperbarui` | config diterima |
| `ai` | `aerator dini` | perintah prediksi diterima |

Pesan alarm yang mungkin: `Level air minimum`, `DO kritis < 3 mg/L`, `Suhu di luar 25-32 C`,
`pH di luar 6,5-8,5`, `pH tetap rendah, dosis maks`, `Air keruh, siklus maks`, `Heater menyala > 4 jam`.

## status

Payload teks biasa: `online` atau `offline` (retained; `offline` dikirim broker lewat Last Will).

## cmd

| Payload | Efek |
| --- | --- |
| `{"actuator":"aer_a","state":"on"}` | paksa ON (`state`: `on` \| `off` \| `auto`) |
| `{"mode":"manual"}` | kunci semua aktuator di kondisi sekarang |
| `{"mode":"auto"}` | lepas semua override |
| `{"action":"water_change"}` | mulai siklus ganti air |
| `{"action":"dose"}` | dosis kapur 3 detik |
| `{"action":"ack"}` | konfirmasi alarm (buzzer diam) |
| `{"action":"aerator_early","minutes":60}` | aerator A menyala lebih awal (prediksi) |

Nama aktuator: `aer_a`, `aer_b`, `heater`, `drain`, `fill`, `dose`, `shade`.
Pengaman perangkat tetap berlaku di mode manual: `drain` dan `heater` dipaksa OFF saat
`float_min = true`, dan `fill` tidak bisa ON bersamaan dengan `drain`.

## config (retained)

Kirim hanya key yang berubah; key lain tetap. Nilai default:

| Key | Default | Arti |
| --- | --- | --- |
| `doAOn` / `doAOff` | 5.0 / 6.0 | aerator A ON di bawah / OFF di atas (mg/L) |
| `doBOn` / `doBOff` | 4.0 / 5.0 | aerator B |
| `doCrit` | 3.0 | batas kritis (SNI 7550:2009) |
| `doMinForWaterChange` | 4.0 | ganti air ditunda di bawah nilai ini |
| `tempHeaterOn` / `tempHeaterOff` | 26 / 27 | °C |
| `tempShadeClose` / `tempShadeOpen` | 30 / 28 | °C |
| `tempMin` / `tempMax` | 25 / 32 | batas SNI |
| `tankTempMaxDiff` | 2.0 | selisih suhu tandon–kolam maksimum saat isi |
| `phLow` / `phTarget` / `phHigh` | 6.5 / 6.8 / 8.5 | |
| `ntuHigh` / `ntuClean` | 60 / 40 | NTU |
| `doseMaxPerDay` | 4 | integer |
| `waterChangeMaxPerDay` | 3 | integer |
