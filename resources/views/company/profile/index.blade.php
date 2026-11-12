@extends('company.layouts.app')

@section('title', 'Profil Perusahaan - SIkERJA')

@section('content')
<!-- Ringkasan Profil Perusahaan -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div>
                <h4 class="mb-1">{{ $profile->company_name }}</h4>
                <div class="text-muted small">
                    Penanggung Jawab: {{ $user->name }} | {{ $user->email }}
                    @if ($user->phone) | {{ $user->phone }} @endif
                    @if ($profile->city || $profile->province) | {{ trim(($profile->city ?? '').', '.($profile->province ?? ''), ', ') }} @endif
                </div>
                <div class="mt-2">
                    <span class="badge {{ $profile->status_badge_class }}">
                        {{ $profile->formatted_status }}
                    </span>
                </div>
            </div>

            <div class="d-flex gap-2">
                <a href="{{ route('company.profile.edit') }}" class="btn btn-primary btn-sm">
                    Ubah Profil Perusahaan
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Status Pemeriksaan & Validasi Identitas (PBI 2.1.1) -->
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Pemeriksaan Identitas Perusahaan</h6>
                <span class="badge {{ $profile->status_badge_class }}">
                    {{ $profile->formatted_status }}
                </span>
            </div>
            <div class="card-body">
                <div class="alert {{ $profile->status_alert_class }} py-2 small mb-3">
                    @if ($profile->isVerified())
                        <strong>SKENARIO VALID (TERVERIFIKASI)</strong><br>
                        {{ $profile->verification_notes ?? 'Pemeriksaan berhasil: Identitas dan NPWP perusahaan valid serta terverifikasi.' }}
                    @elseif ($profile->isRejected())
                        <strong>SKENARIO TIDAK VALID (DITOLAK)</strong><br>
                        {{ $profile->verification_notes ?? 'Pemeriksaan gagal: Identitas perusahaan dinyatakan tidak valid.' }}
                    @else
                        <strong>SKENARIO BELUM DAPAT DIPASTIKAN</strong><br>
                        {{ $profile->verification_notes ?? 'Pemeriksaan tertunda: Identitas menunggu verifikasi manual oleh petugas.' }}
                    @endif
                </div>

                <div class="mb-3">
                    <div class="fw-semibold small mb-1">Dokumen Legalitas Perusahaan</div>
                    @if ($profile->company_document_path)
                        <div class="d-flex justify-content-between align-items-center border rounded p-2 small">
                            <span class="text-truncate me-2">{{ basename($profile->company_document_path) }}</span>
                            <a href="{{ route('company.profile.document.download') }}" class="btn btn-sm btn-outline-primary">
                                Unduh Berkas
                            </a>
                        </div>
                    @else
                        <div class="text-muted small fst-italic">Belum ada dokumen yang diunggah.</div>
                    @endif
                </div>

                @if (isset($inspection['checks']))
                    <div class="fw-semibold small mb-1">Rincian Hasil Pemeriksaan</div>
                    <table class="table table-bordered table-sm small mb-3">
                        <thead class="table-light">
                            <tr>
                                <th>Komponen</th>
                                <th class="text-nowrap" style="width: 25%;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($inspection['checks'] as $check)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $check['label'] }}</div>
                                        <div class="text-muted" style="font-size: 0.75rem;">{{ $check['detail'] }}</div>
                                    </td>
                                    <td class="align-middle text-nowrap">
                                        @if ($check['passed'] === true)
                                            <span class="badge bg-success">Valid</span>
                                        @elseif ($check['passed'] === false)
                                            <span class="badge bg-danger">Ditolak</span>
                                        @else
                                            <span class="badge bg-secondary">Menunggu</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                <div class="border rounded p-3 bg-light">
                    <div class="fw-semibold small mb-2">Uji Skenario Pemeriksaan</div>
                    <form action="{{ route('company.profile.identity.verify') }}" method="POST">
                        @csrf
                        <div class="mb-2">
                            <select name="scenario_override" class="form-select form-select-sm">
                                <option value="valid" @selected($profile->verification_status === 'verified')>Skenario 1: Valid / Terverifikasi</option>
                                <option value="invalid" @selected($profile->verification_status === 'rejected')>Skenario 2: Tidak Valid / Ditolak</option>
                                <option value="unconfirmed" @selected($profile->verification_status === 'pending')>Skenario 3: Belum Dapat Dipastikan</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-sm btn-secondary w-100">
                            Perbarui Status Pemeriksaan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Informasi Profil Perusahaan (PBI 2.1.2) -->
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Informasi Profil Perusahaan</h6>
                <a href="{{ route('company.profile.edit') }}" class="btn btn-sm btn-outline-primary">
                    Ubah Data
                </a>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-sm small mb-0">
                    <tbody>
                        <tr>
                            <th class="bg-light w-35">Nama Perusahaan</th>
                            <td>{{ $profile->company_name }}</td>
                        </tr>
                        <tr>
                            <th class="bg-light">Nomor NPWP</th>
                            <td>{{ $profile->npwp ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th class="bg-light">Penanggung Jawab (PIC)</th>
                            <td>{{ $user->name }}</td>
                        </tr>
                        <tr>
                            <th class="bg-light">Email Resmi</th>
                            <td>{{ $user->email }}</td>
                        </tr>
                        <tr>
                            <th class="bg-light">Nomor Telepon</th>
                            <td>{{ $user->phone ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th class="bg-light">Website Resmi</th>
                            <td>
                                @if ($profile->website)
                                    <a href="{{ $profile->website }}" target="_blank" rel="noopener noreferrer">
                                        {{ $profile->website }}
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th class="bg-light">Provinsi</th>
                            <td>{{ $profile->province ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th class="bg-light">Kota / Kabupaten</th>
                            <td>{{ $profile->city ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th class="bg-light">Alamat Kantor</th>
                            <td>{{ $profile->address ?? '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
