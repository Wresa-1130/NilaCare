@extends('layouts.app')
@section('title', 'Riwayat · '.$pond->name)
@section('content')
<section class="page-head">
    <div>
        <h1>Riwayat</h1>
        <p class="muted">{{ number_format($readingCount) }} data telemetry pada rentang ini.</p>
    </div>
    <form method="GET" class="range-form">
        <label>Dari <input type="date" name="from" value="{{ $from->toDateString() }}"></label>
        <label>Sampai <input type="date" name="to" value="{{ $to->toDateString() }}"></label>
        <button class="btn btn-ghost">Tampilkan</button>
    </form>
</section>

@php($q = ['from' => $from->toDateString(), 'to' => $to->toDateString()])
<div class="export">
    <span class="muted">Unduh CSV:</span>
    @foreach(['readings' => 'Data sensor', 'actuators' => 'Aksi aktuator', 'events' => 'Kejadian', 'commands' => 'Perintah manual', 'alarms' => 'Alarm'] as $type => $label)
        <a class="btn btn-sm" href="{{ route('pond.export', [$pond, $type] + $q) }}">{{ $label }}</a>
    @endforeach
</div>

<div class="grid-2">
    <section class="card">
        <h2>Alarm</h2>
        <div class="scroll"><table class="table">
            <thead><tr><th>Mulai</th><th>Pesan</th><th>Durasi</th><th>Konfirmasi</th></tr></thead>
            <tbody>
            @forelse($alarms as $a)
                <tr>
                    <td>{{ $a->started_at->format('d/m H:i') }}</td>
                    <td>{{ $a->message }}</td>
                    <td>{{ $a->ended_at ? $a->started_at->diffForHumans($a->ended_at, true) : 'masih aktif' }}</td>
                    <td>{{ $a->acknowledged_at ? ($a->acknowledger?->name ?? 'perangkat') : '–' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">Tidak ada alarm.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>

    <section class="card">
        <h2>Perintah manual</h2>
        <div class="scroll"><table class="table">
            <thead><tr><th>Waktu</th><th>Perintah</th><th>Petugas</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($commands as $c)
                <tr>
                    <td>{{ $c->sent_at->format('d/m H:i:s') }}</td>
                    <td>{{ $c->summary }}</td>
                    <td>{{ $c->user?->name ?? '–' }}</td>
                    <td>
                        <span @class(['badge', 'ok' => $c->status === 'confirmed', 'warn' => $c->status === 'sent', 'off' => $c->status === 'failed'])>
                            {{ ['sent' => 'menunggu', 'confirmed' => 'terkonfirmasi', 'failed' => 'gagal'][$c->status] }}
                        </span>
                        @if($c->confirmed_at)<span class="muted small">{{ number_format($c->sent_at->diffInMilliseconds($c->confirmed_at) / 1000, 1, ',', '') }} dtk</span>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">Belum ada perintah.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
</div>

<div class="grid-2">
    <section class="card">
        <h2>Aksi aktuator</h2>
        <div class="scroll"><table class="table">
            <thead><tr><th>Waktu</th><th>Aktuator</th><th>Status</th><th>Alasan</th></tr></thead>
            <tbody>
            @forelse($actuatorEvents as $e)
                <tr>
                    <td>{{ $e->received_at->format('d/m H:i:s') }}</td>
                    <td>{{ config("nilacare.actuators.{$e->actuator}", $e->actuator) }}</td>
                    <td><span @class(['pill', 'on' => $e->state === 'on'])>{{ strtoupper($e->state) }}</span></td>
                    <td>{{ $e->reason }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">Belum ada aksi.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>

    <section class="card">
        <h2>Kejadian perangkat</h2>
        <div class="scroll"><table class="table">
            <thead><tr><th>Waktu</th><th>Jenis</th><th>Detail</th></tr></thead>
            <tbody>
            @forelse($deviceEvents as $e)
                <tr>
                    <td>{{ $e->received_at->format('d/m H:i:s') }}</td>
                    <td><span class="k k-{{ $e->type }}">{{ $e->type }}</span></td>
                    <td>{{ $e->detail }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">Belum ada kejadian.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
</div>
@endsection
