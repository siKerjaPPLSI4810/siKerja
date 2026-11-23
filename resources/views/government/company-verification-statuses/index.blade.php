@extends('government.layouts.app')
@section('title', 'Monitoring Status Verifikasi Perusahaan - SIkERJA')

@section('content')
@can('access-government')
    @php
        $statusOptions = ['' => 'Semua', 'pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected'];
        $selectedStatus = $filters['status'] ?? '';
        $validFilter = is_string($selectedStatus) && array_key_exists($selectedStatus, $statusOptions);
        $search = is_string($filters['q'] ?? null) ? $filters['q'] : '';
        $indexReady = Route::has('government.company-verification-statuses.index');
        $outOfRange = isset($companies) && $companies->currentPage() > $companies->lastPage();
    @endphp
    <div class="card mb-4"><div class="card-body">
        <h1 class="h4">Monitoring Status Verifikasi Perusahaan</h1>
        <p class="text-muted mb-0">Lihat perusahaan menurut status terkini dan ketersediaan hasil pemeriksaan resmi.</p>
    </div></div>
    @if (filled($pageError ?? null))
        <div class="alert alert-danger" role="alert">{{ $pageError }}</div>
    @endif
    @if (! $validFilter)
        <div class="alert alert-warning" role="alert">Filter status tidak valid. Pilih Semua, Pending, Verified, atau Rejected lalu cari kembali.</div>
    @endif
    <div class="card mb-4"><div class="card-body">
        <form method="GET" @if ($indexReady) action="{{ route('government.company-verification-statuses.index') }}" @endif>
            <fieldset @disabled(! $indexReady)>
                <legend class="h6">Filter perusahaan</legend>
                <label for="verification-status" class="form-label">Status verifikasi</label>
                <select id="verification-status" name="status" class="form-select mb-3 @error('status') is-invalid @enderror" @if (! $validFilter || $errors->has('status')) aria-invalid="true" @endif @error('status') aria-describedby="status-filter-error" @enderror>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($validFilter && $selectedStatus === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status') <div class="text-danger small mb-3" id="status-filter-error">{{ $message }}</div> @enderror
                <label for="verification-search" class="form-label">Nama perusahaan atau NPWP</label>
                <div class="d-flex flex-column flex-sm-row gap-2">
                    <input id="verification-search" name="q" type="search" maxlength="100" value="{{ $search }}" class="form-control @error('q') is-invalid @enderror" @error('q') aria-invalid="true" aria-describedby="verification-search-error" @enderror>
                    <button class="btn btn-primary" type="submit">Cari</button>
                    @if ($indexReady)<a class="btn btn-outline-secondary" href="{{ route('government.company-verification-statuses.index') }}">Reset</a>@endif
                </div>
                @error('q') <div class="text-danger small mt-1" id="verification-search-error">{{ $message }}</div> @enderror
                @if (isset($companies))<input type="hidden" name="per_page" value="{{ $companies->perPage() }}">@endif
            </fieldset>
        </form>
        @if (! $indexReady)<p class="small text-muted mt-2 mb-0">Filter belum aktif karena route monitoring belum tersedia.</p>@endif
    </div></div>
    @if (! isset($companies))
        <div class="alert alert-secondary" role="status">Data monitoring belum tersedia. Halaman ini memerlukan integrasi backend.</div>
    @elseif (! $validFilter || filled($pageError ?? null))
        <p class="text-muted">Daftar tidak ditampilkan sampai permintaan dapat diproses dengan benar.</p>
    @elseif ($outOfRange)
        <div class="alert alert-warning" role="alert">
            Halaman yang diminta di luar rentang. Muat halaman pertama untuk melihat data yang tersedia.
            @if ($indexReady)
                <a href="{{ route('government.company-verification-statuses.index', ['status' => $selectedStatus, 'q' => $search, 'per_page' => $companies->perPage()]) }}">Kembali ke halaman pertama</a>
            @endif
        </div>
    @else
        <div class="card">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between gap-2">
                <h2 class="h6 mb-0">Daftar perusahaan</h2><span class="small">{{ $companies->total() }} perusahaan sesuai filter</span>
            </div>
            <div class="table-responsive" tabindex="0" role="region" aria-label="Tabel status verifikasi, gulir horizontal bila diperlukan">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <caption class="visually-hidden">Perusahaan dan sumber status verifikasi terkini</caption>
                    <thead class="table-light"><tr><th scope="col">Perusahaan</th><th scope="col">NPWP</th><th scope="col">Status terkini</th><th scope="col">Pemeriksaan resmi</th><th scope="col">Tindakan</th></tr></thead>
                    <tbody>
                    @forelse ($companies as $row)
                        <tr>
                            <th scope="row" class="fw-normal text-break"><strong>{{ $row['company_name'] }}</strong></th>
                            <td class="text-break">{{ $row['npwp'] ?: 'Belum tersedia' }}</td>
                            <td>@include('government.company-verification-statuses.partials.status', ['status' => $row['verification_status'], 'provenance' => $row['provenance']])</td>
                            <td class="small">
                                @switch($row['provenance'])
                                    @case('official') Hasil pemeriksaan resmi tersedia. @break
                                    @case('legacy') <span class="text-warning-emphasis">Status final legacy; belum ada record pemeriksaan resmi.</span> @break
                                    @case('uninspected') Belum ada hasil pemeriksaan. @break
                                    @default Sumber status belum dapat dipastikan.
                                @endswitch
                            </td>
                            <td>
                                @if (Route::has('government.company-verification-statuses.show'))
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('government.company-verification-statuses.show', ['companyProfile' => $row['id'], 'status' => $selectedStatus, 'q' => $search, 'page' => $companies->currentPage(), 'per_page' => $companies->perPage()]) }}" aria-label="Lihat detail {{ $row['company_name'] }}">Lihat detail</a>
                                @else <span class="small text-muted">Detail belum tersedia</span> @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-5">{{ $selectedStatus !== '' || $search !== '' ? 'Tidak ada perusahaan yang sesuai filter atau pencarian.' : 'Belum ada data perusahaan.' }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($companies->total() > 0)
                <div class="card-body border-top">
                    <p class="small text-muted">Menampilkan {{ $companies->firstItem() }}–{{ $companies->lastItem() }} dari {{ $companies->total() }} perusahaan.</p>
                    {{ $companies->appends(['status' => $selectedStatus, 'q' => $search, 'per_page' => $companies->perPage()])->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    @endif
@else
    <div class="alert alert-danger" role="alert">Akses monitoring hanya untuk Admin Pemerintah aktif.</div>
@endcan
@endsection
