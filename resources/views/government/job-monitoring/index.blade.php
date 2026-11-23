@extends('government.layouts.app')
@section('title', 'Monitoring Lowongan Kerja - SIkERJA')

@section('content')
    @php
        $filters = $filters ?? [];
        $companyChoices = collect($companies ?? []);
        $categoryChoices = collect($categories ?? []);
        $hasFilters = collect($filters)->only(['q', 'company', 'category', 'location'])->contains(fn ($value) => filled($value));
        $indexAvailable = Route::has('government.job-monitoring.index');
        $detailAvailable = Route::has('government.job-monitoring.show');
        $statusLabels = ['draft' => 'Draf', 'pending_approval' => 'Menunggu Tinjauan', 'published' => 'Disetujui / Tayang', 'rejected' => 'Ditolak / Perlu Perbaikan', 'closed' => 'Ditutup'];
    @endphp
    <div class="card mb-4">
        <div class="card-body">
            <h1 class="h4">Monitoring Lowongan Kerja</h1>
            <p class="text-muted mb-0">Pantau informasi lowongan perusahaan melalui daftar dan detail lowongan.</p>
        </div>
    </div>
    <div class="alert alert-info" role="note">
        Cakupan sementara: hanya lowongan berstatus published, termasuk deadline lewat atau belum tersedia. Definisi final lowongan aktif dan cakupan multi-status: <strong>Belum disepakati</strong>.
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h6 mb-3">Cari dan filter lowongan</h2>
            <form method="GET" @if ($indexAvailable) action="{{ route('government.job-monitoring.index') }}" @endif>
                <input type="hidden" name="per_page" value="{{ $filters['per_page'] ?? 15 }}">
                <fieldset @disabled(! $indexAvailable)>
                    <legend class="visually-hidden">Pencarian dan empat filter monitoring lowongan</legend>
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="job-monitoring-q" class="form-label">Judul lowongan atau nama perusahaan</label>
                            <input id="job-monitoring-q" type="search" name="q" maxlength="255" value="{{ $filters['q'] ?? '' }}" class="form-control @error('q') is-invalid @enderror" placeholder="Cari judul lowongan atau perusahaan" @error('q') aria-invalid="true" aria-describedby="job-monitoring-q-error" @enderror>
                            @error('q') <div id="job-monitoring-q-error" class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="job-monitoring-company" class="form-label">Perusahaan</label>
                            <select id="job-monitoring-company" name="company" class="form-select @error('company') is-invalid @enderror" @error('company') aria-invalid="true" aria-describedby="job-monitoring-company-error" @enderror>
                                <option value="">Semua perusahaan</option>
                                @if (filled($filters['company'] ?? null) && ! $companyChoices->contains(fn ($company) => (string) $company->id === (string) $filters['company']))
                                    <option value="{{ $filters['company'] }}" selected>Perusahaan ID {{ $filters['company'] }} (tidak ada dalam opsi monitoring)</option>
                                @endif
                                @foreach ($companyChoices as $company)
                                    <option value="{{ $company->id }}" @selected((string) ($filters['company'] ?? '') === (string) $company->id)>{{ $company->company_name }}</option>
                                @endforeach
                            </select>
                            @error('company') <div id="job-monitoring-company-error" class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="job-monitoring-category" class="form-label">Bidang pekerjaan</label>
                            <select id="job-monitoring-category" name="category" class="form-select @error('category') is-invalid @enderror" @error('category') aria-invalid="true" aria-describedby="job-monitoring-category-error" @enderror>
                                <option value="">Semua bidang pekerjaan</option>
                                @if (filled($filters['category'] ?? null) && ! $categoryChoices->containsStrict($filters['category']))
                                    <option value="{{ $filters['category'] }}" selected>{{ $filters['category'] }} (tidak ada dalam opsi monitoring)</option>
                                @endif
                                @foreach ($categoryChoices as $category)
                                    <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
                                @endforeach
                            </select>
                            @error('category') <div id="job-monitoring-category-error" class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="job-monitoring-location" class="form-label">Lokasi</label>
                            <input id="job-monitoring-location" type="search" name="location" maxlength="255" value="{{ $filters['location'] ?? '' }}" class="form-control @error('location') is-invalid @enderror" placeholder="Nama kota atau wilayah" @error('location') aria-invalid="true" aria-describedby="job-monitoring-location-error" @enderror>
                            @error('location') <div id="job-monitoring-location-error" class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="job-monitoring-status" class="form-label">Status</label>
                            <select id="job-monitoring-status" name="status" class="form-select @error('status') is-invalid @enderror" aria-describedby="job-monitoring-status-help @error('status') job-monitoring-status-error @enderror" @error('status') aria-invalid="true" @enderror>
                                @if (empty($statusOptions))
                                    <option value="">Pilihan status belum tersedia</option>
                                @endif
                                {{-- Backend memasok opsi yang disepakati; tidak menetapkan cakupan status di view. --}}
                                @foreach ($statusOptions ?? [] as $value => $label)
                                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === (string) $value)>{{ $label }} ({{ $value }})</option>
                                @endforeach
                            </select>
                            <div id="job-monitoring-status-help" class="form-text">Saat ini hanya published; cakupan multi-status: Belum disepakati.</div>
                            @error('status') <div id="job-monitoring-status-error" class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary">Terapkan pencarian</button>
                            @if ($indexAvailable)
                                <a class="btn btn-outline-secondary" href="{{ route('government.job-monitoring.index') }}">Reset filter</a>
                            @else
                                <button type="button" class="btn btn-outline-secondary" disabled>Reset filter</button>
                            @endif
                        </div>
                    </div>
                </fieldset>
            </form>
            @if (! $indexAvailable)
                <p class="small text-muted mt-3 mb-0" role="status">Pencarian dan filter belum terhubung ke backend.</p>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="h6 mb-0">Daftar lowongan monitoring</h2>
            @if (isset($jobs))
                <span class="badge bg-light text-dark border">{{ $jobs->total() }} lowongan{{ $hasFilters ? ' sesuai filter' : '' }}</span>
            @endif
        </div>
        <div class="table-responsive" role="region" aria-label="Daftar monitoring lowongan kerja" tabindex="0">
            <table class="table table-bordered table-hover align-middle mb-0 small">
                <caption class="visually-hidden">Informasi lowongan untuk monitoring pemerintah. Cakupan lowongan aktif masih menunggu kesepakatan.</caption>
                <thead class="table-light">
                    <tr>
                        <th scope="col">Judul lowongan</th>
                        <th scope="col">Nama perusahaan</th>
                        <th scope="col">Bidang/kategori pekerjaan</th>
                        <th scope="col">Lokasi</th>
                        <th scope="col">Status</th>
                        <th scope="col">Deadline</th>
                        <th scope="col" class="text-end">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    @if (! isset($jobs))
                        <tr><td colspan="7" class="text-center py-5" role="status">Data monitoring belum tersedia. Halaman ini belum terhubung ke backend.</td></tr>
                    @else
                        @forelse ($jobs as $job)
                            <tr>
                                <th scope="row" class="fw-semibold text-break">
                                    {{ filled($job->title) ? $job->title : 'Belum tersedia' }}
                                    @if ($job->reviewFlag?->isActive())
                                        <span class="d-block mt-1"><span class="badge bg-warning text-dark text-wrap">Perlu Ditinjau</span></span>
                                    @endif
                                </th>
                                <td class="text-break">{{ filled($job->company?->company_name) ? $job->company->company_name : 'Belum tersedia' }}</td>
                                <td class="text-break">{{ filled($job->category) ? $job->category : 'Belum tersedia' }}</td>
                                <td class="text-break">{{ filled($job->location) ? $job->location : 'Belum tersedia' }}</td>
                                <td><span class="badge bg-light text-dark border text-wrap">{{ $statusLabels[$job->status] ?? (filled($job->status) ? $job->status : 'Belum tersedia') }}</span></td>
                                <td class="text-nowrap">
                                    @if ($job->deadline !== null)
                                        <time datetime="{{ $job->deadline->format('Y-m-d') }}">{{ $job->deadline->format('d M Y') }}</time>
                                    @else
                                        <span class="text-muted">Belum tersedia</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    @if ($detailAvailable)
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('government.job-monitoring.show', ['jobPosting' => $job->id]) }}" aria-label="Lihat detail lowongan {{ $job->title }}">Lihat Detail</a>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-primary" disabled>Lihat Detail</button>
                                        <span class="d-block small text-muted mt-1">Detail belum tersedia</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <h3 class="h6">{{ $hasFilters ? 'Tidak ada lowongan yang sesuai' : 'Belum ada lowongan dalam daftar monitoring' }}</h3>
                                    <p class="text-muted mb-0">{{ $hasFilters ? 'Ubah pencarian atau reset filter untuk melihat lowongan lainnya.' : 'Lowongan akan ditampilkan setelah data monitoring tersedia.' }}</p>
                                    @if ($hasFilters && $indexAvailable)
                                        <a class="btn btn-outline-secondary btn-sm mt-3" href="{{ route('government.job-monitoring.index') }}">Reset filter</a>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    @endif
                </tbody>
            </table>
        </div>
        @if (isset($jobs) && $jobs->total() > 0)
            <div class="card-body border-top">
                <p class="small text-muted mb-2" role="status">Menampilkan {{ $jobs->firstItem() ?? 0 }}–{{ $jobs->lastItem() ?? 0 }} dari {{ $jobs->total() }} lowongan.</p>
                {{ $jobs->appends(collect($filters)->only(['q', 'company', 'category', 'location', 'status', 'per_page'])->all())->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
@endsection
