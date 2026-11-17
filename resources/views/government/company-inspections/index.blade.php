@extends('government.layouts.app')
@section('title', 'Antrean Pemeriksaan Perusahaan - SIkERJA')

@section('content')
    <div class="card mb-4">
        <div class="card-body">
            <h1 class="h4">Pemeriksaan Perusahaan</h1>
            <p class="text-muted mb-0">Periksa legalitas dan kelengkapan administrasi perusahaan yang menunggu pemeriksaan.</p>
        </div>
    </div>
    @if (! isset($companies))
        <div class="alert alert-warning" role="alert">Antrean belum tersedia. Data perusahaan perlu disediakan oleh pengelola sistem.</div>
    @else
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" @if (Route::has('government.company-inspections.index')) action="{{ route('government.company-inspections.index') }}" @endif>
                    <label for="company-search" class="form-label">Cari nama perusahaan atau NPWP</label>
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <input id="company-search" type="search" name="q" maxlength="100" value="{{ $filters['q'] ?? '' }}" class="form-control @error('q') is-invalid @enderror" @error('q') aria-invalid="true" aria-describedby="q-error" @enderror>
                        <button class="btn btn-primary" type="submit">Cari</button>
                        @if (Route::has('government.company-inspections.index'))
                            <a class="btn btn-outline-secondary" href="{{ route('government.company-inspections.index') }}">Reset</a>
                        @endif
                    </div>
                    @error('q') <div id="q-error" class="text-danger small mt-1">{{ $message }}</div> @enderror
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between gap-2">
                <h2 class="h6 mb-0">Perusahaan perlu diperiksa</h2>
                <span class="badge bg-light text-dark border">{{ $companies->total() }} perusahaan</span>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <caption class="visually-hidden">Antrean perusahaan berstatus pending yang perlu diperiksa</caption>
                    <thead class="table-light"><tr>
                        <th scope="col">Perusahaan</th><th scope="col">NPWP</th><th scope="col">Dokumen legalitas</th><th scope="col">Status verifikasi</th><th scope="col">Tindakan</th>
                    </tr></thead>
                    <tbody>
                        @forelse ($companies as $company)
                            <tr>
                                <th scope="row" class="fw-normal">
                                    <div class="fw-semibold text-break">{{ $company->company_name }}</div>
                                    <div class="small text-muted">{{ collect([$company->city, $company->province])->filter()->implode(', ') ?: 'Lokasi belum tersedia' }}</div>
                                </th>
                                <td class="text-break">{{ $company->npwp ?: 'Belum tersedia' }}</td>
                                <td class="small">
                                    {{-- document_available adalah atribut presentasi dari controller, bukan kolom baru. --}}
                                    @if ($company->document_available === true)
                                        Tersedia <span class="d-block text-muted">Belum menyatakan keabsahan dokumen</span>
                                    @elseif ($company->document_available === false)
                                        Belum tersedia / tidak dapat diakses
                                    @else
                                        Ketersediaan belum diperiksa
                                    @endif
                                </td>
                                <td>@include('government.company-inspections.partials.status', ['status' => $company->verification_status, 'hasInspection' => $company->inspection !== null])</td>
                                <td>
                                    @if (Route::has('government.company-inspections.show'))
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('government.company-inspections.show', ['companyProfile' => $company->id, 'q' => $filters['q'] ?? '', 'page' => $companies->currentPage()]) }}" aria-label="Lihat detail {{ $company->company_name }}">Lihat detail</a>
                                    @else
                                        <span class="small text-muted">Detail belum tersedia</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-5">
                                {{ filled($filters['q'] ?? '') ? 'Tidak ada perusahaan yang cocok dengan pencarian Anda.' : 'Tidak ada perusahaan yang menunggu pemeriksaan.' }}
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($companies->total() > 0)
                <div class="card-body border-top">
                    <p class="small text-muted">Menampilkan {{ $companies->firstItem() ?? 0 }}–{{ $companies->lastItem() ?? 0 }} dari {{ $companies->total() }} perusahaan.</p>
                    {{ $companies->appends(['q' => $filters['q'] ?? ''])->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    @endif
@endsection
