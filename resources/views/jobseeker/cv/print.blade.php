<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak CV - {{ $user->name }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="bg-white py-4">
    <div class="container" style="max-width: 800px;">
        <div class="d-flex justify-content-between align-items-center mb-3 no-print">
            <a href="{{ route('jobseeker.cv.show') }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-secondary btn-sm">Cetak Halaman</button>
                <a href="{{ route('jobseeker.cv.download') }}" class="btn btn-primary btn-sm">Unduh PDF</a>
            </div>
        </div>

        <div class="border p-4">
            <div class="border-bottom pb-3 mb-3">
                <h3 class="mb-1">{{ $user->name }}</h3>
                <div class="small">
                    Email: {{ $user->email }}
                    @if ($user->phone) | Telepon: {{ $user->phone }} @endif
                    @if ($profile->city || $profile->province) | {{ trim(($profile->city ?? '').', '.($profile->province ?? ''), ', ') }} @endif
                </div>
                <div class="small">
                    NIK: {{ $profile->nik ?: '-' }} ({{ $profile->nik_verified ? 'Terverifikasi' : 'Belum Terverifikasi' }})
                </div>
            </div>

            <div class="mb-3">
                <h6 class="fw-bold text-uppercase border-bottom pb-1">Ringkasan Profil</h6>
                <p class="small mb-0">{{ $profile->bio ?: '-' }}</p>
            </div>

            <div class="mb-3">
                <h6 class="fw-bold text-uppercase border-bottom pb-1">Pendidikan</h6>
                @forelse ($profile->educations as $edu)
                    <div class="d-flex justify-content-between small mb-1">
                        <div><strong>{{ $edu->institution_name }}</strong> ({{ $edu->degree }} - {{ $edu->major }})</div>
                        <div>{{ $edu->start_year }} - {{ $edu->end_year ?: 'Sekarang' }}</div>
                    </div>
                @empty
                    <p class="small text-muted mb-0">-</p>
                @endforelse
            </div>

            <div class="mb-3">
                <h6 class="fw-bold text-uppercase border-bottom pb-1">Pengalaman Kerja</h6>
                @forelse ($profile->experiences as $exp)
                    <div class="small mb-2">
                        <div class="d-flex justify-content-between">
                            <strong>{{ $exp->position }} - {{ $exp->company_name }}</strong>
                            <span>{{ $exp->start_date?->format('M Y') }} - {{ $exp->is_current ? 'Sekarang' : ($exp->end_date?->format('M Y') ?? '-') }}</span>
                        </div>
                        @if ($exp->description)
                            <div>{{ $exp->description }}</div>
                        @endif
                    </div>
                @empty
                    <p class="small text-muted mb-0">-</p>
                @endforelse
            </div>

            <div class="mb-3">
                <h6 class="fw-bold text-uppercase border-bottom pb-1">Kompetensi</h6>
                @if ($profile->skills->isNotEmpty())
                    <p class="small mb-0">
                        {{ $profile->skills->map(fn ($s) => $s->skill_name.' ('.$s->proficiency_level.')')->implode(', ') }}
                    </p>
                @else
                    <p class="small text-muted mb-0">-</p>
                @endif
            </div>

            <div>
                <h6 class="fw-bold text-uppercase border-bottom pb-1">Penghargaan</h6>
                @forelse ($profile->awards as $award)
                    <div class="small mb-1">
                        <strong>{{ $award->title }}</strong> - {{ $award->issuer }} ({{ $award->year }})
                        @if ($award->description)
                            <div>{{ $award->description }}</div>
                        @endif
                    </div>
                @empty
                    <p class="small text-muted mb-0">-</p>
                @endforelse
            </div>
        </div>
    </div>
</body>
</html>
