@extends('government.layouts.app')
@section('title', 'Detail Status Verifikasi Perusahaan - SIkERJA')

@section('content')
@can('access-government')
    @if (Route::has('government.company-verification-statuses.index'))
        <a class="btn btn-sm btn-outline-secondary mb-3" href="{{ route('government.company-verification-statuses.index', $returnFilters ?? []) }}">Kembali ke monitoring</a>
    @endif
    <h1 class="h4 mb-3">Detail Status Verifikasi Perusahaan</h1>
    @if (filled($pageError ?? null))<div class="alert alert-danger" role="alert">{{ $pageError }}</div>@endif
    @if (! isset($companyData))
        <div class="alert alert-secondary" role="status">Detail perusahaan belum tersedia. Integrasi backend diperlukan.</div>
    @else
        <div class="row g-4">
            <div class="col-lg-6">
                <section class="card mb-4" aria-labelledby="monitoring-identity">
                    <div class="card-header bg-white"><h2 id="monitoring-identity" class="h6 mb-0">Identitas perusahaan</h2></div>
                    <div class="card-body">
                        <p class="small text-muted">Informasi hanya dapat dibaca pada halaman monitoring.</p>
                        <dl class="row text-break mb-0">
                            @foreach (['Nama perusahaan' => 'company_name', 'NPWP' => 'npwp', 'Alamat' => 'address', 'Kota / kabupaten' => 'city', 'Provinsi' => 'province'] as $label => $key)
                                <dt class="col-sm-5">{{ $label }}</dt><dd class="col-sm-7">{{ filled($companyData[$key] ?? null) ? $companyData[$key] : 'Belum tersedia' }}</dd>
                            @endforeach
                        </dl>
                    </div>
                </section>
                <section class="card" aria-labelledby="monitoring-status">
                    <div class="card-header bg-white"><h2 id="monitoring-status" class="h6 mb-0">Status terkini dan sumbernya</h2></div>
                    <div class="card-body">
                        @include('government.company-verification-statuses.partials.status', ['status' => $companyData['verification_status'], 'provenance' => $companyData['provenance']])
                        <p class="mt-3 mb-0">
                            @switch($companyData['provenance'])
                                @case('official') Status didukung record pemeriksaan pemerintah yang sesuai. @break
                                @case('legacy') Status final legacy belum didukung record pemeriksaan resmi. Jangan menganggapnya bukti pengecekan OSS. @break
                                @case('uninspected') Perusahaan belum memiliki hasil pemeriksaan resmi. @break
                                @default Sumber atau konsistensi status belum dapat dipastikan.
                            @endswitch
                        </p>
                        @if (($canInspect ?? false) === true && Route::has('government.company-inspections.show'))
                            <a class="btn btn-primary btn-sm mt-3" href="{{ route('government.company-inspections.show', ['companyProfile' => $companyData['id']]) }}">Buka pemeriksaan perusahaan</a>
                        @endif
                    </div>
                </section>
            </div>
            <div class="col-lg-6">
                <section class="card" aria-labelledby="monitoring-inspection">
                    <div class="card-header bg-white"><h2 id="monitoring-inspection" class="h6 mb-0">Hasil pemeriksaan terkini</h2></div>
                    <div class="card-body">
                        @if (empty($inspectionData))
                            <p class="text-muted mb-0">Belum ada hasil pemeriksaan resmi yang tersedia.</p>
                        @else
                            <dl class="row mb-0 text-break">
                                <dt class="col-sm-5">OSS</dt><dd class="col-sm-7">{{ ['matched' => 'Sesuai', 'mismatched' => 'Tidak sesuai', 'unconfirmed' => 'Belum dapat dipastikan'][$inspectionData['oss_check_result'] ?? ''] ?? 'Tidak tersedia' }}</dd>
                                <dt class="col-sm-5">Administrasi</dt><dd class="col-sm-7">{{ ['complete' => 'Lengkap', 'incomplete' => 'Tidak lengkap', 'unconfirmed' => 'Belum dapat dipastikan'][$inspectionData['administrative_result'] ?? ''] ?? 'Tidak tersedia' }}</dd>
                                <dt class="col-sm-5">Tanggal pemeriksaan</dt><dd class="col-sm-7">{{ $inspectionData['checked_on'] ?? 'Tidak tersedia' }}</dd>
                                <dt class="col-sm-5">Petugas</dt><dd class="col-sm-7">{{ $inspectionData['reviewer_name'] ?? 'Tidak tersedia' }}</dd>
                            </dl>
                            @if (($canViewInspectionNotes ?? false) === true && filled($inspectionData['notes'] ?? null))
                                <h3 class="h6 mt-3">Catatan internal pemerintah</h3>
                                <p class="text-break mb-0" style="white-space: pre-wrap">{{ $inspectionData['notes'] }}</p>
                            @endif
                        @endif
                    </div>
                </section>
            </div>
        </div>
        <section class="card mt-4" aria-labelledby="monitoring-history">
            <div class="card-header bg-white"><h2 id="monitoring-history" class="h6 mb-0">Riwayat perubahan status</h2></div>
            <div class="card-body">
                <p class="text-muted">Riwayat sebelum pencatatan diaktifkan tidak tersedia.</p>
                @if (($historyAvailable ?? false) !== true)
                    <p class="mb-0">Layanan riwayat belum terhubung. Tidak ada timeline yang direkonstruksi dari tanggal profil.</p>
                @elseif (! isset($historyEntries))
                    <div class="alert alert-warning mb-0" role="alert">Data riwayat belum dapat dimuat.</div>
                @elseif ($historyEntries->currentPage() > $historyEntries->lastPage())
                    <p role="alert">Halaman riwayat di luar rentang. Kembali ke detail tanpa parameter halaman riwayat.</p>
                    @if (Route::has('government.company-verification-statuses.show'))<a href="{{ route('government.company-verification-statuses.show', ['companyProfile' => $companyData['id']]) }}">Halaman pertama riwayat</a>@endif
                @else
                    <ol class="list-group list-group-numbered mb-3">
                        @forelse ($historyEntries as $entry)
                            <li class="list-group-item text-break">
                                <div class="fw-semibold">{{ $entry['old_status_label'] }} → {{ $entry['new_status_label'] }}</div>
                                <div class="small text-muted">{{ $entry['changed_at_label'] }} · {{ $entry['actor_label'] }}</div>
                                <p class="mb-0">{{ $entry['reason_label'] }}</p>
                            </li>
                        @empty
                            <li class="list-group-item text-muted">Belum ada perubahan status tercatat sejak pencatatan diaktifkan.</li>
                        @endforelse
                    </ol>
                    {{ $historyEntries->appends($returnFilters ?? [])->links('pagination::bootstrap-5') }}
                @endif
            </div>
        </section>
    @endif
@else
    <div class="alert alert-danger" role="alert">Akses detail monitoring hanya untuk Admin Pemerintah aktif.</div>
@endcan
@endsection
