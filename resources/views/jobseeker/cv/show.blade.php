@extends('jobseeker.layouts.app')

@section('title', 'CV Otomatis - SIkERJA')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-2 no-print">
    <div>
        <h4 class="mb-0">CV Otomatis Pencari Kerja</h4>
        <div class="text-muted small">Kelengkapan Profil: {{ $completeness['percentage'] }}% (Data otomatis mengikuti perubahan profil)</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('jobseeker.profile.index') }}" class="btn btn-outline-secondary btn-sm">Kembali ke Profil</a>
        <a href="{{ route('jobseeker.cv.print') }}" target="_blank" class="btn btn-outline-primary btn-sm">Halaman Cetak</a>
        <a href="{{ route('jobseeker.cv.download') }}" class="btn btn-primary btn-sm">Unduh CV (PDF)</a>
    </div>
</div>

<div class="card">
    <div class="card-body p-4">
        <!-- Header Data Diri -->
        <div class="border-bottom pb-3 mb-3">
            <h3 class="mb-1">{{ $user->name }}</h3>
            <div class="small text-muted mb-1">
                Email: {{ $user->email }}
                @if ($user->phone) | Telepon: {{ $user->phone }} @endif
                @if ($profile->city || $profile->province) | Domisili: {{ trim(($profile->city ?? '').', '.($profile->province ?? ''), ', ') }} @endif
            </div>
            <div class="small">
                <strong>NIK:</strong> {{ $profile->nik ?: '-' }}
                ({{ $profile->nik_verified ? 'Terverifikasi' : 'Belum Terverifikasi' }})
                @if ($profile->gender) | <strong>Jenis Kelamin:</strong> {{ $profile->gender }} @endif
                @if ($profile->birth_date) | <strong>Tanggal Lahir:</strong> {{ $profile->birth_date->format('d M Y') }} @endif
                @if ($profile->expected_salary) | <strong>Ekspektasi Gaji:</strong> Rp {{ number_format((float) $profile->expected_salary, 0, ',', '.') }} @endif
            </div>
            @if ($profile->address)
                <div class="small mt-1"><strong>Alamat:</strong> {{ $profile->address }}</div>
            @endif
        </div>

        <!-- Ringkasan Diri -->
        <div class="mb-4">
            <h6 class="fw-bold text-uppercase border-bottom pb-1">Ringkasan Profil</h6>
            @if ($profile->bio)
                <p class="mb-0 small">{{ $profile->bio }}</p>
            @else
                <p class="text-muted small mb-0">Belum ada ringkasan profil.</p>
            @endif
        </div>

        <!-- Pendidikan -->
        <div class="mb-4">
            <h6 class="fw-bold text-uppercase border-bottom pb-1">Riwayat Pendidikan</h6>
            @forelse ($profile->educations as $edu)
                <div class="d-flex justify-content-between small mb-2">
                    <div>
                        <div class="fw-bold">{{ $edu->institution_name }}</div>
                        <div>{{ $edu->degree }} - {{ $edu->major }}</div>
                    </div>
                    <div class="text-muted">{{ $edu->start_year }} - {{ $edu->end_year ?: 'Sekarang' }}</div>
                </div>
            @empty
                <p class="text-muted small mb-0">Belum ada data pendidikan.</p>
            @endforelse
        </div>

        <!-- Pengalaman Kerja -->
        <div class="mb-4">
            <h6 class="fw-bold text-uppercase border-bottom pb-1">Pengalaman Kerja</h6>
            @forelse ($profile->experiences as $exp)
                <div class="small mb-3">
                    <div class="d-flex justify-content-between">
                        <div class="fw-bold">{{ $exp->position }} - {{ $exp->company_name }}</div>
                        <div class="text-muted">
                            {{ $exp->start_date?->format('M Y') }} - {{ $exp->is_current ? 'Sekarang' : ($exp->end_date?->format('M Y') ?? '-') }}
                        </div>
                    </div>
                    @if ($exp->description)
                        <div class="mt-1">{{ $exp->description }}</div>
                    @endif
                </div>
            @empty
                <p class="text-muted small mb-0">Belum ada data pengalaman kerja.</p>
            @endforelse
        </div>

        <!-- Kompetensi -->
        <div class="mb-4">
            <h6 class="fw-bold text-uppercase border-bottom pb-1">Kompetensi dan Keahlian</h6>
            @if ($profile->skills->isNotEmpty())
                <ul class="mb-0 small">
                    @foreach ($profile->skills as $skill)
                        <li>{{ $skill->skill_name }} ({{ $skill->proficiency_level }})</li>
                    @endforeach
                </ul>
            @else
                <p class="text-muted small mb-0">Belum ada data kompetensi.</p>
            @endif
        </div>

        <!-- Penghargaan -->
        <div>
            <h6 class="fw-bold text-uppercase border-bottom pb-1">Penghargaan dan Sertifikasi</h6>
            @forelse ($profile->awards as $award)
                <div class="small mb-2">
                    <div class="d-flex justify-content-between">
                        <div class="fw-bold">{{ $award->title }} - {{ $award->issuer }}</div>
                        <div class="text-muted">{{ $award->year }}</div>
                    </div>
                    @if ($award->description)
                        <div>{{ $award->description }}</div>
                    @endif
                </div>
            @empty
                <p class="text-muted small mb-0">Belum ada data penghargaan.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
