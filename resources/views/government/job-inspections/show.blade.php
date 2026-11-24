@extends('government.layouts.app')
@section('title', 'Detail Pengajuan Lowongan Kerja - SIkERJA')

@section('content')
    <nav aria-label="Breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('government.job-inspections.index') }}">Pengajuan lowongan</a></li>
            <li class="breadcrumb-item active" aria-current="page">Detail pengajuan</li>
        </ol>
    </nav>
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3">
                <div class="text-break">
                    <p class="small text-muted mb-1">Detail Pengajuan Lowongan Kerja</p>
                    <h1 class="h4">{{ filled($job->title) ? $job->title : 'Belum tersedia' }}</h1>
                    <p class="mb-0">Perusahaan: <span class="fw-semibold">{{ filled($job->company?->company_name) ? $job->company->company_name : 'Belum tersedia' }}</span></p>
                </div>
                <div>
                    <span class="visually-hidden">Status: </span>
                    <span class="badge bg-warning text-dark text-wrap" data-job-inspection-status>Menunggu Tinjauan</span>
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
    @can('access-government')
        @if ($job->status === 'pending_approval')
            <section class="card mb-4" aria-labelledby="job-decision-heading" data-job-decision-panel data-job-id="{{ $job->id }}">
                <div class="card-body">
                    <h2 class="h5" id="job-decision-heading">Keputusan pemeriksaan</h2>
                    <p class="small text-muted">PM Hasya telah mendelegasikan pemilihan aturan pemeriksaan kepada tim sesuai PBI dan aturan proyek. Tolak dan Minta Perbaikan sama-sama memakai status rejected dan belum dapat dibedakan secara terstruktur.</p>
                    <noscript><div class="alert alert-warning">Aktifkan JavaScript untuk mengirim keputusan dari halaman ini.</div></noscript>
                    <div class="alert alert-info" role="status" aria-live="polite" tabindex="-1" data-decision-status hidden></div>
                    <div class="alert alert-danger" role="alert" tabindex="-1" data-decision-error hidden>
                        <p class="mb-1" data-decision-error-message></p>
                        <ul class="mb-0" data-decision-error-list></ul>
                    </div>
                    <button type="button" class="btn btn-outline-secondary mb-3" data-decision-reload hidden>Muat ulang halaman</button>
                    <div data-decision-actions>
                        <form method="POST" action="{{ route('government.job-inspections.api.decisions.store', ['jobPosting' => $job->id]) }}" class="mb-4" data-decision-action="approve">
                            @csrf
                            <input type="hidden" name="action" value="approve">
                            <fieldset disabled data-decision-fields>
                                <legend class="visually-hidden">Persetujuan lowongan</legend>
                                <p class="small text-muted">Pastikan informasi pengajuan telah diperiksa sebelum menyetujui.</p>
                                <button type="submit" class="btn btn-success">Setujui Lowongan</button>
                            </fieldset>
                        </form>
                        <div class="row g-3">
                            <div class="col-12 col-lg-6">
                                <form method="POST" action="{{ route('government.job-inspections.api.decisions.store', ['jobPosting' => $job->id]) }}" data-decision-action="reject">
                                    @csrf
                                    <input type="hidden" name="action" value="reject">
                                    <fieldset disabled data-decision-fields>
                                        <legend class="h6">Tolak lowongan</legend>
                                        <label for="job-rejection-reason" class="form-label">Alasan penolakan <span aria-hidden="true">*</span></label>
                                        <textarea id="job-rejection-reason" name="rejection_reason" class="form-control mb-2" rows="4" required maxlength="1000" aria-describedby="job-rejection-help"></textarea>
                                        <p class="form-text" id="job-rejection-help">Wajib diisi, maksimal 1.000 karakter.</p>
                                        <button type="submit" class="btn btn-danger">Tolak Lowongan</button>
                                    </fieldset>
                                </form>
                            </div>
                            <div class="col-12 col-lg-6">
                                <form method="POST" action="{{ route('government.job-inspections.api.decisions.store', ['jobPosting' => $job->id]) }}" data-decision-action="request_changes">
                                    @csrf
                                    <input type="hidden" name="action" value="request_changes">
                                    <fieldset disabled data-decision-fields>
                                        <legend class="h6">Minta perbaikan</legend>
                                        <label for="job-correction-note" class="form-label">Catatan perbaikan <span aria-hidden="true">*</span></label>
                                        <textarea id="job-correction-note" name="rejection_reason" class="form-control mb-2" rows="4" required aria-describedby="job-correction-help"></textarea>
                                        <p class="form-text" id="job-correction-help">Jelaskan informasi yang perlu diperbaiki perusahaan.</p>
                                        <button type="submit" class="btn btn-warning">Minta Perbaikan</button>
                                    </fieldset>
                                </form>
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('government.job-inspections.index') }}" class="btn btn-primary mt-3" data-decision-return hidden>Kembali ke antrean</a>
                </div>
            </section>
        @endif
    @endcan
    <a class="btn btn-outline-secondary" href="{{ route('government.job-inspections.index') }}">Kembali ke daftar pengajuan</a>
@endsection

@push('scripts')
    <script src="{{ asset('js/job-inspection-decision.js') }}" defer></script>
@endpush
