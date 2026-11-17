@switch($status)
    @case('pending')
        <span class="badge bg-warning text-dark text-wrap">{{ ($hasInspection ?? false) ? 'Perlu pemeriksaan lanjutan' : 'Menunggu pemeriksaan' }}</span>
        @break
    @case('verified')
        <span class="badge bg-success text-wrap">Terverifikasi</span>
        @break
    @case('rejected')
        <span class="badge bg-danger text-wrap">Ditolak</span>
        @break
    @default
        <span class="badge bg-secondary text-wrap">Status belum tersedia</span>
@endswitch
