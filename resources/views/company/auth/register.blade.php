@extends('company.layouts.app')

@section('title', 'Pendaftaran Perusahaan - SIkERJA')

@section('content')
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Pendaftaran dan Verifikasi Identitas Perusahaan</h5>
            </div>
            <div class="card-body">
                <form id="companyRegisterForm" action="{{ route('company.register.submit') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-3">
                        <label for="npwp" class="form-label">NPWP Perusahaan (15/16 Digit)</label>
                        <div class="input-group">
                            <input type="text"
                                   id="npwp"
                                   name="npwp"
                                   value="{{ old('npwp', $inspection['extracted']['formatted_npwp'] ?? ($inspection['extracted']['raw_npwp'] ?? '')) }}"
                                   maxlength="25"
                                   class="form-control @error('npwp') is-invalid @enderror"
                                   placeholder="Contoh: 01.234.567.8-901.000 atau 012345678901000"
                                   required>
                            <button type="button" id="btnInspectCompany" class="btn btn-outline-primary">
                                Periksa NPWP
                            </button>
                        </div>
                        <div class="form-text" id="npwpHelpText">NPWP wajib terdiri dari 15 atau 16 digit angka dan belum pernah terdaftar.</div>
                        @error('npwp')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="company_name" class="form-label">Nama Perusahaan</label>
                        <input type="text"
                               id="company_name"
                               name="company_name"
                               value="{{ old('company_name', $inspection['extracted']['company_name'] ?? '') }}"
                               class="form-control @error('company_name') is-invalid @enderror"
                               placeholder="Contoh: PT Maju Bersama Teknologi"
                               required>
                        @error('company_name')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Penanggung Jawab (PIC / HR)</label>
                        <input type="text"
                               id="name"
                               name="name"
                               value="{{ old('name') }}"
                               class="form-control @error('name') is-invalid @enderror"
                               required>
                        @error('name')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Perusahaan</label>
                            <input type="email"
                                   id="email"
                                   name="email"
                                   value="{{ old('email') }}"
                                   class="form-control @error('email') is-invalid @enderror"
                                   required>
                            @error('email')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Nomor Telepon</label>
                            <input type="text"
                                   id="phone"
                                   name="phone"
                                   value="{{ old('phone') }}"
                                   class="form-control @error('phone') is-invalid @enderror"
                                   required>
                            @error('phone')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="province" class="form-label">Provinsi</label>
                            <select id="province" name="province" class="form-select @error('province') is-invalid @enderror">
                                <option value="">Pilih</option>
                                @foreach ($provinces as $code => $provName)
                                    <option value="{{ $provName }}" @selected(old('province') === $provName)>{{ $provName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="city" class="form-label">Kota / Kabupaten</label>
                            <input type="text"
                                   id="city"
                                   name="city"
                                   value="{{ old('city') }}"
                                   class="form-control @error('city') is-invalid @enderror">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="website" class="form-label">Website Perusahaan</label>
                        <input type="text"
                               id="website"
                               name="website"
                               value="{{ old('website') }}"
                               class="form-control @error('website') is-invalid @enderror"
                               placeholder="https://www.perusahaan.co.id">
                        @error('website')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Alamat Kantor</label>
                        <textarea id="address"
                                  name="address"
                                  rows="2"
                                  class="form-control @error('address') is-invalid @enderror">{{ old('address') }}</textarea>
                    </div>

                    <div class="mb-3">
                        <label for="company_document" class="form-label">Dokumen Legalitas (NIB / SIUP / TDP / NPWP)</label>
                        <input type="file"
                               id="company_document"
                               name="company_document"
                               class="form-control @error('company_document') is-invalid @enderror"
                               accept=".pdf,.jpg,.jpeg,.png">
                        <div class="form-text">Format: PDF, JPG, PNG (Maksimal 5MB).</div>
                        @error('company_document')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="scenario_override" class="form-label small text-muted">Pengujian Skenario Pemeriksaan</label>
                        <select id="scenario_override" name="scenario_override" class="form-select form-select-sm">
                            <option value="">Default (Sesuai Validasi & Peninjauan)</option>
                            <option value="valid" @selected(old('scenario_override') === 'valid')>Skenario 1: Valid / Terverifikasi</option>
                            <option value="invalid" @selected(old('scenario_override') === 'invalid')>Skenario 2: Tidak Valid / Ditolak</option>
                            <option value="unconfirmed" @selected(old('scenario_override') === 'unconfirmed')>Skenario 3: Belum Dapat Dipastikan</option>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Kata Sandi</label>
                            <input type="password"
                                   id="password"
                                   name="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   required>
                            @error('password')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
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

                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <span class="small">Sudah punya akun? <a href="{{ route('company.login') }}">Masuk</a></span>
                        <button type="submit" class="btn btn-primary">Daftar dan Verifikasi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Hasil Pemeriksaan Identitas Perusahaan</h6>
                <span id="inspectionBadge"
                      class="badge {{ isset($inspection) ? ($inspection['badge_class'] ?? 'bg-secondary') : 'bg-secondary' }}">
                    {{ $inspection['status_label'] ?? 'BELUM DIPERIKSA' }}
                </span>
            </div>
            <div class="card-body">
                <div id="inspectionAlert"
                     class="alert {{ isset($inspection) ? ($inspection['alert_class'] ?? 'alert-secondary') : 'alert-secondary' }} py-2 small">
                    <span id="inspectionMessageText">
                        {{ $inspection['message'] ?? 'Masukkan NPWP dan Nama Perusahaan lalu klik tombol Periksa NPWP untuk melihat hasil validasi.' }}
                    </span>
                </div>

                @php
                    $defaultChecks = $inspection['checks'] ?? [
                        'company_name' => [
                            'label' => 'Nama Resmi Perusahaan',
                            'passed' => null,
                            'detail' => 'Menunggu input nama perusahaan',
                        ],
                        'npwp_format' => [
                            'label' => 'Panjang & Format NPWP (15 atau 16 Digit)',
                            'passed' => null,
                            'detail' => 'Menunggu input 15 atau 16 digit angka',
                        ],
                        'npwp_unique' => [
                            'label' => 'Pemeriksaan NPWP Tidak Ganda (Keunikan)',
                            'passed' => null,
                            'detail' => 'Memastikan NPWP belum terdaftar pada akun lain',
                        ],
                        'legal_document' => [
                            'label' => 'Lampiran Dokumen Legalitas (NIB/SIUP/NPWP)',
                            'passed' => null,
                            'detail' => 'Format PDF, JPG, PNG maks 5MB',
                        ],
                        'authority_verification' => [
                            'label' => 'Pemeriksaan Keabsahan Identitas & Verifikator',
                            'passed' => null,
                            'detail' => 'Menunggu penelaahan resmi verifikator',
                        ],
                    ];
                @endphp

                <table class="table table-bordered table-sm small mb-3">
                    <thead class="table-light">
                        <tr>
                            <th>Komponen Pemeriksaan</th>
                            <th class="text-nowrap" style="width: 25%;">Status</th>
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
                    <h6 class="small fw-bold">Data Hasil Pemeriksaan</h6>
                    <table class="table table-sm table-bordered small mb-0">
                        <tr>
                            <th class="bg-light w-50">Perusahaan</th>
                            <td id="extCompanyName">{{ $inspection['extracted']['company_name'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th class="bg-light">Format NPWP</th>
                            <td id="extNpwp">{{ $inspection['extracted']['formatted_npwp'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th class="bg-light">Dokumen</th>
                            <td id="extDocName">{{ $inspection['extracted']['document_name'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th class="bg-light">Waktu Periksa</th>
                            <td id="extCheckedAt">{{ $inspection['extracted']['checked_at'] ?? '-' }}</td>
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
    const btnInspect = document.getElementById('btnInspectCompany');
    const inputNpwp = document.getElementById('npwp');
    const inputCompanyName = document.getElementById('company_name');
    const inputDoc = document.getElementById('company_document');
    const selectScenario = document.getElementById('scenario_override');

    const badge = document.getElementById('inspectionBadge');
    const alertBox = document.getElementById('inspectionAlert');
    const alertMessage = document.getElementById('inspectionMessageText');
    const checksList = document.getElementById('inspectionChecksList');
    const extractedBox = document.getElementById('inspectionExtractedBox');
    const extCompanyName = document.getElementById('extCompanyName');
    const extNpwp = document.getElementById('extNpwp');
    const extDocName = document.getElementById('extDocName');
    const extCheckedAt = document.getElementById('extCheckedAt');

    async function runInspection() {
        const companyName = inputCompanyName.value.trim();
        const npwp = inputNpwp.value.trim();
        const scenario = selectScenario.value;

        btnInspect.disabled = true;
        btnInspect.innerText = 'Memeriksa...';

        const formData = new FormData();
        formData.append('company_name', companyName);
        formData.append('npwp', npwp);
        formData.append('scenario_override', scenario);

        if (inputDoc.files && inputDoc.files[0]) {
            formData.append('company_document', inputDoc.files[0]);
        }

        try {
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const response = await fetch("{{ route('company.identity.check') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                },
                body: formData
            });

            const data = await response.json();
            updateInspectionUI(data);
        } catch (error) {
            console.error('Error saat memeriksa identitas:', error);
            alertMessage.innerText = 'Gagal melakukan pemeriksaan ke server.';
        } finally {
            btnInspect.disabled = false;
            btnInspect.innerText = 'Periksa NPWP';
        }
    }

    function updateInspectionUI(data) {
        if (!data) return;

        badge.className = 'badge ' + (data.badge_class || 'bg-secondary');
        badge.innerText = data.status_label || 'DIPERIKSA';

        alertBox.className = 'alert ' + (data.alert_class || 'alert-secondary') + ' py-2 small';
        alertMessage.innerText = data.message || '';

        if (data.checks) {
            let html = '';
            for (const [key, check] of Object.entries(data.checks)) {
                let badgeClass = 'bg-secondary';
                let badgeText = 'Menunggu';
                if (check.passed === true) {
                    badgeClass = 'bg-success';
                    badgeText = 'Valid';
                } else if (check.passed === false) {
                    badgeClass = 'bg-danger';
                    badgeText = 'Ditolak';
                }

                html += `<tr>
                    <td>
                        <div class="fw-semibold">${check.label}</div>
                        <div class="text-muted">${check.detail}</div>
                    </td>
                    <td class="align-middle text-nowrap">
                        <span class="badge ${badgeClass}">${badgeText}</span>
                    </td>
                </tr>`;
            }
            checksList.innerHTML = html;
        }

        if (data.extracted) {
            extractedBox.classList.remove('d-none');
            extCompanyName.innerText = data.extracted.company_name || '-';
            extNpwp.innerText = data.extracted.formatted_npwp || data.extracted.clean_npwp || '-';
            extDocName.innerText = data.extracted.document_name || (inputDoc.files && inputDoc.files[0] ? inputDoc.files[0].name : '-');
            extCheckedAt.innerText = data.extracted.checked_at || '-';
        }
    }

    if (btnInspect) {
        btnInspect.addEventListener('click', runInspection);
    }

    if (selectScenario) {
        selectScenario.addEventListener('change', function () {
            if (inputNpwp.value.trim() !== '') {
                runInspection();
            }
        });
    }
});
</script>
@endpush
