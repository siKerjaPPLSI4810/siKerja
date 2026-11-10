@extends('jobseeker.layouts.app')

@section('title', 'Cari Lowongan Kerja')

@section('content')
    <h1 class="h3 mb-4">Cari Lowongan Kerja</h1>

    <div class="row g-4">
        <aside class="col-lg-4 col-xl-3">
            <form action="{{ route('jobs.index') }}" method="GET" class="card card-body">
                <h2 class="h5 mb-3">Filter Lowongan</h2>

                <label class="form-label" for="q">Kata kunci</label>
                <input class="form-control mb-3" id="q" name="q" value="{{ old('q', request('q')) }}"
                       maxlength="255" placeholder="Posisi, keahlian, atau perusahaan">

                <label class="form-label" for="category">Kategori</label>
                <select class="form-select mb-3" id="category" name="category">
                    <option value="">Semua kategori</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(old('category', request('category')) === $category)>{{ $category }}</option>
                    @endforeach
                </select>

                <label class="form-label" for="job_type">Tipe pekerjaan</label>
                <select class="form-select mb-3" id="job_type" name="job_type">
                    <option value="">Semua tipe</option>
                    @foreach ($jobTypes as $jobType)
                        <option value="{{ $jobType }}" @selected(old('job_type', request('job_type')) === $jobType)>{{ $jobType }}</option>
                    @endforeach
                </select>

                <label class="form-label" for="location">Lokasi</label>
                <input class="form-control mb-3" id="location" name="location" value="{{ old('location', request('location')) }}"
                       maxlength="255" list="job-locations" placeholder="Ketik atau pilih lokasi">
                <datalist id="job-locations">
                    @foreach ($locations as $location)
                        <option value="{{ $location }}"></option>
                    @endforeach
                </datalist>

                <label class="form-label" for="min_salary">Gaji minimum (Rp)</label>
                <input class="form-control mb-3" type="number" min="0" step="0.01" id="min_salary" name="min_salary"
                       value="{{ old('min_salary', request('min_salary')) }}">

                <label class="form-label" for="max_salary">Gaji maksimum (Rp)</label>
                <input class="form-control mb-3" type="number" min="0" step="0.01" id="max_salary" name="max_salary"
                       value="{{ old('max_salary', request('max_salary')) }}">

                <button class="btn btn-primary" type="submit">Terapkan filter</button>
                <a class="btn btn-link mt-1" href="{{ route('jobs.index') }}">Hapus filter</a>
            </form>
        </aside>

        <section class="col-lg-8 col-xl-9" aria-label="Hasil pencarian">
            <p class="text-muted">{{ $jobs->total() }} lowongan ditemukan</p>

            <div class="row g-3">
                @forelse ($jobs as $job)
                    <div class="col-md-6">
                        <article class="card h-100">
                            <div class="card-body">
                                <span class="badge text-bg-light">{{ $job->category }}</span>
                                <span class="badge text-bg-light">{{ $job->job_type }}</span>
                                <h2 class="h5 mt-2">
                                    <a href="{{ route('jobs.show', $job) }}">{{ $job->title }}</a>
                                </h2>
                                <p class="text-muted mb-2">{{ $job->company->company_name }} · {{ $job->location }}</p>
                                <p class="mb-2">{{ Str::limit(strip_tags($job->description), 120) }}</p>
                                <p class="mb-0">
                                    @if ($job->salary_around !== null)
                                        Estimasi gaji Rp {{ number_format((float) $job->salary_around, 0, ',', '.') }}
                                    @else
                                        Estimasi gaji belum dicantumkan
                                    @endif
                                </p>
                            </div>
                        </article>
                    </div>
                @empty
                    <p>Tidak ada lowongan yang sesuai. Coba ubah kata kunci atau filter.</p>
                @endforelse
            </div>

            <div class="mt-4">
                {{ $jobs->links('pagination::bootstrap-5') }}
            </div>
        </section>
    </div>
@endsection
