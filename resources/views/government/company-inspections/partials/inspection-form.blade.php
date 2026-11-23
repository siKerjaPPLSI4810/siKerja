@php
    $formEnabled = ($canRecordInspection ?? false)
        && Route::has('government.company-inspections.store')
        && filled($dataVersion ?? null);
    $options = [
        'oss_check_result' => ['label' => 'Hasil pemeriksaan OSS', 'values' => ['matched' => 'Sesuai', 'mismatched' => 'Tidak sesuai', 'unconfirmed' => 'Belum dapat dipastikan']],
        'administrative_result' => ['label' => 'Kelengkapan administrasi', 'values' => ['complete' => 'Lengkap', 'incomplete' => 'Tidak lengkap', 'unconfirmed' => 'Belum dapat dipastikan']],
        'decision' => ['label' => 'Keputusan', 'values' => ['verified' => 'Terverifikasi', 'rejected' => 'Ditolak', 'pending' => 'Perlu pemeriksaan lanjutan']],
    ];
@endphp
<section class="card" aria-labelledby="inspection-form-heading">
    <div class="card-header bg-white"><h2 id="inspection-form-heading" class="h6 mb-0">Pemeriksaan manual OSS RBA</h2></div>
    <div class="card-body">
        <p>Bandingkan identitas dan dokumen perusahaan dengan sumber resmi, lalu catat temuan pemeriksaan.</p>
        @if (filled($ossPortalUrl ?? null))
            <a href="{{ $ossPortalUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm mb-3">Buka OSS — tab baru</a>
        @else
            <p class="small text-muted">Tautan sumber OSS belum tersedia. Gunakan sumber resmi yang ditetapkan pengelola.</p>
        @endif
        @if (! $formEnabled)
            <div class="alert alert-secondary" role="status">
                @if (! empty($conflictMessage))
                    Pencatatan dinonaktifkan sampai data dimuat ulang.
                @elseif (in_array($inspectionRecord['decision'] ?? null, ['verified', 'rejected'], true))
                    Keputusan sudah final. Pencatatan ulang tidak tersedia pada halaman ini.
                @else
                    Pencatatan belum tersedia. Status atau izin pencatatan perlu dipastikan.
                @endif
            </div>
        @endif
        <p class="small text-muted" id="inspection-form-help">Semua isian bertanda * wajib diisi. Pemeriksaan ini dicatat secara manual oleh petugas.</p>
        <form method="POST" id="inspection-form" aria-describedby="inspection-form-help"
            @if ($formEnabled) action="{{ route('government.company-inspections.store', ['companyProfile' => $company->id]) }}" @endif>
            @csrf
            <fieldset @disabled(! $formEnabled)>
                <legend class="visually-hidden">Catat hasil pemeriksaan manual</legend>
                <input type="hidden" name="data_version" value="{{ $dataVersion ?? '' }}">
                @foreach (['oss_check_result', 'administrative_result'] as $field)
                    <div class="mb-3">
                        <label for="{{ $field }}" class="form-label">{{ $options[$field]['label'] }} *</label>
                        <select id="{{ $field }}" name="{{ $field }}" required class="form-select @error($field) is-invalid @enderror" @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>
                            <option value="">Pilih hasil</option>
                            @foreach ($options[$field]['values'] as $value => $label)
                                <option value="{{ $value }}" @selected(old($field) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error($field) <div id="{{ $field }}-error" class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                @endforeach
                <div class="mb-3">
                    <label for="checked_on" class="form-label">Tanggal pemeriksaan *</label>
                    <input id="checked_on" name="checked_on" type="date" required value="{{ old('checked_on') }}" class="form-control @error('checked_on') is-invalid @enderror" aria-describedby="checked-on-help @error('checked_on') checked-on-error @enderror" @error('checked_on') aria-invalid="true" @enderror>
                    <div id="checked-on-help" class="form-text">Isi tanggal pengecekan dilakukan, bukan tanggal pengisian otomatis.</div>
                    @error('checked_on') <div id="checked-on-error" class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label for="decision" class="form-label">Keputusan *</label>
                    <select id="decision" name="decision" required class="form-select @error('decision') is-invalid @enderror" aria-describedby="decision-help @error('decision') decision-error @enderror" @error('decision') aria-invalid="true" @enderror>
                        <option value="">Pilih keputusan</option>
                        @foreach ($options['decision']['values'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('decision') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div id="decision-help" class="form-text">Terverifikasi hanya jika hasil OSS sesuai dan administrasi lengkap. Jika belum dapat dipastikan, pilih perlu pemeriksaan lanjutan.</div>
                    @error('decision') <div id="decision-error" class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label for="notes" class="form-label">Catatan pemeriksaan *</label>
                    <textarea id="notes" name="notes" rows="5" maxlength="5000" required class="form-control @error('notes') is-invalid @enderror" aria-describedby="notes-help @error('notes') notes-error @enderror" @error('notes') aria-invalid="true" @enderror>{{ old('notes') }}</textarea>
                    <div id="notes-help" class="form-text">Jelaskan identitas yang dibandingkan, temuan, dan alasan keputusan. Maksimal 5.000 karakter.</div>
                    @error('notes') <div id="notes-error" class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <button class="btn btn-primary w-100" type="submit">Simpan hasil pemeriksaan</button>
            </fieldset>
            <p id="save-status" class="small text-muted mt-2 mb-0" role="status" aria-live="polite"></p>
        </form>
    </div>
</section>
@push('scripts')
<script>
    (() => {
        const form = document.getElementById('inspection-form');
        const button = form.querySelector('button[type="submit"]');
        form.addEventListener('submit', (event) => {
            if (!form.hasAttribute('action') || form.querySelector('fieldset').disabled) {
                event.preventDefault();
                return;
            }
            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }
            form.dataset.submitting = 'true';
            button.disabled = true;
            button.textContent = 'Menyimpan…';
            document.getElementById('save-status').textContent = 'Menunggu hasil penyimpanan dari server.';
        });
        window.addEventListener('pageshow', () => {
            delete form.dataset.submitting;
            button.disabled = form.querySelector('fieldset').disabled;
            button.textContent = 'Simpan hasil pemeriksaan';
            document.getElementById('save-status').textContent = '';
        });
    })();
</script>
@endpush
