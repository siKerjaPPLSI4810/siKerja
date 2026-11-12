@extends('company.layouts.app')

@section('title', 'Ubah Profil Perusahaan - SIkERJA')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Kelola Profil Perusahaan</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('company.profile.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="company_name" class="form-label">Nama Perusahaan <span class="text-danger">*</span></label>
                        <input type="text"
                               id="company_name"
                               name="company_name"
                               value="{{ old('company_name', $profile->company_name) }}"
                               class="form-control @error('company_name') is-invalid @enderror"
                               required>
                        @error('company_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Nama Penanggung Jawab (PIC) <span class="text-danger">*</span></label>
                            <input type="text"
                                   id="name"
                                   name="name"
                                   value="{{ old('name', $user->name) }}"
                                   class="form-control @error('name') is-invalid @enderror"
                                   required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Resmi <span class="text-danger">*</span></label>
                            <input type="email"
                                   id="email"
                                   name="email"
                                   value="{{ old('email', $user->email) }}"
                                   class="form-control @error('email') is-invalid @enderror"
                                   required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Nomor Telepon <span class="text-danger">*</span></label>
                            <input type="text"
                                   id="phone"
                                   name="phone"
                                   value="{{ old('phone', $user->phone) }}"
                                   class="form-control @error('phone') is-invalid @enderror"
                                   required>
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="website" class="form-label">Website Perusahaan</label>
                            <input type="text"
                                   id="website"
                                   name="website"
                                   value="{{ old('website', $profile->website) }}"
                                   class="form-control @error('website') is-invalid @enderror"
                                   placeholder="https://www.perusahaan.co.id">
                            @error('website')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="npwp" class="form-label">Nomor NPWP</label>
                            <input type="text"
                                   id="npwp"
                                   name="npwp"
                                   value="{{ old('npwp', $profile->npwp) }}"
                                   class="form-control @error('npwp') is-invalid @enderror">
                            @error('npwp')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="company_document" class="form-label">Dokumen Legalitas Baru</label>
                            <input type="file"
                                   id="company_document"
                                   name="company_document"
                                   class="form-control @error('company_document') is-invalid @enderror"
                                   accept=".pdf,.jpg,.jpeg,.png">
                            <div class="form-text small">
                                @if ($profile->company_document_path)
                                    File saat ini: {{ basename($profile->company_document_path) }}
                                @else
                                    Format: PDF, JPG, PNG (Maks 5MB)
                                @endif
                            </div>
                            @error('company_document')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="province" class="form-label">Provinsi</label>
                            <select id="province" name="province" class="form-select @error('province') is-invalid @enderror">
                                <option value="">Pilih</option>
                                @foreach ($provinces as $code => $provName)
                                    <option value="{{ $provName }}" @selected(old('province', $profile->province) === $provName)>{{ $provName }}</option>
                                @endforeach
                            </select>
                            @error('province')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="city" class="form-label">Kota / Kabupaten</label>
                            <input type="text"
                                   id="city"
                                   name="city"
                                   value="{{ old('city', $profile->city) }}"
                                   class="form-control @error('city') is-invalid @enderror">
                            @error('city')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Alamat Kantor</label>
                        <textarea id="address"
                                  name="address"
                                  rows="2"
                                  class="form-control @error('address') is-invalid @enderror">{{ old('address', $profile->address) }}</textarea>
                        @error('address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <a href="{{ route('company.profile.index') }}" class="btn btn-outline-secondary">
                            Batal
                        </a>
                        <button type="submit" class="btn btn-primary">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
