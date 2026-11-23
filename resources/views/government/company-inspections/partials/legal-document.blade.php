<section class="card mb-4" aria-labelledby="legal-document-heading">
    <div class="card-header bg-white"><h2 id="legal-document-heading" class="h6 mb-0">Dokumen legalitas</h2></div>
    <div class="card-body">
        @if (($document['available'] ?? false) && filled($document['download_url'] ?? null))
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <span class="text-break">{{ $document['display_name'] ?? 'Dokumen perusahaan' }}</span>
                <a class="btn btn-sm btn-outline-primary" href="{{ $document['download_url'] }}">Unduh dokumen</a>
            </div>
            <p class="small text-muted mt-2 mb-0">Ketersediaan berkas belum menyatakan keabsahan dokumen.</p>
        @elseif ($company->company_document_path)
            <p class="text-muted mb-0">Dokumen belum tersedia melalui penyimpanan private. Berkas lama pada penyimpanan public memerlukan penanganan pengelola sebelum dapat diakses dengan aman.</p>
        @else
            <p class="text-muted mb-0">Dokumen legalitas belum tersedia.</p>
        @endif
    </div>
</section>
