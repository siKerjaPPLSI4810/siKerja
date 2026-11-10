@extends('jobseeker.layouts.app')

@section('title', 'Riwayat Lamaran')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="h3 mb-0">Riwayat Lamaran</h1>
        <a class="btn btn-outline-primary" href="{{ route('jobs.index') }}">Cari lowongan</a>
    </div>

    <div class="vstack gap-3">
        @forelse ($applications as $application)
            @php
                $status = match ($application->status) {
                    'terkirim' => 'Terkirim',
                    'review' => 'Dalam peninjauan',
                    'wawancara' => 'Wawancara',
                    'offering' => 'Penawaran',
                    'diterima' => 'Diterima',
                    'ditolak' => 'Ditolak',
                    default => $application->status,
                };
            @endphp

            <article class="card card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <div>
                        <h2 class="h5 mb-1">
                            @if ($application->jobPosting->status === 'published')
                                <a href="{{ route('jobs.show', $application->jobPosting) }}">{{ $application->jobPosting->title }}</a>
                            @else
                                {{ $application->jobPosting->title }}
                            @endif
                        </h2>
                        <p class="text-muted mb-2">{{ $application->jobPosting->company->company_name }} · {{ $application->jobPosting->location }}</p>
                    </div>
                    <span class="badge text-bg-primary">{{ $status }}</span>
                </div>

                <p class="small mb-0">Dikirim pada {{ $application->applied_at->format('d/m/Y H:i') }}</p>
                @if ($application->offered_salary !== null)
                    <p class="small mt-2 mb-0">Penawaran gaji Rp {{ number_format((float) $application->offered_salary, 0, ',', '.') }}</p>
                @endif
            </article>
        @empty
            <p class="text-muted">Anda belum mengirim lamaran.</p>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $applications->links('pagination::bootstrap-5') }}
    </div>
@endsection
