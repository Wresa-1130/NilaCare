<?php

namespace App\Services;

use App\Models\Pond;

/**
 * Ambang yang berlaku: default dari config/nilacare.php ditimpa nilai terakhir yang dikirim.
 */
class ThresholdService
{
    public function current(Pond $pond): array
    {
        $values = collect(config('nilacare.thresholds'))->map(fn ($t) => $t['default'])->all();
        $last = $pond->thresholdConfigs()->latest('published_at')->first();

        return array_merge($values, $last?->values ?? []);
    }

    /** Aturan validasi form ambang. */
    public function rules(): array
    {
        return collect(config('nilacare.thresholds'))->map(fn ($t) => ($t['integer'] ?? false)
            ? ['required', 'integer', 'min:0', 'max:50']
            : ['required', 'numeric', 'min:0', 'max:200'])->all();
    }

    /** Validasi logis antar-ambang. Mengembalikan daftar pesan error (kosong = valid). */
    public function crossCheck(array $v): array
    {
        $pairs = [
            ['doAOn', 'doAOff', 'Aerator A: batas OFF harus lebih besar dari batas ON.'],
            ['doBOn', 'doBOff', 'Aerator B: batas OFF harus lebih besar dari batas ON.'],
            ['doBOn', 'doAOn', 'Aerator B harus menyala pada DO lebih rendah dari Aerator A.'],
            ['doCrit', 'doBOn', 'Batas kritis DO harus di bawah batas ON Aerator B.'],
            ['tempHeaterOn', 'tempHeaterOff', 'Heater: batas OFF harus lebih besar dari batas ON.'],
            ['tempShadeOpen', 'tempShadeClose', 'Paranet: batas tutup harus lebih besar dari batas buka.'],
            ['tempMin', 'tempMax', 'Batas bawah suhu harus di bawah batas atas.'],
            ['phLow', 'phTarget', 'pH target harus di atas batas dosis.'],
            ['phTarget', 'phHigh', 'pH atas harus di atas pH target.'],
            ['ntuClean', 'ntuHigh', 'Batas air bersih harus di bawah batas ganti air.'],
        ];
        $errors = [];
        foreach ($pairs as [$low, $high, $msg]) {
            if ((float) $v[$low] >= (float) $v[$high]) {
                $errors[$high] = $msg;
            }
        }

        return $errors;
    }
}
