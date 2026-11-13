<?php

namespace App\Http\Controllers;

use App\Models\Pond;
use App\Models\SensorReading;

/**
 * Halaman dashboard + endpoint JSON yang dipanggil JavaScript tiap 5 detik.
 */
class DashboardController extends Controller
{
    public function index(?Pond $pond = null)
    {
        $pond ??= Pond::where('code', config('nilacare.default_pond'))->first() ?? Pond::first();
        abort_if(! $pond, 404, 'Belum ada kolam. Jalankan php artisan db:seed.');

        return view('dashboard', ['pond' => $pond, 'ponds' => Pond::orderBy('code')->get()]);
    }

    /** Kondisi terkini: nilai sensor, zona, aktuator, alarm, event & perintah terakhir. */
    public function live(Pond $pond)
    {
        $r = $pond->latestReading;
        $alarm = $pond->openAlarm();
        $online = $pond->is_online && $pond->last_seen_at?->gt(now()->subSeconds(config('nilacare.offline_after')));

        $actuatorEvents = $pond->actuatorEvents()->latest('received_at')->limit(15)->get()
            ->map(fn ($e) => ['time' => $e->received_at, 'kind' => 'actuator', 'text' => config("nilacare.actuators.{$e->actuator}", $e->actuator).' '.strtoupper($e->state).($e->reason ? " · {$e->reason}" : '')]);
        $deviceEvents = $pond->deviceEvents()->where('type', '!=', 'tahap_ganti_air')->latest('received_at')->limit(15)->get()
            ->map(fn ($e) => ['time' => $e->received_at, 'kind' => $e->type, 'text' => $this->eventLabel($e->type).': '.$e->detail]);

        return response()->json([
            'pond' => [
                'code' => $pond->code,
                'name' => $pond->name,
                'online' => (bool) $online,
                'last_seen' => $pond->last_seen_at?->diffForHumans(),
                'mode' => $pond->mode,
            ],
            'reading' => $r ? [
                'do' => $r->do_mg_l, 'temp' => $r->temp_c, 'temp_tank' => $r->temp_tank_c, 'ph' => $r->ph,
                'ntu' => $r->ntu, 'level' => $r->level_cm, 'float_min' => $r->float_min,
                'zone' => ['do' => $r->zone_do, 'temp' => $r->zone_temp, 'ph' => $r->zone_ph, 'ntu' => $r->zone_ntu],
                'actuators' => $r->actuators,
                'water_change' => $r->water_change_state,
                'received_at' => $r->received_at->format('H:i:s'),
                'age' => $r->received_at->diffForHumans(),
            ] : null,
            'alarm' => $alarm ? [
                'message' => $alarm->message,
                'since' => $alarm->started_at->format('H:i'),
                'acknowledged' => (bool) $alarm->acknowledged_at,
            ] : null,
            'events' => $actuatorEvents->concat($deviceEvents)->sortByDesc('time')->take(15)->values()
                ->map(fn ($e) => ['time' => $e['time']->format('H:i:s'), 'kind' => $e['kind'], 'text' => $e['text']]),
            'commands' => $pond->commandLogs()->with('user')->latest('sent_at')->limit(6)->get()->map(fn ($c) => [
                'time' => $c->sent_at->format('H:i:s'),
                'summary' => $c->summary,
                'status' => $c->status,
                'user' => $c->user?->name,
                'error' => $c->error,
            ]),
        ]);
    }

    /** Riwayat untuk grafik (default 6 jam terakhir, maks. 1.500 titik). */
    public function history(Pond $pond)
    {
        $hours = min(72, max(1, (int) request('hours', 6)));
        $rows = SensorReading::where('pond_id', $pond->id)->where('received_at', '>=', now()->subHours($hours))
            ->orderBy('received_at')->get(['received_at', 'do_mg_l', 'temp_c', 'ph', 'ntu', 'level_cm']);
        $step = max(1, (int) ceil($rows->count() / 1500));
        $rows = $rows->values()->filter(fn ($r, $i) => $i % $step === 0)->values();

        return response()->json([
            'labels' => $rows->map(fn ($r) => $r->received_at->format('H:i')),
            'do' => $rows->pluck('do_mg_l'),
            'temp' => $rows->pluck('temp_c'),
            'ph' => $rows->pluck('ph'),
            'ntu' => $rows->pluck('ntu'),
            'level' => $rows->pluck('level_cm'),
        ]);
    }

    private function eventLabel(string $type): string
    {
        return [
            'alarm' => 'Alarm', 'ganti_air' => 'Ganti air', 'dosis' => 'Dosis', 'pengaman' => 'Pengaman',
            'manual' => 'Manual', 'mode' => 'Mode', 'config' => 'Ambang', 'ai' => 'Prediksi',
        ][$type] ?? ucfirst($type);
    }
}
