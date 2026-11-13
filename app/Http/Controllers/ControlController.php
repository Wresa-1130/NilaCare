<?php

namespace App\Http\Controllers;

use App\Models\Pond;
use App\Services\CommandService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Kendali manual dari dashboard. Setiap perintah dicatat lalu dikirim ke topik cmd.
 */
class ControlController extends Controller
{
    public function __construct(private CommandService $commands) {}

    public function actuator(Request $request, Pond $pond)
    {
        $data = $request->validate([
            'actuator' => ['required', Rule::in(array_keys(config('nilacare.actuators')))],
            'state' => ['required', Rule::in(['on', 'off', 'auto'])],
        ]);

        return $this->result($this->commands->actuator($pond, $request->user(), $data['actuator'], $data['state']));
    }

    public function mode(Request $request, Pond $pond)
    {
        $data = $request->validate(['mode' => ['required', Rule::in(['auto', 'manual'])]]);

        return $this->result($this->commands->mode($pond, $request->user(), $data['mode']));
    }

    public function action(Request $request, Pond $pond)
    {
        $data = $request->validate(['action' => ['required', Rule::in(['water_change', 'dose', 'ack', 'aerator_early'])]]);
        $extra = $data['action'] === 'aerator_early' ? ['minutes' => 60] : [];

        return $this->result($this->commands->action($pond, $request->user(), $data['action'], $extra));
    }

    private function result($log)
    {
        return response()->json([
            'ok' => $log->status !== 'failed',
            'message' => $log->status === 'failed' ? 'Gagal mengirim ke broker: '.$log->error : "Terkirim: {$log->summary}. Menunggu konfirmasi ESP32…",
        ], $log->status === 'failed' ? 502 : 200);
    }
}
