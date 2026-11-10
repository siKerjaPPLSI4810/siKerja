<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SIkERJA - Portal Pencari Kerja')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4 no-print">
        <div class="container">
            <a class="navbar-brand fw-bold" href="{{ route('home') }}">SIkERJA</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('jobs.*') ? 'active fw-semibold' : '' }}"
                           href="{{ route('jobs.index') }}">Lowongan Kerja</a>
                    </li>
                    @can('access-job-applications')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('jobseeker.applications') ? 'active fw-semibold' : '' }}"
                               href="{{ route('jobseeker.applications') }}">Riwayat Lamaran</a>
                        </li>
                    @endcan
                    @auth
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('jobseeker.profile.*') ? 'active fw-semibold' : '' }}"
                               href="{{ route('jobseeker.profile.index') }}">
                                Profil Pencari Kerja
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('jobseeker.cv.*') ? 'active fw-semibold' : '' }}"
                               href="{{ route('jobseeker.cv.show') }}">
                                CV Otomatis
                            </a>
                        </li>
                    @endauth
                </ul>
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    @auth
                        <li class="nav-item me-3 text-white small">
                            {{ auth()->user()->name }} ({{ auth()->user()->email }})
                        </li>
                        <li class="nav-item">
                            <form action="{{ route('jobseeker.logout') }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-light">Keluar</button>
                            </form>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('jobseeker.register') ? 'active fw-semibold' : '' }}"
                               href="{{ route('jobseeker.register') }}">
                                Daftar Akun
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('jobseeker.login') ? 'active fw-semibold' : '' }}"
                               href="{{ route('jobseeker.login') }}">
                                Masuk
                            </a>
                        </li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <div class="container pb-5">
        @if (session('status'))
            <div class="alert alert-success no-print">
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger no-print">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger no-print">
                <div class="fw-bold mb-1">Terdapat kesalahan validasi:</div>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
