{{-- =========================================================================
     VIEW: resources/views/company/jobs/edit.blade.php
     Penanggung Jawab: Rifqi Ahmad Fauzy (NIM 102022430023)
     Cakupan: PBI 2.2.3 (Mengubah dan Menutup Lowongan Kerja)
     ========================================================================= --}}
@extends('company.layouts.app')

@section('title', 'Ubah Lowongan Kerja - SIkERJA')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-9">
        @if ($job->status === 'published')
            <div class="alert alert-warning mb-3 small">
                <strong>Perhatian Aturan Peninjauan Ulang:</strong> Lowongan ini sedang berstatus <strong>Disetujui / Aktif</strong>. Menyimpan perubahan pada informasi lowongan akan mengubah statusnya kembali menjadi <strong>Menunggu Tinjauan</strong> untuk diverifikasi ulang oleh Pemerintah.
            </div>
        @endif

        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">Ubah Informasi Lowongan Kerja</h5>
                    <div class="text-muted small">Posisi: <strong>{{ $job->title }}</strong></div>
                </div>
                <a href="{{ route('company.jobs.index') }}" class="btn btn-sm btn-outline-secondary">
                    Kembali ke Daftar
                </a>
            </div>
            <div class="card-body">
                <form action="{{ route('company.jobs.update', $job->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="title" class="form-label">Judul Posisi Lowongan <span class="text-danger">*</span></label>
                        <input type="text"
                            id="title"
                            name="title"
                            value="{{ old('title', $job->title) }}"
                            class="form-control @error('title') is-invalid @enderror"
                            required>
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="category" class="form-label">Bidang / Kategori <span class="text-danger">*</span></label>
                            @php $selectedCategory = old('category', $job->category); @endphp
                            <select id="category" name="category" class="form-select @error('category') is-invalid @enderror" required>
                                <option value="" disabled>-- Pilih Bidang / Kategori --</option>
                                @foreach ($categories as $value => $label)
                                    <option value="{{ $value }}" @selected($selectedCategory === $value)>{{ $label }}</option>
                                @endforeach
                                @if (! array_key_exists($selectedCategory, $categories) && ! empty($selectedCategory))
                                    <option value="{{ $selectedCategory }}" selected>{{ $selectedCategory }}</option>
                                @endif
                            </select>
                            @error('category')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="job_type" class="form-label">Tipe Pekerjaan <span class="text-danger">*</span></label>
                            @php $selectedJobType = old('job_type', $job->job_type); @endphp
                            <select id="job_type" name="job_type" class="form-select @error('job_type') is-invalid @enderror" required>
                                <option value="" disabled>-- Pilih Tipe Pekerjaan --</option>
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
                                value="{{ old('location', $job->location) }}"
                                class="form-control @error('location') is-invalid @enderror"
                                required>
                            @error('location')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="education_requirement" class="form-label">Pendidikan Minimum <span class="text-danger">*</span></label>
                            @php $selectedEdu = old('education_requirement', $job->education_requirement); @endphp
                            <select id="education_requirement" name="education_requirement" class="form-select @error('education_requirement') is-invalid @enderror" required>
                                <option value="" disabled>-- Pilih Pendidikan Minimum --</option>
                                @foreach ($educations as $value => $label)
                                    <option value="{{ $value }}" @selected($selectedEdu === $value)>{{ $label }}</option>
                                @endforeach
                                @if (! array_key_exists($selectedEdu, $educations) && ! empty($selectedEdu))
                                    <option value="{{ $selectedEdu }}" selected>{{ $selectedEdu }}</option>
                                @endif
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
                                value="{{ old('quota', $job->quota) }}"
                                class="form-control @error('quota') is-invalid @enderror"
                                required>
                            @error('quota')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    
                    @php $rawSalary = old('salary_around', (int) $job->salary_around); @endphp
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="salary_display" class="form-label">Estimasi Gaji Bulanan (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="text"
                                    id="salary_display"
                                    inputmode="numeric"
                                    value="{{ $rawSalary ? number_format((float) $rawSalary, 0, ',', '.') : '' }}"
                                    class="form-control @error('salary_around') is-invalid @enderror"
                                    required>
                                <input type="hidden" id="salary_around" name="salary_around" value="{{ $rawSalary }}">
                                @error('salary_around')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="deadline" class="form-label">Batas Akhir Penerimaan (Deadline) <span class="text-danger">*</span></label>
                            <input type="date"
                                id="deadline"
                                name="deadline"
                                min="{{ date('Y-m-d', strtotime('+1 day')) }}"
                                value="{{ old('deadline', $job->deadline ? $job->deadline->format('Y-m-d') : '') }}"
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
                                required>{{ old('description', $job->description) }}</textarea>
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
                                required>{{ old('requirements', $job->requirements) }}</textarea>
                        @error('requirements')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="{{ route('company.jobs.index') }}" class="btn btn-outline-secondary">
                            Batal
                        </a>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                Simpan Perubahan
                            </button>
                            @if (in_array($job->status, ['draft', 'rejected'], true))
                                <button type="submit" name="resubmit" value="1" class="btn btn-success">
                                    Simpan & Ajukan ke Pemerintah
                                </button>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
