@extends('layouts.app')

@section('title', 'Pencarian Lowongan Kerja Resmi — siKERJA')

@section('content')
<div class="page-header">
    <div class="container">
        <h2 class="fw-bold text-navy mb-1"><i class="bi bi-briefcase text-teal me-2"></i>Eksplorasi Lowongan Kerja</h2>
        <p class="text-muted mb-0">Temukan karir terverifikasi dengan upah terstandarisasi dan perlindungan hukum ketenagakerjaan</p>
    </div>
</div>

<div class="container pb-5">
    <div class="row g-4">
        <!-- Sidebar Filter (PBI 1.2.1) -->
        <div class="col-lg-3">
            <div class="card card-custom p-4 sticky-top" style="top: 100px; z-index: 10;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-navy mb-0"><i class="bi bi-funnel me-1"></i> Filter Pencarian</h5>
                    <a href="{{ route('jobs.index') }}" class="small text-danger text-decoration-none">Reset</a>
                </div>

                <form action="{{ route('jobs.index') }}" method="GET">
                    <!-- Kata Kunci -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Kata Kunci</label>
                        <input type="text" name="q" class="form-control form-control-sm" placeholder="Posisi atau keahlian..." value="{{ request('q') }}">
                    </div>

                    <!-- Kategori -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Bidang / Kategori</label>
                        <select name="category" class="form-select form-select-sm">
                            <option value="">Semua Kategori</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}" {{ request('category') == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Tipe Kerja -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Tipe Pekerjaan</label>
                        <select name="job_type" class="form-select form-select-sm">
                            <option value="">Semua Tipe</option>
                            <option value="Penuh Waktu" {{ request('job_type') == 'Penuh Waktu' ? 'selected' : '' }}>Penuh Waktu</option>
                            <option value="Paruh Waktu" {{ request('job_type') == 'Paruh Waktu' ? 'selected' : '' }}>Paruh Waktu</option>
                            <option value="Kontrak" {{ request('job_type') == 'Kontrak' ? 'selected' : '' }}>Kontrak</option>
                            <option value="Magang" {{ request('job_type') == 'Magang' ? 'selected' : '' }}>Magang</option>
                            <option value="Remote" {{ request('job_type') == 'Remote' ? 'selected' : '' }}>Remote</option>
                        </select>
                    </div>

                   
                    <!-- Lokasi -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">
                            Lokasi / Wilayah
                        </label>

                        <input
                            type="text"
                            name="location"
                            class="form-control form-control-sm"
                            placeholder="Ketik atau pilih lokasi..."
                            value="{{ old('location', request('location')) }}"
                            list="location-options"
                            autocomplete="off"
                        >

                        <datalist id="location-options">
                            @foreach($locations as $location)
                                <option value="{{ $location }}"></option>
                            @endforeach
                        </datalist>
                    </div>


                    <!-- Estimasi Gaji Minimal -->
                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Gaji Minimal (Rp)</label>
                        <select name="min_salary" class="form-select form-select-sm">
                            <option value="">Semua Rentang</option>
                            <option value="3000000" {{ old('min_salary', request('min_salary')) == '3000000' ? 'selected' : '' }}>>= Rp 3.000.000</option>
                            <option value="5000000" {{ old('min_salary', request('min_salary')) == '5000000' ? 'selected' : '' }}>>= Rp 5.000.000</option>
                            <option value="8000000" {{ old('min_salary', request('min_salary')) == '8000000' ? 'selected' : '' }}>>= Rp 8.000.000</option>
                            <option value="12000000" {{ old('min_salary', request('min_salary')) == '12000000' ? 'selected' : '' }}>>= Rp 12.000.000</option>
                        </select>
                    </div>

                    <!-- Estimasi Gaji Maksimum -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Gaji Maksimum (Rp)</label>
                        <select name="max_salary" class="form-select form-select-sm">
                            <option value="">Tanpa Batas Maksimum</option>
                            <option value="5000000" {{ old('max_salary', request('max_salary')) == '5000000' ? 'selected' : '' }}>Rp 5.000.000</option>
                            <option value="8000000" {{ old('max_salary', request('max_salary')) == '8000000' ? 'selected' : '' }}>Rp 8.000.000</option>
                            <option value="10000000" {{ old('max_salary', request('max_salary')) == '10000000' ? 'selected' : '' }}>Rp 10.000.000</option>
                            <option value="15000000" {{ old('max_salary', request('max_salary')) == '15000000' ? 'selected' : '' }}>Rp 15.000.000</option>
                        </select>

                        @error('max_salary')
                            <div class="text-danger small mt-2">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-navy text-white btn-sm w-100 py-2 fw-semibold" style="background-color: var(--primary-navy);">
                        <i class="bi bi-search me-1"></i> Terapkan Filter
                    </button>
                </form>
            </div>
        </div>

        <!-- Daftar Lowongan -->
        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="small text-muted">
                    Menampilkan <strong>{{ $jobs->total() }}</strong> lowongan kerja aktif terverifikasi
                </div>
            </div>

            <div class="row g-3">
                @forelse($jobs as $job)
                    <div class="col-md-6">
                        <div class="card card-custom card-hover h-100 p-4 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge-pill-soft badge-soft-teal">{{ $job->category }}</span>
                                    <span class="badge-pill-soft badge-soft-primary">{{ $job->job_type }}</span>
                                </div>

                                <h5 class="fw-bold text-navy mb-1">
                                    <a href="{{ route('jobs.show', $job->id) }}" class="text-decoration-none text-navy">
                                        {{ $job->title }}
                                    </a>
                                </h5>

                                <div class="small text-muted mb-2 d-flex align-items-center gap-1">
                                    <i class="bi bi-building text-warning"></i>
                                    <span>{{ $job->company->company_name }}</span>
                                    @if($job->company->verification_status === 'verified')
                                        <i class="bi bi-patch-check-fill text-primary" title="Legalitas OSS Terverifikasi"></i>
                                    @endif
                                </div>

                                <div class="small text-muted mb-3 d-flex flex-wrap gap-2">
                                    <span><i class="bi bi-geo-alt me-1"></i>{{ $job->location }}</span>
                                    <span>•</span>
                                    <span><i class="bi bi-mortarboard me-1"></i>{{ $job->education_requirement }}</span>
                                    <span>•</span>
                                    <span><i class="bi bi-people me-1"></i>Kuota: {{ $job->quota }} orang</span>
                                </div>

                                <p class="text-muted small mb-3">
                                    {{ Str::limit(strip_tags($job->description), 100) }}
                                </p>
                            </div>

                            <div>
                                <hr class="my-2 border-light">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="small text-muted">Estimasi Kompensasi:</span>
                                    <span class="fw-bold text-success">
                                        Rp {{ number_format($job->salary_around, 0, ',', '.') }}
                                    </span>
                                </div>

                                <a href="{{ route('jobs.show', $job->id) }}" class="btn btn-outline-navy btn-sm w-100 py-2 fw-semibold">
                                    Lihat Rincian & Lamar <i class="bi bi-chevron-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="card card-custom p-5 text-center">
                            <i class="bi bi-search text-muted fs-1 mb-2"></i>
                            <h5 class="fw-bold text-navy">Tidak ada lowongan yang sesuai kriteria</h5>
                            <p class="text-muted small">Coba ubah kata kunci atau hapus filter untuk melihat pilihan pekerjaan lainnya.</p>
                            <div>
                                <a href="{{ route('jobs.index') }}" class="btn btn-sm btn-navy text-white px-3" style="background-color: var(--primary-navy);">
                                    Reset Semua Filter
                                </a>
                            </div>
                        </div>
                    </div>
                @endforelse
            </div>

            <div class="mt-4 d-flex justify-content-center">
                {{ $jobs->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
</div>
@endsection
