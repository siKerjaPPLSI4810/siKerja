@php
    $statusLabels = ['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected'];
    $badgeClass = ['pending' => 'bg-warning text-dark', 'verified' => 'bg-success', 'rejected' => 'bg-danger'][$status ?? ''] ?? 'bg-secondary';
@endphp
<span class="badge {{ $badgeClass }} text-wrap">{{ $statusLabels[$status ?? ''] ?? 'Status tidak dikenal' }}</span>
@if (($status ?? null) === 'pending')
    <span class="d-block small text-muted mt-1">{{ ($provenance ?? '') === 'official' ? 'Perlu pemeriksaan lanjutan' : (($provenance ?? '') === 'uninspected' ? 'Belum diperiksa' : 'Sumber status perlu dipastikan') }}</span>
@endif
