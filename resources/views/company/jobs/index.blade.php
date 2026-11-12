@extends('company.layouts.app')
@section('title', 'Kelola & Pantau Status Lowongan Kerja - SIkERJA')
@section('content')
<!-- Header Ringkasan Lowongan Perusahaan -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div>
                <h4 class="mb-1">Kelola & Pantau Status Lowongan Kerja</h4>
                <div class="text-muted small">
                    Perusahaan: <strong>{{ $profile->company_name }}</strong> | Pantau status peninjauan pemerintah (Kemnaker), perbarui informasi, atau tutup lowongan.
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('company.jobs.create') }}" class="btn btn-primary btn-sm">
                    + Buat & Ajukan Lowongan Baru
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Ringkasan Status Lowongan -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
        <a href="{{ route('company.jobs.index') }}" class="text-decoration-none">
            <div class="card h-100 {{ empty($statusFilter) ? 'border-primary' : '' }}">
                <div class="card-body py-2 px-3">
                    <div class="text-muted small">Semua</div>
                    <div class="fs-5 fw-bold text-dark">{{ $summary['total'] }}</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-2">
        <a href="{{ route('company.jobs.index', ['status' => 'draft']) }}" class="text-decoration-none">
            <div class="card h-100 {{ $statusFilter === 'draft' ? 'border-primary' : '' }}">
                <div class="card-body py-2 px-3">
                    <div class="text-muted small">Draf</div>
                    <div class="fs-5 fw-bold text-secondary">{{ $summary['draft'] }}</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-2">
        <a href="{{ route('company.jobs.index', ['status' => 'pending_approval']) }}" class="text-decoration-none">
            <div class="card h-100 {{ $statusFilter === 'pending_approval' ? 'border-primary' : '' }}">
                <div class="card-body py-2 px-3">
                    <div class="text-muted small">Menunggu</div>
                    <div class="fs-5 fw-bold text-warning">{{ $summary['pending_approval'] }}</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-2">
        <a href="{{ route('company.jobs.index', ['status' => 'published']) }}" class="text-decoration-none">
            <div class="card h-100 {{ $statusFilter === 'published' ? 'border-primary' : '' }}">
                <div class="card-body py-2 px-3">
                    <div class="text-muted small">Disetujui</div>
                    <div class="fs-5 fw-bold text-success">{{ $summary['published'] }}</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-2">
        <a href="{{ route('company.jobs.index', ['status' => 'rejected']) }}" class="text-decoration-none">
            <div class="card h-100 {{ $statusFilter === 'rejected' ? 'border-primary' : '' }}">
                <div class="card-body py-2 px-3">
                    <div class="text-muted small">Ditolak</div>
                    <div class="fs-5 fw-bold text-danger">{{ $summary['rejected'] }}</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-2">
        <a href="{{ route('company.jobs.index', ['status' => 'closed']) }}" class="text-decoration-none">
            <div class="card h-100 {{ $statusFilter === 'closed' ? 'border-primary' : '' }}">
                <div class="card-body py-2 px-3">
                    <div class="text-muted small">Ditutup</div>
                    <div class="fs-5 fw-bold text-dark">{{ $summary['closed'] }}</div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Tabel Daftar Lowongan dan  Status -->
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Daftar Lowongan Kerja Perusahaan</h6>
        <span class="badge bg-light text-dark border">Menampilkan {{ $jobs->total() }} Lowongan</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Posisi & Bidang</th>
                        <th>Lokasi & Tipe</th>
                        <th>Gaji & Kuota</th>
                        <th>Tenggat</th>
                        <th>Status Peninjauan Pemerintah</th>
                        <th>Pelamar</th>
                        <th class="text-end" style="min-width: 170px;">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jobs as $job)
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">{{ $job->title }}</div>
                                <div class="text-muted">{{ $job->category }} | Min. {{ $job->education_requirement }}</div>
                            </td>
                            <td>
                                <div>{{ $job->location }}</div>
                                <div class="text-muted">{{ $job->job_type }}</div>
                            </td>
                            <td>
                                <div class="fw-semibold text-success">
                                    Rp {{ number_format((float) $job->salary_around, 0, ',', '.') }}
                                </div>
                                <div class="text-muted">Kuota: {{ $job->quota }} orang</div>
                            </td>
                            <td>
                                {{ $job->deadline ? $job->deadline->format('d/m/Y') : '-' }}
                            </td>
                            <td>
                                @php
                                    $badgeClass = match ($job->status) {
                                        'published' => 'bg-success',
                                        'pending_approval' => 'bg-warning text-dark',
                                        'rejected' => 'bg-danger',
                                        'closed' => 'bg-secondary',
                                        default => 'bg-info text-dark',
                                    };
                                    $statusLabel = match ($job->status) {
                                        'published' => 'Disetujui / Aktif',
                                        'pending_approval' => 'Menunggu Tinjauan',
                                        'rejected' => 'Ditolak Pemerintah',
                                        'closed' => 'Ditutup',
                                        default => 'Draf',
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>

                                @if ($job->rejection_reason)
                                    <div class="alert alert-danger py-1 px-2 mt-2 mb-0" style="font-size: 0.75rem;">
                                        <strong>Alasan Penolakan:</strong> {{ $job->rejection_reason }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border">
                                    {{ $job->applications_count ?? 0 }} Pelamar
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1 flex-wrap">
                                    {{-- Tombol Ubah Lowongan --}}
                                    <a href="{{ route('company.jobs.edit', $job->id) }}" class="btn btn-sm btn-outline-primary">
                                        Ubah
                                    </a>

                                    {{-- Tombol Tutup / Ajukan Lowongan --}}
                                    <form action="{{ route('company.jobs.toggle', $job->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        @if ($job->status === 'published')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                Tutup Lowongan
                                            </button>
                                        @else
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                Ajukan ke Pemerintah
                                            </button>
                                        @endif
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                Belum ada data lowongan kerja. Silakan klik tombol <strong>Buat & Ajukan Lowongan Baru</strong>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if ($jobs->hasPages())
        <div class="card-footer bg-white d-flex justify-content-center">
            {{ $jobs->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
