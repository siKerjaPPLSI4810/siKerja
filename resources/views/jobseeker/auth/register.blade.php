@extends('jobseeker.layouts.app')

@section('title', 'Pendaftaran Pencari Kerja - SIkERJA')

@section('content')
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Pendaftaran dan Verifikasi Identitas Pencari Kerja</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('jobseeker.register.submit') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label for="nik" class="form-label">NIK (16 Digit)</label>
                        <div class="input-group">
                            <input type="text"
                                   id="nik"
                                   name="nik"
                                   value="{{ old('nik', $inspection['extracted']['nik'] ?? '') }}"
                                   maxlength="16"
                                   class="form-control @error('nik') is-invalid @enderror"
                                   placeholder="Masukkan 16 digit NIK"
                                   required>
                            <button type="button" id="btnInspectNik" class="btn btn-outline-primary">
                                Periksa NIK
                            </button>
                        </div>
                        <div class="form-text" id="nikDigitCounter">Panjang NIK wajib 16 digit angka dan belum pernah terdaftar.</div>
                        @error('nik')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Lengkap</label>
                        <input type="text"
                               id="name"
                               name="name"
                               value="{{ old('name') }}"
                               class="form-control"
                               required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email"
                                   id="email"
                                   name="email"
                                   value="{{ old('email') }}"
                                   class="form-control"
                                   required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Nomor Telepon</label>
                            <input type="text"
                                   id="phone"
                                   name="phone"
                                   value="{{ old('phone') }}"
                                   class="form-control"
                                   required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="birth_date" class="form-label">Tanggal Lahir</label>
                            <input type="date"
                                   id="birth_date"
                                   name="birth_date"
                                   value="{{ old('birth_date', $inspection['extracted']['birth_date'] ?? '') }}"
                                   class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="gender" class="form-label">Jenis Kelamin</label>
                            <select id="gender" name="gender" class="form-select">
                                <option value="">Pilih</option>
                                <option value="Laki-laki" @selected(old('gender', $inspection['extracted']['gender'] ?? '') === 'Laki-laki')>Laki-laki</option>
                                <option value="Perempuan" @selected(old('gender', $inspection['extracted']['gender'] ?? '') === 'Perempuan')>Perempuan</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="province" class="form-label">Provinsi</label>
                            <input type="text"
                                   id="province"
                                   name="province"
                                   value="{{ old('province', $inspection['extracted']['province_name'] ?? '') }}"
                                   class="form-control">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Kata Sandi</label>
                            <input type="password"
                                   id="password"
                                   name="password"
                                   class="form-control"
                                   required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="password_confirmation" class="form-label">Konfirmasi Kata Sandi</label>
                            <input type="password"
                                   id="password_confirmation"
                                   name="password_confirmation"
                                   class="form-control"
                                   required>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="small">Sudah punya akun? <a href="{{ route('jobseeker.login') }}">Masuk</a></span>
                        <button type="submit" class="btn btn-primary">Daftar dan Verifikasi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Hasil Pemeriksaan Identitas NIK</h6>
                <span id="inspectionBadge"
                      class="badge {{ isset($inspection) ? ($inspection['valid'] ? 'bg-success' : 'bg-danger') : 'bg-secondary' }}">
                    {{ $inspection['status_label'] ?? 'BELUM DIPERIKSA' }}
                </span>
            </div>
            <div class="card-body">
                <div id="inspectionAlert"
                     class="alert {{ isset($inspection) ? ($inspection['valid'] ? 'alert-success' : 'alert-danger') : 'alert-secondary' }} py-2 small">
                    {{ $inspection['message'] ?? 'Masukkan NIK 16 digit lalu klik tombol Periksa NIK untuk melihat hasil validasi.' }}
                </div>

                @php
                    $defaultChecks = $inspection['checks'] ?? [
                        'format_16_digit' => [
                            'label' => 'Panjang & Format 16 Digit Angka',
                            'passed' => null,
                            'detail' => 'Menunggu input NIK 16 digit',
                        ],
                        'struktur_nik' => [
                            'label' => 'Pemeriksaan Struktur & Kode Wilayah NIK',
                            'passed' => null,
                            'detail' => 'Menunggu pemeriksaan kode provinsi & tanggal lahir',
                        ],
                        'keunikan_nik' => [
                            'label' => 'Pemeriksaan NIK Tidak Ganda',
                            'passed' => null,
                            'detail' => 'Memastikan NIK belum terdaftar pada akun lain',
                        ],
                    ];
                @endphp

                <table class="table table-bordered table-sm small mb-3">
                    <thead class="table-light">
                        <tr>
                            <th>Komponen Pemeriksaan</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="inspectionChecksList">
                        @foreach ($defaultChecks as $check)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $check['label'] }}</div>
                                    <div class="text-muted">{{ $check['detail'] }}</div>
                                </td>
                                <td class="align-middle text-nowrap">
                                    @if ($check['passed'] === true)
                                        <span class="badge bg-success">Valid</span>
                                    @elseif ($check['passed'] === false)
                                        <span class="badge bg-danger">Ditolak</span>
                                    @else
                                        <span class="badge bg-secondary">Menunggu</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div id="inspectionExtractedBox" class="{{ isset($inspection) ? '' : 'd-none' }}">
                    <h6 class="small fw-bold">Data Hasil Pembacaan NIK</h6>
                    <table class="table table-sm table-bordered small mb-0">
                        <tr>
                            <th class="bg-light w-50">NIK</th>
                            <td id="extNik">{{ $inspection['extracted']['nik'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th class="bg-light">Provinsi</th>
                            <td id="extProvince">{{ $inspection['extracted']['province_name'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th class="bg-light">Tanggal Lahir</th>
                            <td id="extBirthDate">{{ $inspection['extracted']['birth_date'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th class="bg-light">Jenis Kelamin</th>
                            <td id="extGender">{{ $inspection['extracted']['gender'] ?? '-' }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const nikInput = document.getElementById('nik');
    const btnInspect = document.getElementById('btnInspectNik');
    const badge = document.getElementById('inspectionBadge');
    const alertBox = document.getElementById('inspectionAlert');
    const checksList = document.getElementById('inspectionChecksList');
    const extractedBox = document.getElementById('inspectionExtractedBox');

    btnInspect.addEventListener('click', async function () {
        const nik = nikInput.value.trim();
        btnInspect.disabled = true;
        btnInspect.textContent = 'Memeriksa...';

        try {
            const response = await fetch('{{ route("jobseeker.nik.check") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ nik })
            });

            const data = await response.json();

            badge.textContent = data.status_label;
            badge.className = data.valid ? 'badge bg-success' : 'badge bg-danger';

            alertBox.textContent = data.message;
            alertBox.className = data.valid
                ? 'alert alert-success py-2 small'
                : 'alert alert-danger py-2 small';

            let rows = '';
            Object.values(data.checks || {}).forEach(function (check) {
                const statusBadge = check.passed
                    ? '<span class="badge bg-success">Valid</span>'
                    : '<span class="badge bg-danger">Ditolak</span>';

                rows += `
                    <tr>
                        <td>
                            <div class="fw-semibold">${check.label}</div>
                            <div class="text-muted">${check.detail}</div>
                        </td>
                        <td class="align-middle text-nowrap">${statusBadge}</td>
                    </tr>
                `;
            });
            checksList.innerHTML = rows;

            if (data.extracted) {
                extractedBox.classList.remove('d-none');
                document.getElementById('extNik').textContent = data.extracted.nik || '-';
                document.getElementById('extProvince').textContent = data.extracted.province_name || '-';
                document.getElementById('extBirthDate').textContent = data.extracted.birth_date || '-';
                document.getElementById('extGender').textContent = data.extracted.gender || '-';

                if (data.valid) {
                    const birthInput = document.getElementById('birth_date');
                    const genderInput = document.getElementById('gender');
                    const provinceInput = document.getElementById('province');

                    if (birthInput && !birthInput.value && data.extracted.birth_date) {
                        birthInput.value = data.extracted.birth_date;
                    }
                    if (genderInput && !genderInput.value && data.extracted.gender) {
                        genderInput.value = data.extracted.gender;
                    }
                    if (provinceInput && !provinceInput.value && data.extracted.province_name) {
                        provinceInput.value = data.extracted.province_name;
                    }
                }
            }
        } catch (err) {
            console.error(err);
        } finally {
            btnInspect.disabled = false;
            btnInspect.textContent = 'Periksa NIK';
        }
    });
});
</script>
@endpush
