<?php

namespace App\Console\Commands;

use App\Services\Mqtt\ClientFactory;
use Illuminate\Console\Command;
use PhpMqtt\Client\Contracts\MqttClient;
use Throwable;

/**
 * Simulasi ESP32 NilaCare tanpa Wokwi: mengirim telemetry/event dengan format yang sama
 * seperti firmware, menjalankan aturan ON/OFF sederhana, dan merespons cmd/config.
 * Dipakai untuk mengembangkan & menguji web saat Wokwi tidak dijalankan.
 */
class FakeDevice extends Command
{
    protected $signature = 'nilacare:fake-device
        {--pond=kolam1 : ID kolam (POND_ID)}
        {--scenario=normal : normal | do-drop | do-kritis | keruh | panas | dingin | asam}
        {--tick=2 : detik per siklus simulasi}
        {--telemetry=5 : kirim telemetry tiap N siklus}';

    protected $description = 'Simulasikan perangkat ESP32 NilaCare (untuk testing tanpa Wokwi)';

    private MqttClient $client;
    private string $prefix;

    // Kondisi air
    private array $s = ['do' => 6.2, 'temp' => 28.5, 'temp_tank' => 28.0, 'ph' => 7.4, 'ntu' => 22, 'level_cm' => 100, 'float_min' => false];
    // Keputusan otomatis, override manual (null = otomatis), status akhir
    private array $auto = [];
    private array $ovr = [];
    private array $act = [];
    private array $onSince = [];
    private array $th = [];
    private string $wc = 'idle';
    private float $wcStart = 100;
    private int $tick = 0;
    private int $lastDoseTick = -999;
    private int $doseUntil = -1;
    private int $aiUntil = -1; // aerator dini (perintah AI) aktif sampai siklus ini
    private string $doseReason = '';
    private string $alarm = '';
    private bool $alarmAck = false;

