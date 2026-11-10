@extends('jobseeker.layouts.app')

@section('title', $job->title)

@section('content')
    <a class="d-inline-block mb-3" href="{{ route('jobs.index') }}">Kembali ke daftar lowongan</a>

    <article class="card card-body">
        <h1 class="h3">{{ $job->title }}</h1>
        <p class="text-muted">{{ $job->company->company_name }} · {{ $job->location }}</p>

        <dl class="row">
            <dt class="col-sm-4">Kategori</dt>
            <dd class="col-sm-8">{{ $job->category }}</dd>
            <dt class="col-sm-4">Tipe pekerjaan</dt>
            <dd class="col-sm-8">{{ $job->job_type }}</dd>
            <dt class="col-sm-4">Pendidikan</dt>
            <dd class="col-sm-8">{{ $job->education_requirement }}</dd>
            <dt class="col-sm-4">Kuota</dt>
            <dd class="col-sm-8">{{ $job->quota }} orang</dd>
            <dt class="col-sm-4">Estimasi gaji</dt>
            <dd class="col-sm-8">
                @if ($job->salary_around !== null)
                    Rp {{ number_format((float) $job->salary_around, 0, ',', '.') }}
                @else
                    Belum dicantumkan
                @endif
            </dd>
            <dt class="col-sm-4">Batas pendaftaran</dt>
            <dd class="col-sm-8">{{ $job->deadline?->format('d/m/Y') ?? 'Belum dicantumkan' }}</dd>
        </dl>

        <h2 class="h5">Deskripsi</h2>
        <p class="text-break" style="white-space: pre-line">{{ $job->description }}</p>

        <h2 class="h5">Persyaratan</h2>
        <p class="text-break mb-0" style="white-space: pre-line">{{ $job->requirements }}</p>
    </article>

    <section class="card card-body mt-3">
        <h2 class="h5">Ajukan Lamaran</h2>
        @if ($hasApplied)
            <p class="mb-0">Anda sudah melamar lowongan ini. <a href="{{ route('jobseeker.applications') }}">Lihat riwayat lamaran</a>.</p>
        @elseif ($job->applicationDeadlinePassed())
            <p class="mb-0">Batas waktu pendaftaran lowongan ini sudah berakhir.</p>
        @else
            @guest
                <p class="mb-0"><a href="{{ route('jobseeker.login') }}">Masuk sebagai pencari kerja</a> untuk mengirim lamaran.</p>
            @endguest
            @can('access-job-applications')
                <form method="POST" action="{{ route('jobseeker.apply', $job) }}">
                    @csrf
                    <label class="form-label" for="applicant_notes">Catatan untuk perusahaan (opsional)</label>
                    <textarea class="form-control mb-3" id="applicant_notes" name="applicant_notes" maxlength="2000" rows="3">{{ is_string(old('applicant_notes')) ? old('applicant_notes') : '' }}</textarea>
                    <button class="btn btn-primary" type="submit">Kirim lamaran</button>
                </form>
            @endcan
        @endif
    </section>
@endsection
