<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SIkERJA - Portal Perusahaan')</title>
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
                    @auth
                        @if (auth()->user()->role === 'perusahaan')
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('company.profile.*') ? 'active fw-semibold' : '' }}"
                                   href="{{ route('company.profile.index') }}">
                                    Profil Perusahaan
                                </a>
                            </li>
                        @endif
                    @endauth
                </ul>
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    @auth
                        <li class="nav-item me-3 text-white small">
                            {{ auth()->user()->companyProfile->company_name ?? auth()->user()->name }} ({{ auth()->user()->email }})
                        </li>
                        <li class="nav-item">
                            <form action="{{ route('company.logout') }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-light">Keluar</button>
                            </form>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('company.register') ? 'active fw-semibold' : '' }}"
                               href="{{ route('company.register') }}">
                                Daftar Akun Perusahaan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('company.login') ? 'active fw-semibold' : '' }}"
                               href="{{ route('company.login') }}">
                                Masuk Perusahaan
                            </a>
                        </li>
                        <li class="nav-item border-start ps-2 ms-2 d-none d-lg-block">
                            <a class="btn btn-sm btn-outline-light" href="{{ route('jobseeker.login') }}">
                                Pencari Kerja
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
