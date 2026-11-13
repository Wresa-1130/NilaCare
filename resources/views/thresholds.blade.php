@extends('layouts.app')
@section('title', 'Ambang · '.$pond->name)
@section('content')
<section class="page-head">
    <div>
        <h1>Pengaturan ambang</h1>
        <p class="muted">Nilai dikirim ke ESP32 lewat topik <code>{{ $pond->topic('config') }}</code> (retained), jadi tetap berlaku setelah perangkat tersambung ulang.
            @if($last) Terakhir diubah {{ $last->published_at->diffForHumans() }}{{ $last->user ? ' oleh '.$last->user->name : '' }}. @else Saat ini memakai nilai default firmware. @endif
        </p>
    </div>
    <form method="POST" action="{{ route('pond.thresholds.reset', $pond) }}" onsubmit="return confirm('Kembalikan semua ambang ke nilai default?')">
        @csrf
        <button class="btn btn-ghost">Kembalikan default</button>
    </form>
</section>

@if($errors->any())
<div class="flash err"><ul class="plain">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" class="thresholds">
    @csrf @method('PUT')
    @foreach($groups as $group => $items)
    <fieldset class="card">
        <legend>{{ $group }}</legend>
        <div class="th-grid">
            @foreach($items as $key => $t)
            <label @class(['has-error' => $errors->has($key)])>
                <span>{{ $t['label'] }}</span>
                <span class="input-unit">
                    <input type="number" name="{{ $key }}" step="{{ ($t['integer'] ?? false) ? 1 : 0.1 }}"
                           value="{{ old($key, $values[$key]) }}" required>
                    <em>{{ $t['unit'] }}</em>
                </span>
                <small class="muted">default {{ $t['default'] }}</small>
            </label>
            @endforeach
        </div>
    </fieldset>
    @endforeach
    <div class="form-actions">
        <button class="btn btn-primary">Kirim ke perangkat</button>
        <span class="muted small">Batas SNI 7550:2009: suhu 25–32 °C, pH 6,5–8,5, DO ≥ 3 mg/L.</span>
    </div>
</form>
@endsection