    public function handle(): int
    {
        $pond = $this->option('pond');
        $this->prefix = config('nilacare.topic_prefix')."/$pond/";
        $this->th = collect(config('nilacare.thresholds'))->map(fn ($t) => $t['default'])->all();
        foreach (array_keys(config('nilacare.actuators')) as $a) {
            $this->auto[$a] = false;
            $this->ovr[$a] = null;
            $this->act[$a] = false;
            $this->onSince[$a] = 0;
        }

        try {
            [$this->client, $settings] = ClientFactory::make('nilacare-fake-'.$pond.'-'.substr(md5(uniqid()), 0, 6), $this->prefix.'status', 'offline');
            $this->client->connect($settings, true);
        } catch (Throwable $e) {
            $this->error('Gagal terhubung ke broker: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->client->publish($this->prefix.'status', 'online', \PhpMqtt\Client\MqttClient::QOS_AT_LEAST_ONCE, true);
        $this->client->subscribe($this->prefix.'cmd', fn ($t, $m) => $this->onCmd($m), \PhpMqtt\Client\MqttClient::QOS_AT_LEAST_ONCE);
        $this->client->subscribe($this->prefix.'config', fn ($t, $m) => $this->onConfig($m), \PhpMqtt\Client\MqttClient::QOS_AT_LEAST_ONCE);
        $this->info("Fake device '$pond' terhubung. Skenario: ".$this->option('scenario').'. Ctrl+C untuk berhenti.');

        $tickSec = max(1, (int) $this->option('tick'));
        $last = 0;
        $this->client->registerLoopEventHandler(function () use (&$last, $tickSec) {
            if (microtime(true) - $last >= $tickSec) {
                $last = microtime(true);
                $this->step();
            }
        });
        $this->client->loop(true);

        return self::SUCCESS;
    }

    /** Satu siklus: ubah kondisi air sesuai skenario, jalankan aturan, kirim data. */
    private function step(): void
    {
        $this->tick++;
        $this->simulateWater();
        $this->rules();
        $this->apply();
        $this->checkAlarm();
        if ($this->tick % max(1, (int) $this->option('telemetry')) === 1 || (int) $this->option('telemetry') === 1) {
            $this->publishTelemetry();
        }
    }

    private function simulateWater(): void
    {
        $s = &$this->s;
        $j = fn (float $amp) => (mt_rand(-100, 100) / 100) * $amp;
        $sc = $this->option('scenario');

        // Kecenderungan per skenario
        $doDrift = match ($sc) { 'do-drop' => -0.12, 'do-kritis' => -0.35, default => ($s['do'] < 6.2 ? 0.03 : -0.03) };
        $doDrift += ($this->act['aer_a'] ? 0.07 : 0) + ($this->act['aer_b'] ? 0.12 : 0);
        if ($sc === 'do-kritis' && $this->tick > 25) {
            $doDrift += 0.3; // setelah beberapa saat, kondisi alam membaik
        }
        $s['do'] = $this->clamp($s['do'] + $doDrift + $j(0.03), 1.5, 9);

        $tempDrift = match ($sc) { 'panas' => 0.25, 'dingin' => -0.2, default => ($s['temp'] < 28.5 ? 0.03 : -0.03) };
        $tempDrift += ($this->act['shade'] ? -0.35 : 0) + ($this->act['heater'] ? 0.3 : 0);
        $s['temp'] = $this->clamp($s['temp'] + $tempDrift + $j(0.05), 22, 35);
        $s['temp_tank'] = $this->clamp(28 + $j(0.2), 22, 35);

        $phDrift = $sc === 'asam' ? -0.06 : ($s['ph'] < 7.4 ? 0.01 : -0.01);
        if ($this->tick <= $this->doseUntil) {
            $phDrift += 0.12;
        }
        $s['ph'] = $this->clamp($s['ph'] + $phDrift + $j(0.02), 5, 9.5);

        $ntuDrift = $sc === 'keruh' && $this->wc === 'idle' ? 3.0 : ($s['ntu'] > 22 ? -0.2 : 0.1);
        if ($this->wc === 'isi') {
            $ntuDrift = -8;
        }
        $s['ntu'] = $this->clamp($s['ntu'] + $ntuDrift + $j(0.5), 5, 150);

        if ($this->act['drain']) {
            $s['level_cm'] -= 4;
        }
        if ($this->act['fill']) {
            $s['level_cm'] = min(100, $s['level_cm'] + 4);
        }
    }

    /** Aturan ON/OFF sederhana dengan ambang yang sama seperti firmware. */
    private function rules(): void
    {
        $s = $this->s;
        $th = $this->th;
        $minOn = 5; // siklus minimum ON (setara durasi minimum aerator)

        if ($s['do'] < $th['doBOn']) {
            $this->setAuto('aer_b', true, 'DO < '.$th['doBOn']);
        } elseif ($s['do'] >= $th['doBOff'] && $this->tick - $this->onSince['aer_b'] >= $minOn) {
            $this->setAuto('aer_b', false, '');
        }

        $needA = $s['do'] < $th['doAOn'] || $s['temp'] >= $th['tempShadeClose'] || $this->wc !== 'idle' || $this->tick <= $this->aiUntil;
        if ($needA) {
            $this->setAuto('aer_a', true, $s['do'] < $th['doAOn'] ? 'DO < '.$th['doAOn'] : ($this->tick <= $this->aiUntil ? 'aerator dini' : ($this->wc !== 'idle' ? 'ganti air' : 'suhu >= '.$th['tempShadeClose'])));
        } elseif ($s['do'] >= $th['doAOff'] && $s['temp'] <= $th['tempShadeOpen'] && $this->tick - $this->onSince['aer_a'] >= $minOn) {
            $this->setAuto('aer_a', false, '');
        }

        if ($s['temp'] < $th['tempHeaterOn']) {
            $this->setAuto('heater', true, 'suhu < '.$th['tempHeaterOn']);
        } elseif ($s['temp'] >= $th['tempHeaterOff']) {
            $this->setAuto('heater', false, '');
        }

        if ($s['temp'] >= $th['tempShadeClose'] && ! $this->act['heater']) {
            $this->setAuto('shade', true, 'suhu >= '.$th['tempShadeClose']);
        } elseif ($s['temp'] <= $th['tempShadeOpen']) {
            $this->setAuto('shade', false, '');
        }

        // Dosis kapur: 1 siklus ON, lock-out 6 siklus
        if ($s['ph'] < $th['phLow'] && $this->tick - $this->lastDoseTick >= 6) {
            $this->lastDoseTick = $this->tick;
            $this->doseUntil = $this->tick + 1;
            $this->doseReason = 'pH < '.$th['phLow'];
            $this->event('dosis', 'pompa dosis 3 detik');
        }
        $this->setAuto('dose', $this->tick <= $this->doseUntil, $this->doseReason);

        // Siklus ganti air
        if ($this->wc === 'idle' && ($s['ntu'] > $th['ntuHigh'] || $s['ph'] > $th['phHigh'])) {
            $this->startWaterChange($s['ntu'] > $th['ntuHigh'] ? 'kekeruhan > '.$th['ntuHigh'].' NTU' : 'pH > '.$th['phHigh']);
        }
        if ($this->wc === 'buang' && $this->s['level_cm'] <= $this->wcStart - 20) {
            $this->setWc('isi');
        } elseif ($this->wc === 'isi' && $this->s['level_cm'] >= 100) {
            $this->setWc('verifikasi');
        } elseif ($this->wc === 'verifikasi') {
            $this->event('ganti_air', sprintf('selesai, NTU %.0f pH %.1f', $this->s['ntu'], $this->s['ph']));
            $this->setWc('idle');
        }
        $this->setAuto('drain', $this->wc === 'buang', 'ganti air: buang');
        $this->setAuto('fill', $this->wc === 'isi', 'ganti air: isi');
    }

    private function startWaterChange(string $reason): void
    {
        $this->event('ganti_air', $reason);
        $this->wcStart = $this->s['level_cm'];
        $this->setWc('buang');
    }

    private function setWc(string $state): void
    {
        $this->wc = $state;
        $this->event('tahap_ganti_air', $state);
    }

    private function setAuto(string $a, bool $on, string $reason): void
    {
        if ($on && ! $this->auto[$a]) {
            $this->onSince[$a] = $this->tick;
        }
        $this->auto[$a] = $on;
        $this->auto[$a.'_reason'] = $reason;
    }

    /** Terapkan override manual + pengaman, kirim event jika status berubah. */
    private function apply(): void
    {
        foreach (array_keys($this->act) as $a) {
            $want = $this->ovr[$a] === null ? $this->auto[$a] : $this->ovr[$a];
            $reason = $this->ovr[$a] === null ? ($this->auto[$a.'_reason'] ?? '-') : 'manual';
            if ($a === 'fill' && $want && $this->act['drain']) {
                $want = false;
                $reason = 'pengaman: interlock';
            }
            if ($want !== $this->act[$a]) {
                $this->act[$a] = $want;
                $this->publish('event', ['type' => 'actuator', 'name' => $a, 'state' => $want ? 'on' : 'off', 'reason' => $want ? $reason : ($this->ovr[$a] === null ? 'kondisi normal' : 'manual'), 'ts' => $this->tick]);
                $this->line(sprintf('  [AKSI] %-6s -> %s (%s)', $a, $want ? 'ON' : 'OFF', $reason));
            }
        }
    }

    private function zones(): array
    {
        $s = $this->s;
        $th = $this->th;

        return [
            'do' => $s['do'] >= 5 ? 'optimal' : ($s['do'] >= $th['doCrit'] ? 'waspada' : 'bahaya'),
            'temp' => ($s['temp'] >= 27 && $s['temp'] <= 30) ? 'optimal' : (($s['temp'] >= $th['tempMin'] && $s['temp'] <= $th['tempMax']) ? 'waspada' : 'bahaya'),
            'ph' => ($s['ph'] >= 7 && $s['ph'] <= 8) ? 'optimal' : (($s['ph'] >= $th['phLow'] && $s['ph'] <= $th['phHigh']) ? 'waspada' : 'bahaya'),
            'ntu' => $s['ntu'] < $th['ntuClean'] ? 'optimal' : ($s['ntu'] <= $th['ntuHigh'] ? 'waspada' : 'bahaya'),
        ];
    }

    private function checkAlarm(): void
    {
        $z = $this->zones();
        $msg = match (true) {
            $z['do'] === 'bahaya' => 'DO kritis < 3 mg/L',
            $z['temp'] === 'bahaya' => 'Suhu di luar 25-32 C',
            $z['ph'] === 'bahaya' => 'pH di luar 6,5-8,5',
            default => '',
        };
        if ($msg && ! $this->alarm) {
            $this->event('alarm', $msg);
        } elseif (! $msg && $this->alarm) {
            $this->event('alarm', 'kondisi kembali normal');
            $this->alarmAck = false;
        }
        $this->alarm = $msg;
    }

    private function publishTelemetry(): void
    {
        $s = $this->s;
        $payload = [
            'do' => round($s['do'], 1), 'temp' => round($s['temp'], 1), 'temp_tank' => round($s['temp_tank'], 1),
            'ph' => round($s['ph'], 1), 'ntu' => round($s['ntu']), 'level_cm' => round($s['level_cm']),
            'float_min' => false, 'zone' => $this->zones(), 'act' => $this->act,
            'mode' => in_array(true, array_map(fn ($v) => $v !== null, $this->ovr), true) ? 'manual' : 'auto',
            'water_change' => $this->wc, 'alarm' => $this->alarm,
        ];
        $this->publish('telemetry', $payload, 0);
        $this->line(sprintf('<fg=gray>%s</> telemetry DO=%.1f T=%.1f pH=%.1f NTU=%d Lv=%d', now()->format('H:i:s'), $payload['do'], $payload['temp'], $payload['ph'], $payload['ntu'], $payload['level_cm']));
    }

    private function onCmd(string $message): void
    {
        $d = json_decode($message, true) ?: [];
        $this->line('  <fg=yellow>[CMD]</> '.$message);
        if (isset($d['mode'])) {
            foreach (array_keys($this->ovr) as $a) {
                $this->ovr[$a] = $d['mode'] === 'manual' ? $this->act[$a] : null;
            }
            $this->event('mode', $d['mode']);
        }
        if (isset($d['actuator']) && array_key_exists($d['actuator'], $this->ovr)) {
            $state = $d['state'] ?? 'auto';
            $this->ovr[$d['actuator']] = $state === 'on' ? true : ($state === 'off' ? false : null);
            $this->event('manual', $d['actuator'].' -> '.$state);
        }
        match ($d['action'] ?? null) {
            'water_change' => $this->wc === 'idle' ? $this->startWaterChange('manual') : null,
            'dose' => [$this->lastDoseTick = $this->tick, $this->doseUntil = $this->tick + 1, $this->doseReason = 'dosis manual', $this->event('dosis', 'dosis manual 3 detik')],
            'ack' => [$this->alarmAck = true, $this->event('alarm', 'dikonfirmasi petugas')],
            'aerator_early' => [$this->aiUntil = $this->tick + (int) ceil(($d['minutes'] ?? 60) / max(1, (int) $this->option('tick'))), $this->event('ai', 'aerator dini')], // TIME_SCALE 60: 1 menit = 1 detik
            default => null,
        };
        $this->apply();
    }

    private function onConfig(string $message): void
    {
        $d = json_decode($message, true) ?: [];
        foreach ($d as $k => $v) {
            if (array_key_exists($k, $this->th) && is_numeric($v)) {
                $this->th[$k] = $v + 0;
            }
        }
        $this->event('config', 'ambang diperbarui');
    }

    private function event(string $type, string $detail): void
    {
        $this->publish('event', ['type' => $type, 'detail' => $detail, 'ts' => $this->tick]);
        $this->line("  <fg=cyan>[EVENT]</> $type: $detail");
    }

    private function publish(string $leaf, array $payload, int $qos = 0): void
    {
        $this->client->publish($this->prefix.$leaf, json_encode($payload), $qos);
    }

    private function clamp(float $v, float $min, float $max): float
    {
        return max($min, min($max, $v));
    }
}
