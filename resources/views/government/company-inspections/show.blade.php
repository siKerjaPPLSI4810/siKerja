@extends('government.layouts.app')
@section('title', 'Detail Pemeriksaan Perusahaan - SIkERJA')

@section('content')
    @if (! empty($conflictMessage))
        <div class="alert alert-warning" role="alert" id="conflict-message" tabindex="-1">
            <p>{{ $conflictMessage }}</p>
            <a class="btn btn-outline-dark btn-sm" href="{{ route('government.company-inspections.show', $company) }}">Muat ulang data</a>
        </div>
    @endif
    @if (Route::has('government.company-inspections.index'))
        <a class="btn btn-outline-secondary btn-sm mb-3" href="{{ route('government.company-inspections.index', ['q' => request('q'), 'page' => request('page')]) }}">← Kembali ke antrean</a>
    @endif
    @if (! isset($company))
        <div class="alert alert-warning" role="alert">Data perusahaan belum tersedia.</div>
    @else
        <div class="card mb-4"><div class="card-body">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <h1 class="h4 mb-0 text-break">{{ $company->company_name }}</h1>
                <div>@include('government.company-inspections.partials.status', ['status' => $company->verification_status, 'hasInspection' => ! empty($inspectionRecord)])</div>
            </div>
            <p class="small text-muted mt-2 mb-0">Status perusahaan dan catatan pemeriksaan manual ditampilkan terpisah. Status saja bukan bukti pengecekan OSS RBA.</p>
        </div></div>
        <div class="row g-4">
            <div class="col-lg-6">
                @include('government.company-inspections.partials.company-information')
                @include('government.company-inspections.partials.legal-document')
                @include('government.company-inspections.partials.inspection-result')
            </div>
            <div class="col-lg-6">
                @include('government.company-inspections.partials.inspection-form')
            </div>
        </div>
    @endif
@endsection
