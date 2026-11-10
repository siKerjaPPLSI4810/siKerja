@extends('jobseeker.layouts.app')

@section('title', 'Masuk Pencari Kerja - SIkERJA')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Masuk Akun Pencari Kerja</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('jobseeker.login.submit') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email"
                               id="email"
                               name="email"
                               value="{{ old('email') }}"
                               class="form-control"
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Kata Sandi</label>
                        <input type="password"
                               id="password"
                               name="password"
                               class="form-control"
                               required>
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" name="remember" id="remember" class="form-check-input">
                        <label for="remember" class="form-check-label">Ingat saya</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Masuk</button>

                    <div class="text-center mt-3 small">
                        Belum punya akun? <a href="{{ route('jobseeker.register') }}">Daftar di sini</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
