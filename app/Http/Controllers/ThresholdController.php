<?php

namespace App\Http\Controllers;

use App\Models\Pond;
use App\Services\CommandService;
use App\Services\ThresholdService;
use Illuminate\Http\Request;

/** Form pengaturan ambang; nilai dikirim retained ke topik config. */
class ThresholdController extends Controller
{
    public function __construct(private ThresholdService $thresholds, private CommandService $commands) {}

    public function edit(Pond $pond)
    {
        return view('thresholds', [
            'pond' => $pond,
            'ponds' => Pond::orderBy('code')->get(),
            'values' => $this->thresholds->current($pond),
            'groups' => collect(config('nilacare.thresholds'))->groupBy('group', true),
            'last' => $pond->thresholdConfigs()->with('user')->latest('published_at')->first(),
        ]);
    }

    public function update(Request $request, Pond $pond)
    {
        $values = $request->validate($this->thresholds->rules());
        if ($errors = $this->thresholds->crossCheck($values)) {
            return back()->withErrors($errors)->withInput();
        }
        $values = collect($values)->map(fn ($v, $k) => (config("nilacare.thresholds.$k.integer") ? (int) $v : (float) $v))->all();
        $log = $this->commands->thresholds($pond, $request->user(), $values);

        return back()->with($log->status === 'failed' ? 'error' : 'status', $log->status === 'failed'
            ? 'Gagal mengirim ke broker: '.$log->error
            : 'Ambang dikirim ke perangkat. ESP32 akan memakai nilai baru segera setelah menerimanya.');
    }

    public function reset(Request $request, Pond $pond)
    {
        $defaults = collect(config('nilacare.thresholds'))->map(fn ($t) => $t['default'])->all();
        $log = $this->commands->thresholds($pond, $request->user(), $defaults);

        return back()->with($log->status === 'failed' ? 'error' : 'status', $log->status === 'failed' ? 'Gagal: '.$log->error : 'Ambang dikembalikan ke nilai default.');
    }
}
