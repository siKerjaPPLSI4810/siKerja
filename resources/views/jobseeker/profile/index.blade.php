@extends('jobseeker.layouts.app')

@section('title', 'Profil Pencari Kerja - SIkERJA')

@section('content')
<!-- Ringkasan Profil & Indikator Kelengkapan -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-2">
            <div>
                <h4 class="mb-1">{{ $user->name }}</h4>
                <div class="text-muted small">
                    {{ $user->email }}
                    @if ($user->phone) | {{ $user->phone }} @endif
                    @if ($profile->city || $profile->province) | {{ trim(($profile->city ?? '').', '.($profile->province ?? ''), ', ') }} @endif
                </div>
                <div class="mt-2">
                    @if ($profile->nik_verified && $profile->nik)
                        <span class="badge bg-success">Identitas Terverifikasi (NIK: {{ $profile->nik }})</span>
                    @else
                        <span class="badge bg-warning text-dark">NIK Belum Terverifikasi</span>
                    @endif
                </div>
            </div>

            <div class="d-flex gap-2">
                <a href="{{ route('jobseeker.cv.show') }}" class="btn btn-outline-primary btn-sm">Lihat CV Otomatis</a>
                <a href="{{ route('jobseeker.cv.download') }}" class="btn btn-primary btn-sm">Unduh CV (PDF)</a>
            </div>
        </div>

        <hr>

        <!-- Indikator Kelengkapan Profil (PBI 1.1.2) -->
        <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="fw-semibold small">
                Indikator Kelengkapan Profil ({{ $completeness['completed_count'] }} dari {{ $completeness['total_count'] }} bagian terisi)
            </span>
            <span class="fw-bold">{{ $completeness['percentage'] }}%</span>
        </div>

        <div class="progress mb-3" style="height: 18px;">
            <div class="progress-bar {{ $completeness['percentage'] === 100 ? 'bg-success' : 'bg-primary' }}"
                 role="progressbar"
                 style="width: {{ $completeness['percentage'] }}%;"
                 aria-valuenow="{{ $completeness['percentage'] }}"
                 aria-valuemin="0"
                 aria-valuemax="100">
                {{ $completeness['percentage'] }}%
            </div>
        </div>

        <div class="row g-2 small">
            @foreach ($completeness['sections'] as $sec)
                <div class="col-md">
                    <div class="border rounded p-2 {{ $sec['completed'] ? 'bg-success-subtle border-success-subtle' : 'bg-light' }}">
                        <div class="d-flex justify-content-between">
                            <span class="fw-semibold">{{ $sec['label'] }}</span>
                            <span>{{ $sec['completed'] ? 'Lengkap (20%)' : 'Belum (0%)' }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<!-- Navigasi Tab Bagian Profil -->
@php
    $tabs = [
        'data-diri' => 'Data Diri',
        'pendidikan' => 'Pendidikan ('.$profile->educations->count().')',
        'kompetensi' => 'Kompetensi ('.$profile->skills->count().')',
        'pengalaman' => 'Pengalaman Kerja ('.$profile->experiences->count().')',
        'penghargaan' => 'Penghargaan ('.$profile->awards->count().')',
        'verifikasi-nik' => 'Verifikasi NIK',
    ];
@endphp

<ul class="nav nav-tabs mb-3">
    @foreach ($tabs as $tabKey => $tabLabel)
        <li class="nav-item">
            <button type="button"
                    id="tab-btn-{{ $tabKey }}"
                    onclick="switchProfileTab('{{ $tabKey }}')"
                    class="nav-link profile-tab-btn {{ $activeTab === $tabKey ? 'active fw-semibold' : '' }}">
                {{ $tabLabel }}
            </button>
        </li>
    @endforeach
</ul>

<!-- 1. TAB DATA DIRI -->
<div id="tab-panel-data-diri" class="profile-tab-panel {{ $activeTab === 'data-diri' ? '' : 'd-none' }}">
    <div class="card">
        <div class="card-header bg-white">
            <h6 class="mb-0">Data Diri Pencari Kerja</h6>
        </div>
        <div class="card-body">
            <form action="{{ route('jobseeker.profile.personal.update') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" class="form-control" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Nomor Telepon</label>
                        <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="form-control" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">NIK</label>
                        <input type="text" value="{{ $profile->nik ?: '-' }}" class="form-control" disabled>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tanggal Lahir</label>
                        <input type="date" name="birth_date" value="{{ old('birth_date', $profile->birth_date?->format('Y-m-d')) }}" class="form-control" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Jenis Kelamin</label>
                        <select name="gender" class="form-select" required>
                            <option value="">Pilih</option>
                            <option value="Laki-laki" @selected(old('gender', $profile->gender) === 'Laki-laki')>Laki-laki</option>
                            <option value="Perempuan" @selected(old('gender', $profile->gender) === 'Perempuan')>Perempuan</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Ekspektasi Gaji (Rp)</label>
                        <input type="number" name="expected_salary" value="{{ old('expected_salary', $profile->expected_salary ? (int) $profile->expected_salary : '') }}" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Provinsi</label>
                        <input type="text" name="province" value="{{ old('province', $profile->province) }}" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kabupaten / Kota</label>
                        <input type="text" name="city" value="{{ old('city', $profile->city) }}" class="form-control" required>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Alamat Lengkap</label>
                        <textarea name="address" rows="2" class="form-control" required>{{ old('address', $profile->address) }}</textarea>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Ringkasan Diri (Bio)</label>
                        <textarea name="bio" rows="3" class="form-control" required>{{ old('bio', $profile->bio) }}</textarea>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Simpan Data Diri</button>
            </form>
        </div>
    </div>
</div>

<!-- 2. TAB PENDIDIKAN -->
<div id="tab-panel-pendidikan" class="profile-tab-panel {{ $activeTab === 'pendidikan' ? '' : 'd-none' }}">
    <div class="row g-4">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Tambah Pendidikan</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('jobseeker.profile.education.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Nama Institusi / Sekolah / Kampus</label>
                            <input type="text" name="institution_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jenjang</label>
                            <select name="degree" class="form-select" required>
                                <option value="">Pilih Jenjang</option>
                                <option value="SMA/SMK">SMA/SMK</option>
                                <option value="D3">D3</option>
                                <option value="D4/S1">D4/S1</option>
                                <option value="S2">S2</option>
                                <option value="S3">S3</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jurusan</label>
                            <input type="text" name="major" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Tahun Mulai</label>
                                <input type="number" name="start_year" class="form-control" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Tahun Lulus</label>
                                <input type="number" name="end_year" class="form-control">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Simpan Pendidikan</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Daftar Pendidikan</h6>
                </div>
                <div class="card-body">
                    @if ($profile->educations->isEmpty())
                        <p class="text-muted mb-0">Belum ada data pendidikan.</p>
                    @else
                        @foreach ($profile->educations as $edu)
                            <div class="border rounded p-3 mb-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold">{{ $edu->institution_name }}</div>
                                        <div class="small">{{ $edu->degree }} - {{ $edu->major }}</div>
                                        <div class="text-muted small">{{ $edu->start_year }} - {{ $edu->end_year ?: 'Sekarang' }}</div>
                                    </div>
                                    <div class="d-flex gap-1">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-secondary"
                                                onclick="document.getElementById('edit-edu-{{ $edu->id }}').classList.toggle('d-none')">
                                            Edit
                                        </button>
                                        <form action="{{ route('jobseeker.profile.education.destroy', $edu) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                        </form>
                                    </div>
                                </div>

                                <form id="edit-edu-{{ $edu->id }}"
                                      action="{{ route('jobseeker.profile.education.update', $edu) }}"
                                      method="POST"
                                      class="d-none mt-3 pt-3 border-top">
                                    @csrf
                                    @method('PUT')
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <input type="text" name="institution_name" value="{{ $edu->institution_name }}" class="form-control form-control-sm" required>
                                        </div>
                                        <div class="col-6">
                                            <input type="text" name="degree" value="{{ $edu->degree }}" class="form-control form-control-sm" required>
                                        </div>
                                        <div class="col-6">
                                            <input type="text" name="major" value="{{ $edu->major }}" class="form-control form-control-sm" required>
                                        </div>
                                        <div class="col-6">
                                            <input type="number" name="start_year" value="{{ $edu->start_year }}" class="form-control form-control-sm" required>
                                        </div>
                                        <div class="col-6">
                                            <input type="number" name="end_year" value="{{ $edu->end_year }}" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-sm btn-primary">Perbarui</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 3. TAB KOMPETENSI -->
<div id="tab-panel-kompetensi" class="profile-tab-panel {{ $activeTab === 'kompetensi' ? '' : 'd-none' }}">
    <div class="row g-4">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Tambah Kompetensi</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('jobseeker.profile.skill.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Nama Kompetensi / Keahlian</label>
                            <input type="text" name="skill_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tingkat Kemahiran</label>
                            <select name="proficiency_level" class="form-select" required>
                                <option value="Pemula">Pemula</option>
                                <option value="Menengah" selected>Menengah</option>
                                <option value="Mahir">Mahir</option>
                                <option value="Ahli">Ahli</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Simpan Kompetensi</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Daftar Kompetensi</h6>
                </div>
                <div class="card-body">
                    @if ($profile->skills->isEmpty())
                        <p class="text-muted mb-0">Belum ada data kompetensi.</p>
                    @else
                        @foreach ($profile->skills as $skill)
                            <div class="border rounded p-3 mb-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="fw-semibold">{{ $skill->skill_name }}</span>
                                        <span class="badge bg-secondary ms-1">{{ $skill->proficiency_level }}</span>
                                    </div>
                                    <div class="d-flex gap-1">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-secondary"
                                                onclick="document.getElementById('edit-skill-{{ $skill->id }}').classList.toggle('d-none')">
                                            Edit
                                        </button>
                                        <form action="{{ route('jobseeker.profile.skill.destroy', $skill) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                        </form>
                                    </div>
                                </div>

                                <form id="edit-skill-{{ $skill->id }}"
                                      action="{{ route('jobseeker.profile.skill.update', $skill) }}"
                                      method="POST"
                                      class="d-none mt-2 pt-2 border-top">
                                    @csrf
                                    @method('PUT')
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <input type="text" name="skill_name" value="{{ $skill->skill_name }}" class="form-control form-control-sm" required>
                                        </div>
                                        <div class="col-4">
                                            <select name="proficiency_level" class="form-select form-select-sm">
                                                @foreach (['Pemula', 'Menengah', 'Mahir', 'Ahli'] as $level)
                                                    <option value="{{ $level }}" @selected($skill->proficiency_level === $level)>{{ $level }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-2">
                                            <button type="submit" class="btn btn-sm btn-primary w-100">Simpan</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 4. TAB PENGALAMAN KERJA -->
<div id="tab-panel-pengalaman" class="profile-tab-panel {{ $activeTab === 'pengalaman' ? '' : 'd-none' }}">
    <div class="row g-4">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Tambah Pengalaman Kerja</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('jobseeker.profile.experience.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Nama Perusahaan</label>
                            <input type="text" name="company_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Posisi / Jabatan</label>
                            <input type="text" name="position" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Tanggal Mulai</label>
                                <input type="date" name="start_date" class="form-control" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Tanggal Selesai</label>
                                <input type="date" name="end_date" class="form-control">
                            </div>
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" name="is_current" value="1" id="is_current_new" class="form-check-input">
                            <label for="is_current_new" class="form-check-label">Masih bekerja di sini</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi Pekerjaan</label>
                            <textarea name="description" rows="3" class="form-control"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Simpan Pengalaman</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Daftar Pengalaman Kerja</h6>
                </div>
                <div class="card-body">
                    @if ($profile->experiences->isEmpty())
                        <p class="text-muted mb-0">Belum ada data pengalaman kerja.</p>
                    @else
                        @foreach ($profile->experiences as $exp)
                            <div class="border rounded p-3 mb-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold">{{ $exp->position }} - {{ $exp->company_name }}</div>
                                        <div class="text-muted small">
                                            {{ $exp->start_date?->format('M Y') }} - {{ $exp->is_current ? 'Sekarang' : ($exp->end_date?->format('M Y') ?? '-') }}
                                        </div>
                                        @if ($exp->description)
                                            <div class="small mt-1">{{ $exp->description }}</div>
                                        @endif
                                    </div>
                                    <div class="d-flex gap-1">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-secondary"
                                                onclick="document.getElementById('edit-exp-{{ $exp->id }}').classList.toggle('d-none')">
                                            Edit
                                        </button>
                                        <form action="{{ route('jobseeker.profile.experience.destroy', $exp) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                        </form>
                                    </div>
                                </div>

                                <form id="edit-exp-{{ $exp->id }}"
                                      action="{{ route('jobseeker.profile.experience.update', $exp) }}"
                                      method="POST"
                                      class="d-none mt-3 pt-3 border-top">
                                    @csrf
                                    @method('PUT')
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <input type="text" name="company_name" value="{{ $exp->company_name }}" class="form-control form-control-sm" required>
                                        </div>
                                        <div class="col-6">
                                            <input type="text" name="position" value="{{ $exp->position }}" class="form-control form-control-sm" required>
                                        </div>
                                        <div class="col-6">
                                            <input type="date" name="start_date" value="{{ $exp->start_date?->format('Y-m-d') }}" class="form-control form-control-sm" required>
                                        </div>
                                        <div class="col-6">
                                            <input type="date" name="end_date" value="{{ $exp->end_date?->format('Y-m-d') }}" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-12">
                                            <div class="form-check">
                                                <input type="checkbox" name="is_current" value="1" class="form-check-input" @checked($exp->is_current)>
                                                <label class="form-check-label small">Masih bekerja di sini</label>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <textarea name="description" rows="2" class="form-control form-control-sm">{{ $exp->description }}</textarea>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-sm btn-primary">Perbarui</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 5. TAB PENGHARGAAN -->
<div id="tab-panel-penghargaan" class="profile-tab-panel {{ $activeTab === 'penghargaan' ? '' : 'd-none' }}">
    <div class="row g-4">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Tambah Penghargaan</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('jobseeker.profile.award.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Nama Penghargaan / Sertifikasi</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Institusi Pemberi</label>
                            <input type="text" name="issuer" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tahun</label>
                            <input type="number" name="year" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Keterangan</label>
                            <textarea name="description" rows="2" class="form-control"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Simpan Penghargaan</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Daftar Penghargaan</h6>
                </div>
                <div class="card-body">
                    @if ($profile->awards->isEmpty())
                        <p class="text-muted mb-0">Belum ada data penghargaan.</p>
                    @else
                        @foreach ($profile->awards as $award)
                            <div class="border rounded p-3 mb-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold">{{ $award->title }} ({{ $award->year }})</div>
                                        <div class="small text-muted">{{ $award->issuer }}</div>
                                        @if ($award->description)
                                            <div class="small mt-1">{{ $award->description }}</div>
                                        @endif
                                    </div>
                                    <div class="d-flex gap-1">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-secondary"
                                                onclick="document.getElementById('edit-award-{{ $award->id }}').classList.toggle('d-none')">
                                            Edit
                                        </button>
                                        <form action="{{ route('jobseeker.profile.award.destroy', $award) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                        </form>
                                    </div>
                                </div>

                                <form id="edit-award-{{ $award->id }}"
                                      action="{{ route('jobseeker.profile.award.update', $award) }}"
                                      method="POST"
                                      class="d-none mt-3 pt-3 border-top">
                                    @csrf
                                    @method('PUT')
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <input type="text" name="title" value="{{ $award->title }}" class="form-control form-control-sm" required>
                                        </div>
                                        <div class="col-8">
                                            <input type="text" name="issuer" value="{{ $award->issuer }}" class="form-control form-control-sm" required>
                                        </div>
                                        <div class="col-4">
                                            <input type="number" name="year" value="{{ $award->year }}" class="form-control form-control-sm" required>
                                        </div>
                                        <div class="col-12">
                                            <textarea name="description" rows="2" class="form-control form-control-sm">{{ $award->description }}</textarea>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-sm btn-primary">Perbarui</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 6. TAB VERIFIKASI NIK -->
<div id="tab-panel-verifikasi-nik" class="profile-tab-panel {{ $activeTab === 'verifikasi-nik' ? '' : 'd-none' }}">
    <div class="row g-4">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Verifikasi Identitas NIK</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('jobseeker.profile.nik.verify') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">NIK (16 Digit)</label>
                            <input type="text"
                                   name="nik"
                                   maxlength="16"
                                   value="{{ old('nik', $profile->nik) }}"
                                   class="form-control"
                                   required>
                        </div>
                        <button type="submit" class="btn btn-primary">Periksa dan Simpan NIK</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Hasil Pemeriksaan NIK</h6>
                    @if ($nikInspection)
                        <span class="badge {{ $nikInspection['valid'] ? 'bg-success' : 'bg-danger' }}">
                            {{ $nikInspection['status_label'] }}
                        </span>
                    @endif
                </div>
                <div class="card-body">
                    @if ($nikInspection)
                        <div class="alert {{ $nikInspection['valid'] ? 'alert-success' : 'alert-danger' }} py-2 small">
                            {{ $nikInspection['message'] }}
                        </div>

                        <table class="table table-bordered table-sm small mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Pemeriksaan</th>
                                    <th>Keterangan</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($nikInspection['checks'] as $check)
                                    <tr>
                                        <td class="fw-semibold">{{ $check['label'] }}</td>
                                        <td>{{ $check['detail'] }}</td>
                                        <td>
                                            @if ($check['passed'])
                                                <span class="badge bg-success">Valid</span>
                                            @else
                                                <span class="badge bg-danger">Ditolak</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-muted mb-0">Belum ada hasil pemeriksaan NIK.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function switchProfileTab(tabName) {
    document.querySelectorAll('.profile-tab-panel').forEach(function (panel) {
        panel.classList.add('d-none');
    });
    document.querySelectorAll('.profile-tab-btn').forEach(function (btn) {
        btn.classList.remove('active', 'fw-semibold');
    });

    const targetPanel = document.getElementById('tab-panel-' + tabName);
    if (targetPanel) {
        targetPanel.classList.remove('d-none');
    }

    const targetBtn = document.getElementById('tab-btn-' + tabName);
    if (targetBtn) {
        targetBtn.classList.add('active', 'fw-semibold');
    }
}
</script>
@endpush
