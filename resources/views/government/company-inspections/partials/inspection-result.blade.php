<section class="card mb-4" aria-labelledby="inspection-result-heading">
    <div class="card-header bg-white"><h2 id="inspection-result-heading" class="h6 mb-0">Hasil pemeriksaan terkini</h2></div>
    <div class="card-body">
        @if (! empty($inspectionRecord))
            <dl class="row mb-0">
                <dt class="col-sm-5">Hasil OSS</dt><dd class="col-sm-7">{{ ['matched' => 'Sesuai', 'mismatched' => 'Tidak sesuai', 'unconfirmed' => 'Belum dapat dipastikan'][$inspectionRecord['oss_check_result']] ?? 'Belum tersedia' }}</dd>
                <dt class="col-sm-5">Administrasi</dt><dd class="col-sm-7">{{ ['complete' => 'Lengkap', 'incomplete' => 'Tidak lengkap', 'unconfirmed' => 'Belum dapat dipastikan'][$inspectionRecord['administrative_result']] ?? 'Belum tersedia' }}</dd>
                <dt class="col-sm-5">Tanggal pemeriksaan</dt><dd class="col-sm-7">{{ $inspectionRecord['checked_on'] ?? 'Belum tersedia' }}</dd>
                <dt class="col-sm-5">Keputusan</dt><dd class="col-sm-7">@include('government.company-inspections.partials.status', ['status' => $inspectionRecord['decision'], 'hasInspection' => true])</dd>
                <dt class="col-sm-5">Petugas</dt><dd class="col-sm-7">{{ $inspectionRecord['reviewer_name'] ?? 'Belum tersedia' }}</dd>
                <dt class="col-sm-5">Waktu penyimpanan</dt><dd class="col-sm-7">{{ $inspectionRecord['recorded_at'] ?? 'Belum tersedia' }}</dd>
                <dt class="col-sm-5">Catatan</dt><dd class="col-sm-7 text-break" style="white-space: pre-wrap">{{ $inspectionRecord['notes'] ?? 'Belum tersedia' }}</dd>
            </dl>
        @else
            <p class="text-muted">Belum ada catatan pemeriksaan manual Admin Pemerintah.</p>
            @if (filled($company->verification_notes))
                <div class="border rounded p-3 bg-light">
                    <h3 class="h6">Catatan status perusahaan yang sudah ada</h3>
                    <p class="small text-muted">Catatan ini belum membuktikan adanya pemeriksaan OSS manual.</p>
                    <p class="mb-0 text-break" style="white-space: pre-wrap">{{ $company->verification_notes }}</p>
                </div>
            @endif
        @endif
    </div>
</section>
