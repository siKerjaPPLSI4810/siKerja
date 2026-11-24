@extends('government.layouts.app')
@section('title', 'Antrean Pemeriksaan Lowongan Kerja - SIkERJA')

@section('content')
    @php
        $filters = $filters ?? [];
        $hasFilters = collect($filters)->only(['q', 'category', 'location'])->contains(fn ($value) => filled($value));
        $indexAvailable = Route::has('government.job-inspections.index');
    @endphp
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h1 class="h4">Pemeriksaan Pengajuan Lowongan Kerja</h1>
                    <p class="text-muted mb-0">Tinjau pengajuan lowongan perusahaan sebelum dipublikasikan kepada pencari kerja.</p>
                </div>
                <span class="badge bg-warning text-dark align-self-start text-wrap">Menunggu Tinjauan</span>
            </div>
            <p class="small text-muted mt-3 mb-0">Antrean ini hanya menampilkan lowongan berstatus menunggu tinjauan.</p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h6 mb-3">Cari pengajuan</h2>
            <form method="GET" @if ($indexAvailable) action="{{ route('government.job-inspections.index') }}" @endif>
                <input type="hidden" name="per_page" value="{{ $filters['per_page'] ?? 15 }}">
                <fieldset @disabled(! $indexAvailable)>
                    <legend class="visually-hidden">Pencarian dan filter pengajuan lowongan</legend>
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-lg-6">
                            <label for="job-inspection-q" class="form-label">Nama lowongan atau perusahaan</label>
                            <input id="job-inspection-q" type="search" name="q" maxlength="255" value="{{ $filters['q'] ?? '' }}" class="form-control @error('q') is-invalid @enderror" placeholder="Cari nama lowongan atau perusahaan" @error('q') aria-invalid="true" aria-describedby="job-inspection-q-error" @enderror>
                            @error('q') <div id="job-inspection-q-error" class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="job-inspection-category" class="form-label">Kategori</label>
                            <select id="job-inspection-category" name="category" class="form-select @error('category') is-invalid @enderror" @error('category') aria-invalid="true" aria-describedby="job-inspection-category-error" @enderror>
                                <option value="">Semua kategori</option>
                                @foreach ($categories ?? [] as $category)
                                    <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
                                @endforeach
                            </select>
                            @error('category') <div id="job-inspection-category-error" class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="job-inspection-location" class="form-label">Lokasi</label>
                            <input id="job-inspection-location" type="search" name="location" maxlength="255" value="{{ $filters['location'] ?? '' }}" class="form-control @error('location') is-invalid @enderror" placeholder="Nama kota atau wilayah" @error('location') aria-invalid="true" aria-describedby="job-inspection-location-error" @enderror>
                            @error('location') <div id="job-inspection-location-error" class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button class="btn btn-primary" type="submit">Terapkan pencarian</button>
                            @if ($indexAvailable)
                                <a class="btn btn-outline-secondary" href="{{ route('government.job-inspections.index') }}">Reset</a>
                            @else
                                <button class="btn btn-outline-secondary" type="button" disabled>Reset</button>
                            @endif
                        </div>
                    </div>
                </fieldset>
            </form>
            @if (! $indexAvailable)
                <p class="small text-muted mt-3 mb-0" role="status">Pencarian belum tersedia.</p>
            @endif
        </div>
    </div>

    @if (! isset($jobs))
        <div class="alert alert-warning" role="status">Antrean pengajuan belum tersedia. Silakan coba kembali setelah data dapat dimuat.</div>
    @else
        <div class="card">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h2 class="h6 mb-0">Pengajuan menunggu tinjauan</h2>
                <span class="badge bg-light text-dark border">{{ $jobs->total() }} pengajuan{{ $hasFilters ? ' sesuai filter' : '' }}</span>
            </div>
            <div class="table-responsive" role="region" aria-label="Daftar pengajuan lowongan" tabindex="0">
                <table class="table table-bordered table-hover align-middle mb-0 small">
                    <caption class="visually-hidden">Antrean lowongan berstatus pending_approval. Tanggal pengajuan ditampilkan jika tersedia.</caption>
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Nama lowongan</th>
                            <th scope="col">Perusahaan</th>
                            <th scope="col">Kategori</th>
                            <th scope="col">Lokasi</th>
                            <th scope="col">Estimasi gaji</th>
                            <th scope="col">Tanggal pengajuan</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($jobs as $job)
                            <tr>
                                <th scope="row" class="fw-semibold text-break">{{ $job->title }}</th>
                                <td class="text-break">{{ $job->company?->company_name ?? 'Belum tersedia' }}</td>
                                <td class="text-break">{{ $job->category }}</td>
                                <td class="text-break">{{ $job->location }}</td>
                                <td class="text-nowrap">{{ $job->salary_around !== null ? 'Rp '.number_format((float) $job->salary_around, 0, ',', '.') : 'Belum tersedia' }}</td>
                                <td>
                                    {{-- Peta presentasi opsional; jangan mengganti tanggal pengajuan dengan created_at. --}}
                                    @if (isset($submissionDates[$job->id]))
                                        <time datetime="{{ $submissionDates[$job->id]->toIso8601String() }}">{{ $submissionDates[$job->id]->format('d M Y H:i') }}</time>
                                    @else
                                        <span class="text-muted">Belum tersedia</span>
                                    @endif
                                </td>
                                <td><span class="badge bg-warning text-dark text-wrap">Menunggu Tinjauan</span></td>
                                <td class="text-end text-nowrap">
                                    @if (Route::has('government.job-inspections.show'))
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('government.job-inspections.show', ['jobPosting' => $job->id]) }}" aria-label="Lihat detail lowongan {{ $job->title }}">Lihat Detail</a>
                                    @else
                                        <button class="btn btn-sm btn-outline-primary" type="button" disabled>Lihat Detail</button>
                                        <span class="d-block small text-muted mt-1">Detail belum tersedia</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <h3 class="h6">{{ $hasFilters ? 'Tidak ada pengajuan yang sesuai' : 'Belum ada pengajuan lowongan' }}</h3>
                                    <p class="text-muted mb-0">{{ $hasFilters ? 'Ubah kata pencarian atau reset filter untuk melihat pengajuan lainnya.' : 'Pengajuan baru akan tampil di sini setelah perusahaan mengirim lowongan untuk ditinjau.' }}</p>
                                    @if ($hasFilters && $indexAvailable)
                                        <a class="btn btn-outline-secondary btn-sm mt-3" href="{{ route('government.job-inspections.index') }}">Reset filter</a>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($jobs->total() > 0)
                <div class="card-body border-top">
                    <p class="small text-muted mb-2" role="status">Menampilkan {{ $jobs->firstItem() ?? 0 }}–{{ $jobs->lastItem() ?? 0 }} dari {{ $jobs->total() }} pengajuan.</p>
                    {{ $jobs->appends(collect($filters)->only(['q', 'category', 'location', 'per_page'])->all())->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    @endif
@endsection
