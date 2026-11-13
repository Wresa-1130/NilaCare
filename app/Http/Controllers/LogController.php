<?php

namespace App\Http\Controllers;

use App\Models\ActuatorEvent;
use App\Models\Alarm;
use App\Models\CommandLog;
use App\Models\DeviceEvent;
use App\Models\Pond;
use App\Models\SensorReading;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Riwayat aksi, perintah, alarm, dan ekspor CSV (bahan Bab 4 laporan). */
class LogController extends Controller
{
    public function index(Pond $pond)
    {
        [$from, $to] = $this->range();

        return view('logs', [
            'pond' => $pond,
            'ponds' => Pond::orderBy('code')->get(),
            'from' => $from, 'to' => $to,
            'actuatorEvents' => ActuatorEvent::where('pond_id', $pond->id)->whereBetween('received_at', [$from, $to])->latest('received_at')->limit(300)->get(),
            'deviceEvents' => DeviceEvent::where('pond_id', $pond->id)->whereBetween('received_at', [$from, $to])->latest('received_at')->limit(300)->get(),
            'commands' => CommandLog::with('user')->where('pond_id', $pond->id)->whereBetween('sent_at', [$from, $to])->latest('sent_at')->limit(300)->get(),
            'alarms' => Alarm::with('acknowledger')->where('pond_id', $pond->id)->whereBetween('started_at', [$from, $to])->latest('started_at')->limit(300)->get(),
            'readingCount' => SensorReading::where('pond_id', $pond->id)->whereBetween('received_at', [$from, $to])->count(),
        ]);
    }

    /** Ekspor CSV: type = readings | actuators | events | commands | alarms. */
    public function export(Pond $pond, string $type): StreamedResponse
    {
        [$from, $to] = $this->range();
        [$header, $query, $map] = match ($type) {
            'readings' => [['waktu', 'do_mg_l', 'suhu_c', 'suhu_tandon_c', 'ph', 'ntu', 'level_cm', 'zona_do', 'zona_suhu', 'zona_ph', 'zona_ntu', 'mode', 'ganti_air', 'alarm'],
                SensorReading::where('pond_id', $pond->id)->whereBetween('received_at', [$from, $to])->orderBy('received_at'),
                fn ($r) => [$r->received_at, $r->do_mg_l, $r->temp_c, $r->temp_tank_c, $r->ph, $r->ntu, $r->level_cm, $r->zone_do, $r->zone_temp, $r->zone_ph, $r->zone_ntu, $r->mode, $r->water_change_state, $r->alarm]],
            'actuators' => [['waktu', 'aktuator', 'status', 'alasan'],
                ActuatorEvent::where('pond_id', $pond->id)->whereBetween('received_at', [$from, $to])->orderBy('received_at'),
                fn ($e) => [$e->received_at, $e->actuator, $e->state, $e->reason]],
            'events' => [['waktu', 'jenis', 'detail'],
                DeviceEvent::where('pond_id', $pond->id)->whereBetween('received_at', [$from, $to])->orderBy('received_at'),
                fn ($e) => [$e->received_at, $e->type, $e->detail]],
            'commands' => [['dikirim', 'petugas', 'topik', 'perintah', 'payload', 'status', 'dikonfirmasi', 'latensi_detik'],
                CommandLog::with('user')->where('pond_id', $pond->id)->whereBetween('sent_at', [$from, $to])->orderBy('sent_at'),
                fn ($c) => [$c->sent_at, $c->user?->name, $c->topic, $c->summary, json_encode($c->payload), $c->status, $c->confirmed_at, $c->confirmed_at ? round($c->sent_at->diffInMilliseconds($c->confirmed_at) / 1000, 2) : null]],
            'alarms' => [['mulai', 'selesai', 'pesan', 'durasi_menit', 'dikonfirmasi', 'notifikasi_telegram'],
                Alarm::where('pond_id', $pond->id)->whereBetween('started_at', [$from, $to])->orderBy('started_at'),
                fn ($a) => [$a->started_at, $a->ended_at, $a->message, $a->ended_at ? round($a->started_at->diffInSeconds($a->ended_at) / 60, 1) : null, $a->acknowledged_at, $a->notified_at]],
            default => abort(404),
        };

        return response()->streamDownload(function () use ($header, $query, $map) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);
            $query->chunk(500, fn ($rows) => $rows->each(fn ($row) => fputcsv($out, $map($row))));
            fclose($out);
        }, "nilacare-{$pond->code}-{$type}-".now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function range(): array
    {
        $from = request('from') ? Carbon::parse(request('from'))->startOfDay() : now()->subDay()->startOfDay();
        $to = request('to') ? Carbon::parse(request('to'))->endOfDay() : now()->endOfDay();

        return [$from, $to];
    }
}
