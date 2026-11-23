@extends('government.layouts.app')
@section('title', 'Detail Monitoring Lowongan Kerja - SIkERJA')

@section('content')
    <nav aria-label="Breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('government.job-monitoring.index') }}">Monitoring lowongan</a></li>
            <li class="breadcrumb-item active" aria-current="page">Detail monitoring</li>
        </ol>
    </nav>
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3">
                <div class="text-break">
                    <p class="small text-muted mb-1">Detail Monitoring Lowongan Kerja</p>
                    <h1 class="h4">{{ filled($job->title) ? $job->title : 'Belum tersedia' }}</h1>
                    <p class="mb-0">Perusahaan: <span class="fw-semibold">{{ filled($job->company?->company_name) ? $job->company->company_name : 'Belum tersedia' }}</span></p>
                </div>
                <div>
                    <span class="visually-hidden">Status: </span>
                    <span class="badge bg-success text-wrap">Disetujui / Tayang</span>
                </div>
            </div>
        </div>
    </div>

    <section class="card mb-4" aria-labelledby="job-information-heading">
        <div class="card-body">
            <h2 class="h5 mb-3" id="job-information-heading">Informasi pekerjaan</h2>
            <dl class="row mb-0">
                <dt class="col-sm-4">Kategori</dt>
                <dd class="col-sm-8 text-break">{{ filled($job->category) ? $job->category : 'Belum tersedia' }}</dd>
                <dt class="col-sm-4">Tipe pekerjaan</dt>
                <dd class="col-sm-8 text-break">{{ filled($job->job_type) ? $job->job_type : 'Belum tersedia' }}</dd>
                <dt class="col-sm-4">Lokasi</dt>
                <dd class="col-sm-8 text-break">{{ filled($job->location) ? $job->location : 'Belum tersedia' }}</dd>
                <dt class="col-sm-4">Estimasi gaji</dt>
                <dd class="col-sm-8">{{ $job->salary_around !== null ? 'Rp '.number_format((float) $job->salary_around, 0, ',', '.') : 'Belum tersedia' }}</dd>
                <dt class="col-sm-4">Kuota</dt>
                <dd class="col-sm-8">{{ $job->quota !== null ? $job->quota.' orang' : 'Belum tersedia' }}</dd>
                <dt class="col-sm-4">Batas waktu pendaftaran</dt>
                <dd class="col-sm-8 mb-0">
                    @if ($job->deadline !== null)
                        <time datetime="{{ $job->deadline->format('Y-m-d') }}">{{ $job->deadline->format('d M Y') }}</time>
                    @else
                        Belum tersedia
                    @endif
                </dd>
            </dl>
        </div>
    </section>

    <section class="card mb-4" aria-labelledby="job-description-heading">
        <div class="card-body">
            <h2 class="h5" id="job-description-heading">Deskripsi pekerjaan</h2>
            <p class="text-break mb-0" style="white-space: pre-line">{{ filled($job->description) ? $job->description : 'Belum tersedia' }}</p>
        </div>
    </section>
    <section class="card mb-4" aria-labelledby="job-requirements-heading">
        <div class="card-body">
            <h2 class="h5" id="job-requirements-heading">Kualifikasi dan persyaratan</h2>
            <dl class="mb-3">
                <dt>Pendidikan minimum</dt>
                <dd class="text-break">{{ filled($job->education_requirement) ? $job->education_requirement : 'Belum tersedia' }}</dd>
            </dl>
            <h3 class="h6">Persyaratan</h3>
            <p class="text-break mb-0" style="white-space: pre-line">{{ filled($job->requirements) ? $job->requirements : 'Belum tersedia' }}</p>
        </div>
    </section>
    <p class="small text-muted">Cakupan monitoring sementara hanya published, termasuk deadline lewat/null. Definisi aktif final dan cakupan multi-status: Belum disepakati.</p>
    @php($flag = $job->reviewFlag)
    <section class="card mb-4" aria-labelledby="review-flag-heading">
        <div class="card-body">
            <h2 class="h5" id="review-flag-heading">Tinjauan lanjutan</h2>
            @if ($flag?->isActive())
                <p><span class="badge bg-warning text-dark">Perlu Ditinjau</span></p>
                <dl>
                    <dt>Alasan penandaan</dt><dd class="text-break" style="white-space: pre-line">{{ $flag->reason }}</dd>
                    <dt>Ditandai oleh</dt><dd>{{ $flag->flagger?->name ?? 'Petugas tidak tersedia' }}</dd>
                    <dt>Waktu penandaan</dt><dd><time datetime="{{ $flag->flagged_at->toIso8601String() }}">{{ $flag->flagged_at->format('d M Y H:i:s') }} {{ config('app.timezone') }}</time></dd>
                </dl>
                <form method="POST" action="{{ route('government.job-monitoring.api.flags.destroy', ['jobPosting' => $job->id]) }}" onsubmit="return window.confirm('Hapus tanda tinjauan lowongan ini?');">
                    @csrf
                    <input type="hidden" name="_flag_form" value="1">
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">Hapus Tanda</button>
                </form>
            @else
                <p class="text-muted">Tidak ada tanda tinjauan aktif.</p>
                @if ($flag)
                    <p class="small text-muted">Tanda terakhir dicabut oleh {{ $flag->clearer?->name ?? 'Petugas tidak tersedia' }} pada {{ $flag->cleared_at?->format('d M Y H:i:s') }} {{ config('app.timezone') }}.</p>
                @endif
                <form method="POST" action="{{ route('government.job-monitoring.api.flags.store', ['jobPosting' => $job->id]) }}">
                    @csrf
                    <input type="hidden" name="_flag_form" value="1">
                    <label for="review-flag-reason" class="form-label">Alasan penandaan <span aria-hidden="true">*</span></label>
                    <textarea id="review-flag-reason" name="reason" rows="4" required maxlength="1000" class="form-control @error('reason') is-invalid @enderror" aria-describedby="review-flag-reason-help @error('reason') review-flag-reason-error @enderror" @error('reason') aria-invalid="true" @enderror>{{ old('reason') }}</textarea>
                    <p id="review-flag-reason-help" class="form-text">Wajib diisi, maksimal 1.000 karakter. Penandaan tidak mengubah status lowongan.</p>
                    @error('reason') <div id="review-flag-reason-error" class="invalid-feedback">{{ $message }}</div> @enderror
                    <button type="submit" class="btn btn-warning mt-2">Tandai untuk Ditinjau</button>
                </form>
            @endif
        </div>
    </section>
    <a class="btn btn-outline-secondary" href="{{ route('government.job-monitoring.index') }}">Kembali ke daftar monitoring</a>
@endsection