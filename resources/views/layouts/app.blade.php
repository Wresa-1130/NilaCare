<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · NilaCare</title>
    <link rel="stylesheet" href="{{ asset('css/nilacare.css') }}?v=1">
</head>
<body>
@auth
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="{{ route('dashboard') }}">
            <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12c3-5 9-6 13-3l4-3v12l-4-3c-4 3-10 2-13-3z" fill="currentColor"/></svg>
            NilaCare
        </a>
        @isset($pond)
        <nav class="nav">
            <a href="{{ route('pond.show', $pond) }}" @class(['active' => request()->routeIs('dashboard', 'pond.show')])>Dashboard</a>
            <a href="{{ route('pond.thresholds', $pond) }}" @class(['active' => request()->routeIs('pond.thresholds')])>Ambang</a>
            <a href="{{ route('pond.logs', $pond) }}" @class(['active' => request()->routeIs('pond.logs')])>Riwayat</a>
        </nav>
        @if(isset($ponds) && $ponds->count() > 1)
        <select class="pond-select" onchange="location.href=this.value" aria-label="Pilih kolam">
            @foreach($ponds as $p)
                <option value="{{ route('pond.show', $p) }}" @selected($p->id === $pond->id)>{{ $p->name }}</option>
            @endforeach
        </select>
        @endif
        @endisset
        <form method="POST" action="{{ route('logout') }}" class="logout">
            @csrf
            <span class="muted">{{ auth()->user()->name }}</span>
            <button class="btn btn-ghost btn-sm">Keluar</button>
        </form>
    </div>
</header>
@endauth
<main class="container">
    @if(session('status'))<div class="flash ok">{{ session('status') }}</div>@endif
    @if(session('error'))<div class="flash err">{{ session('error') }}</div>@endif
    @yield('content')
</main>
<div id="toast" class="toast" role="status" aria-live="polite"></div>
@stack('scripts')
</body>
</html>
