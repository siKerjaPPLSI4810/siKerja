{{-- untuk PBI 2.2.1 (Membuat dan Mengajukan Lowongan Kerja)--}}
@extends('company.layouts.app')

@section('title', 'Buat & Ajukan Lowongan Kerja - SIkERJA')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">Buat & Ajukan Lowongan Kerja</h5>
                    <div class="text-muted small">Isi formulir lowongan kerja untuk disimpan sebagai draf atau diajukan ke Pemerintah (Kemnaker).</div>
                </div>
                <a href="{{ route('company.jobs.index') }}" class="btn btn-sm btn-outline-secondary">
                    Kembali ke Daftar
                </a>
            </div>
            <div class="card-body">
                <form action="{{ route('company.jobs.store') }}" method="POST">
                    @csrf


                    <div class="mb-3">
                        <label for="title" class="form-label">Judul Posisi Lowongan <span class="text-danger">*</span></label>
                        <input type="text"
                            id="title"
                            name="title"
                            value="{{ old('title') }}"
                            class="form-control @error('title') is-invalid @enderror"
                            placeholder="Contoh: Senior Backend Engineer"
                            required>
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>


                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="category" class="form-label">Bidang / Kategori <span class="text-danger">*</span></label>
                            @php $selectedCategory = old('category'); @endphp
                            <select id="category" name="category" class="form-select @error('category') is-invalid @enderror" required>
                                <option value="" disabled {{ empty($selectedCategory) ? 'selected' : '' }}>-- Pilih Bidang / Kategori --</option>
                                @foreach ($categories as $value => $label)
                                    <option value="{{ $value }}" @selected($selectedCategory === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('category')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="job_type" class="form-label">Tipe Pekerjaan <span class="text-danger">*</span></label>
                            @php $selectedJobType = old('job_type'); @endphp
                            <select id="job_type" name="job_type" class="form-select @error('job_type') is-invalid @enderror" required>
                                <option value="" disabled {{ empty($selectedJobType) ? 'selected' : '' }}>-- Pilih Tipe Pekerjaan --</option>
                                @foreach ($jobTypes as $value => $label)
                                    <option value="{{ $value }}" @selected($selectedJobType === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('job_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="location" class="form-label">Lokasi Penempatan <span class="text-danger">*</span></label>
                            <input type="text"
                                id="location"
                                name="location"
                                value="{{ old('location') }}"
                                class="form-control @error('location') is-invalid @enderror"
                                placeholder="Contoh: Jakarta Selatan"
                                required>
                            @error('location')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="education_requirement" class="form-label">Pendidikan Minimum <span class="text-danger">*</span></label>
                            @php $selectedEdu = old('education_requirement'); @endphp
                            <select id="education_requirement" name="education_requirement" class="form-select @error('education_requirement') is-invalid @enderror" required>
                                <option value="" disabled {{ empty($selectedEdu) ? 'selected' : '' }}>-- Pilih Pendidikan Minimum --</option>
                                @foreach ($educations as $value => $label)
                                    <option value="{{ $value }}" @selected($selectedEdu === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('education_requirement')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="quota" class="form-label">Kuota Penerimaan (Orang) <span class="text-danger">*</span></label>
                            <input type="number"
                                id="quota"
                                name="quota"
                                min="1"
                                value="{{ old('quota', 1) }}"
                                class="form-control @error('quota') is-invalid @enderror"
                                required>
                            @error('quota')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="salary_display" class="form-label">Estimasi Gaji Bulanan (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="text"
                                    id="salary_display"
                                    inputmode="numeric"
                                    value="{{ old('salary_around') ? number_format((float) old('salary_around'), 0, ',', '.') : '' }}"
                                    class="form-control @error('salary_around') is-invalid @enderror"
                                    placeholder="Contoh: 8.500.000"
                                    required>
                                <input type="hidden" id="salary_around" name="salary_around" value="{{ old('salary_around') }}">
                                @error('salary_around')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-text small">Harus memenuhi standar upah minimum wilayah (UMK) yang berlaku (min. Rp 1.000.000).</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="deadline" class="form-label">Batas Akhir Penerimaan (Deadline) <span class="text-danger">*</span></label>
                            <input type="date"
                                id="deadline"
                                name="deadline"
                                min="{{ date('Y-m-d', strtotime('+1 day')) }}"
                                value="{{ old('deadline') }}"
                                class="form-control @error('deadline') is-invalid @enderror"
                                required>
                            @error('deadline')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Deskripsi Tugas & Tanggung Jawab <span class="text-danger">*</span></label>
                        <textarea id="description"
                                name="description"
                                rows="4"
                                class="form-control @error('description') is-invalid @enderror"
                                placeholder="Jelaskan ruang lingkup pekerjaan, fungsi divisi, dan tanggung jawab harian (minimal 20 karakter)..."
                                required>{{ old('description') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="requirements" class="form-label">Kualifikasi & Persyaratan Khusus <span class="text-danger">*</span></label>
                        <textarea id="requirements"
                                name="requirements"
                                rows="4"
                                class="form-control @error('requirements') is-invalid @enderror"
                                placeholder="Tuliskan daftar persyaratan keahlian teknis, pengalaman minimal, dan kualifikasi pendukung (minimal 20 karakter)..."
                                required>{{ old('requirements') }}</textarea>
                        @error('requirements')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" name="is_draft" value="1" class="btn btn-outline-secondary">
                            Simpan Sebagai Draf
                        </button>
                        <button type="submit" class="btn btn-primary">
                            Ajukan ke Pengawas Kemnaker
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const displayInput = document.getElementById('salary_display');
        const hiddenInput = document.getElementById('salary_around');

        if (displayInput && hiddenInput) {
            displayInput.addEventListener('input', function () {
                const rawDigits = this.value.replace(/\D/g, '');
                hiddenInput.value = rawDigits;
                this.value = rawDigits ? rawDigits.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';
            });
        }
    });
</script>
@endpush