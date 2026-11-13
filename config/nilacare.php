<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi NilaCare
|--------------------------------------------------------------------------
| Nilai ambang default harus sama dengan firmware (config.h -> struct Thresholds).
*/

return [

    // Prefix topik MQTT: nilacare/{kolam}/{telemetry|event|status|cmd|config}
    'topic_prefix' => env('NILACARE_TOPIC_PREFIX', 'nilacare'),

    // Kolam default yang dibuka dashboard (sama dengan POND_ID di firmware)
    'default_pond' => env('NILACARE_DEFAULT_POND', 'kolam1'),

    // Perangkat dianggap offline jika tidak ada telemetry selama N detik
    'offline_after' => (int) env('NILACARE_OFFLINE_AFTER', 90),

    'telegram' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        // Satu atau beberapa chat ID dipisah koma
        'chat_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('TELEGRAM_CHAT_ID', ''))))),
    ],

    // Nama tampilan aktuator (kunci = nama di payload MQTT)
    'actuators' => [
        'aer_a' => 'Aerator A',
        'aer_b' => 'Aerator B',
        'heater' => 'Heater',
        'shade' => 'Paranet',
        'drain' => 'Pompa buang',
        'fill' => 'Valve isi',
        'dose' => 'Pompa dosis',
    ],

    // Ambang default (sama dengan firmware). Label & batas validasi untuk form.
    'thresholds' => [
        'doAOn' => ['default' => 5.0, 'label' => 'Aerator A ON jika DO di bawah', 'unit' => 'mg/L', 'group' => 'Oksigen terlarut (DO)'],
        'doAOff' => ['default' => 6.0, 'label' => 'Aerator A OFF jika DO di atas', 'unit' => 'mg/L', 'group' => 'Oksigen terlarut (DO)'],
        'doBOn' => ['default' => 4.0, 'label' => 'Aerator B ON jika DO di bawah', 'unit' => 'mg/L', 'group' => 'Oksigen terlarut (DO)'],
        'doBOff' => ['default' => 5.0, 'label' => 'Aerator B OFF jika DO di atas', 'unit' => 'mg/L', 'group' => 'Oksigen terlarut (DO)'],
        'doCrit' => ['default' => 3.0, 'label' => 'Batas kritis DO (SNI)', 'unit' => 'mg/L', 'group' => 'Oksigen terlarut (DO)'],
        'doMinForWaterChange' => ['default' => 4.0, 'label' => 'Ganti air ditunda jika DO di bawah', 'unit' => 'mg/L', 'group' => 'Oksigen terlarut (DO)'],
        'tempHeaterOn' => ['default' => 26.0, 'label' => 'Heater ON jika suhu di bawah', 'unit' => '°C', 'group' => 'Suhu'],
        'tempHeaterOff' => ['default' => 27.0, 'label' => 'Heater OFF jika suhu mencapai', 'unit' => '°C', 'group' => 'Suhu'],
        'tempShadeClose' => ['default' => 30.0, 'label' => 'Paranet tutup jika suhu mencapai', 'unit' => '°C', 'group' => 'Suhu'],
        'tempShadeOpen' => ['default' => 28.0, 'label' => 'Paranet buka jika suhu turun ke', 'unit' => '°C', 'group' => 'Suhu'],
        'tempMin' => ['default' => 25.0, 'label' => 'Batas bawah suhu (SNI)', 'unit' => '°C', 'group' => 'Suhu'],
        'tempMax' => ['default' => 32.0, 'label' => 'Batas atas suhu (SNI)', 'unit' => '°C', 'group' => 'Suhu'],
        'tankTempMaxDiff' => ['default' => 2.0, 'label' => 'Selisih suhu tandon–kolam maksimum saat isi', 'unit' => '°C', 'group' => 'Suhu'],
        'phLow' => ['default' => 6.5, 'label' => 'Dosis kapur jika pH di bawah', 'unit' => '', 'group' => 'pH'],
        'phTarget' => ['default' => 6.8, 'label' => 'Dosis berhenti jika pH mencapai', 'unit' => '', 'group' => 'pH'],
        'phHigh' => ['default' => 8.5, 'label' => 'Ganti air jika pH di atas', 'unit' => '', 'group' => 'pH'],
        'ntuHigh' => ['default' => 60.0, 'label' => 'Ganti air jika kekeruhan di atas', 'unit' => 'NTU', 'group' => 'Kekeruhan'],
        'ntuClean' => ['default' => 40.0, 'label' => 'Air dianggap bersih di bawah', 'unit' => 'NTU', 'group' => 'Kekeruhan'],
        'doseMaxPerDay' => ['default' => 4, 'label' => 'Maksimum dosis kapur per hari', 'unit' => 'kali', 'group' => 'Batas harian', 'integer' => true],
        'waterChangeMaxPerDay' => ['default' => 3, 'label' => 'Maksimum ganti air per hari', 'unit' => 'kali', 'group' => 'Batas harian', 'integer' => true],
    ],

];
