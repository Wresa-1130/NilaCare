@extends('layouts.app')
@section('title', $pond->name)
@section('content')
@php($th = app(\App\Services\ThresholdService::class)->current($pond))
<div id="app"
     data-live="{{ route('pond.live', $pond) }}"
     data-history="{{ route('pond.history', $pond) }}"
     data-actuator="{{ route('pond.actuator', $pond) }}"
     data-mode="{{ route('pond.mode', $pond) }}"
     data-action="{{ route('pond.action', $pond) }}"
     data-thresholds='@json($th)'>

    <section class="status-bar">
        <div>
            <h1>{{ $pond->name }} <span class="code">{{ $pond->code }}</span></h1>
            <div class="status-line">
                <span id="online" class="badge">memuat…</span>
                <span id="mode" class="badge">mode –</span>
                <span id="wc" class="badge hidden"></span>
                <span class="muted" id="age"></span>
            </div>
        </div>
        <div class="status-actions">
            <button class="btn btn-ghost" data-mode-set="auto" title="Lepas semua kendali manual">Otomatis semua</button>
            <button class="btn btn-ghost" data-mode-set="manual" title="Kunci semua aktuator di kondisi sekarang">Kunci manual</button>
        </div>
    </section>

    <div id="alarm" class="alarm hidden">
        <div><strong>Alarm:</strong> <span id="alarm-text"></span> <span class="muted" id="alarm-since"></span></div>
        <button class="btn btn-sm btn-danger" data-action-run="ack" id="ack-btn">Konfirmasi alarm</button>
    </div>

    <section class="metrics">
        <article class="metric" data-metric="do">
            <div class="metric-label">Oksigen terlarut</div>
            <div class="metric-value"><span class="v">–</span><small>mg/L</small></div>
            <div class="metric-foot"><span class="zone">–</span><span class="muted">target ≥ 5</span></div>
        </article>
        <article class="metric" data-metric="temp">
            <div class="metric-label">Suhu air</div>
            <div class="metric-value"><span class="v">–</span><small>°C</small></div>
            <div class="metric-foot"><span class="zone">–</span><span class="muted">tandon <span id="temp-tank">–</span>°C</span></div>
        </article>
        <article class="metric" data-metric="ph">
            <div class="metric-label">pH</div>
            <div class="metric-value"><span class="v">–</span><small></small></div>
            <div class="metric-foot"><span class="zone">–</span><span class="muted">target 7,0–8,0</span></div>
        </article>
        <article class="metric" data-metric="ntu">
            <div class="metric-label">Kekeruhan</div>
            <div class="metric-value"><span class="v">–</span><small>NTU</small></div>
            <div class="metric-foot"><span class="zone">–</span><span class="muted">target &lt; 40</span></div>
        </article>
        <article class="metric" data-metric="level">
            <div class="metric-label">Level air</div>
            <div class="metric-value"><span class="v">–</span><small>cm</small></div>
            <div class="metric-foot"><span class="zone" id="float">–</span><span class="muted">normal 100</span></div>
        </article>
    </section>

    <div class="grid-2">
        <section class="card">
            <div class="card-head">
                <h2>Aktuator</h2>
                <span class="muted small">Otomatis = mengikuti aturan ESP32</span>
            </div>
            <div class="actuators">
                @foreach(config('nilacare.actuators') as $key => $label)
                <div class="act-row" data-act="{{ $key }}">
                    <span class="act-name">{{ $label }}</span>
                    <span class="pill">–</span>
                    <div class="seg" role="group" aria-label="Kendali {{ $label }}">
                        <button data-set="auto">Otomatis</button>
                        <button data-set="on">ON</button>
                        <button data-set="off">OFF</button>
                    </div>
                </div>
                @endforeach
            </div>
            <div class="quick">
                <button class="btn" data-action-run="water_change">Ganti air sekarang</button>
                <button class="btn" data-action-run="dose">Dosis kapur</button>
                <button class="btn" data-action-run="aerator_early" title="Simulasi perintah modul prediksi">Aerator dini (60 mnt)</button>
            </div>
            <p class="muted small">Pengaman perangkat tetap aktif di ESP32: pompa buang &amp; heater mati otomatis saat air di level minimum.</p>
        </section>

        <section class="card">
            <div class="card-head"><h2>Perintah terakhir</h2></div>
            <ul class="list" id="commands"><li class="muted">Belum ada perintah.</li></ul>
            <div class="card-head" style="margin-top:18px"><h2>Kejadian terbaru</h2></div>
            <ul class="list" id="events"><li class="muted">Belum ada kejadian.</li></ul>
        </section>
    </div>

    <section class="card">
        <div class="card-head">
            <h2>Grafik</h2>
            <div class="seg" id="range">
                <button data-hours="1">1 jam</button>
                <button data-hours="6" class="on">6 jam</button>
                <button data-hours="24">24 jam</button>
            </div>
        </div>
        <div class="charts">
            <div class="chart-box"><h3>Oksigen terlarut (mg/L)</h3><canvas id="c-do"></canvas></div>
            <div class="chart-box"><h3>Suhu (°C)</h3><canvas id="c-temp"></canvas></div>
            <div class="chart-box"><h3>pH</h3><canvas id="c-ph"></canvas></div>
            <div class="chart-box"><h3>Kekeruhan (NTU)</h3><canvas id="c-ntu"></canvas></div>
        </div>
        <p class="muted small">Garis putus-putus = ambang aksi aktuator. Data telemetry masuk tiap 30 detik.</p>
    </section>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/dashboard.js') }}?v=1"></script>
@endpush
