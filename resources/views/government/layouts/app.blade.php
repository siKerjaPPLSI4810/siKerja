<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Pemeriksaan Perusahaan - SIkERJA')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <a href="#main-content" class="visually-hidden-focusable btn btn-light">Lewati ke konten utama</a>
    <nav class="navbar navbar-dark bg-primary mb-4" aria-label="Navigasi pemerintah">
        <div class="container gap-2">
            @if (Route::has('government.company-inspections.index'))
                <a class="navbar-brand fw-bold" href="{{ route('government.company-inspections.index') }}">SIkERJA</a>
            @else
                <span class="navbar-brand fw-bold">SIkERJA</span>
            @endif
            <span class="text-white me-auto">{{ request()->routeIs('government.job-monitoring.*') ? 'Monitoring Lowongan' : (request()->routeIs('government.job-inspections.*') ? 'Pemeriksaan Lowongan' : 'Pemeriksaan Perusahaan') }}</span>
            @can('access-government')
                @if (Route::has('government.job-inspections.index'))
                    <a class="btn btn-sm btn-outline-light" href="{{ route('government.job-inspections.index') }}" @if (request()->routeIs('government.job-inspections.*')) aria-current="page" @endif>Pemeriksaan lowongan</a>
                @endif
                @if (Route::has('government.job-monitoring.index'))
                    <a class="btn btn-sm btn-outline-light" href="{{ route('government.job-monitoring.index') }}" @if (request()->routeIs('government.job-monitoring.*')) aria-current="page" @endif>Monitoring Lowongan</a>
                @endif
                @if (Route::has('government.company-verification-statuses.index'))
                    <a class="btn btn-sm btn-outline-light" href="{{ route('government.company-verification-statuses.index') }}" @if (request()->routeIs('government.company-verification-statuses.index', 'government.company-verification-statuses.show')) aria-current="page" @endif>Monitoring verifikasi</a>
                @endif
                <span class="text-white small">{{ auth()->user()->name }} · Admin Pemerintah</span>
            @endcan
        </div>
    </nav>
    <main id="main-content" class="container pb-5">
        {{-- Pesan sukses hanya berasal dari backend setelah penyimpanan berhasil. --}}
        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert" tabindex="-1" id="validation-summary">
                <p class="fw-semibold mb-1">Periksa isian berikut:</p>
                <ul class="mb-0">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
    <script>
        const errorSummary = document.getElementById('validation-summary') || document.getElementById('conflict-message');
        if (errorSummary) errorSummary.focus();
    </script>
</body>
</html>
