<?php

namespace App\Services;

use App\Models\CompanyProfile;
use Illuminate\Http\UploadedFile;

class CompanyVerificationService
{
    /**
     * Format NPWP Indonesia (menghilangkan titik dan strip untuk validasi).
     */
    public function cleanNpwp(?string $npwp): string
    {
        if ($npwp === null) {
            return '';
        }

        return preg_replace('/[^0-9]/', '', $npwp) ?? '';
    }

    /**
     * Format tampilan standar NPWP (XX.XXX.XXX.X-XXX.XXX).
     */
    public function formatNpwp(?string $npwp): string
    {
        $clean = $this->cleanNpwp($npwp);

        if (strlen($clean) === 15) {
            return sprintf(
                '%s.%s.%s.%s-%s.%s',
                substr($clean, 0, 2),
                substr($clean, 2, 3),
                substr($clean, 5, 3),
                substr($clean, 8, 1),
                substr($clean, 9, 3),
                substr($clean, 12, 3)
            );
        }

        if (strlen($clean) === 16) {
            return sprintf(
                '%s.%s.%s.%s-%s.%s',
                substr($clean, 0, 2),
                substr($clean, 2, 3),
                substr($clean, 5, 3),
                substr($clean, 8, 2),
                substr($clean, 10, 3),
                substr($clean, 13, 3)
            );
        }

        return $npwp ?? '';
    }

    /**
     * Memeriksa dan memvalidasi identitas perusahaan (PBI 2.1.1).
     *
     * Menghasilkan hasil pemeriksaan untuk 3 skenario:
     * 1. Valid (verified): data lengkap, NPWP valid 15/16 digit, dokumen terlampir & terverifikasi.
     * 2. Tidak Valid (rejected): format NPWP salah, NPWP ganda, atau dokumen/data tidak sesuai.
     * 3. Belum Dapat Dipastikan (pending): data dasar lengkap & NPWP sesuai, namun dokumen dalam antrean telaah manual.
     *
     * @param  UploadedFile|string|null  $document
     * @param  string|null  $requestedScenario  'valid'|'invalid'|'unconfirmed'|null
     * @return array{
     *     valid: bool,
     *     scenario: string,
     *     status: string,
     *     status_label: string,
     *     badge_class: string,
     *     alert_class: string,
     *     message: string,
     *     errors: array<int, string>,
     *     checks: array<string, array{label: string, passed: bool|null, detail: string}>,
     *     extracted: array<string, mixed>
     * }
     */
    public function inspect(
        string $companyName,
        ?string $rawNpwp,
        mixed $document = null,
        ?int $ignoreProfileId = null,
        ?string $requestedScenario = null
    ): array {
        $errors = [];
        $companyName = trim($companyName);
        $cleanNpwp = $this->cleanNpwp($rawNpwp);
        $rawNpwpTrimmed = trim((string) $rawNpwp);

        // 1. Validasi Nama Perusahaan
        $nameValid = $companyName !== '';
        if (! $nameValid) {
            $errors[] = 'Nama perusahaan wajib diisi.';
        }

        // 2. Validasi Format NPWP (15 atau 16 Digit)
        $npwpNotEmpty = $rawNpwpTrimmed !== '';
        $npwpIsNumeric = $cleanNpwp !== '' && ctype_digit($cleanNpwp);
        $npwpLength = strlen($cleanNpwp);
        $npwpLengthValid = in_array($npwpLength, [15, 16], true);
        $npwpNonRepeating = $cleanNpwp !== '' && ! preg_match('/^(\d)\1{14,15}$/', $cleanNpwp);

        if (! $npwpNotEmpty) {
            $errors[] = 'NPWP perusahaan wajib diisi untuk memvalidasi identitas legal perusahaan.';
        } else {
            if (! $npwpIsNumeric || ! $npwpLengthValid) {
                $errors[] = 'NPWP tidak valid: wajib terdiri dari 15 atau 16 digit angka (saat ini '.$npwpLength.' digit).';
            } elseif (! $npwpNonRepeating || $cleanNpwp === str_repeat('0', $npwpLength)) {
                $errors[] = 'NPWP tidak valid: nomor NPWP terindikasi nomor dummy atau nomor tidak resmi.';
            }
        }

        $npwpFormatPassed = $npwpNotEmpty && $npwpIsNumeric && $npwpLengthValid && $npwpNonRepeating && ($cleanNpwp !== str_repeat('0', $npwpLength));

        // 3. Validasi Keunikan NPWP
        $npwpUniquePassed = true;
        if ($npwpFormatPassed) {
            $query = CompanyProfile::query()->where(function ($q) use ($cleanNpwp, $rawNpwpTrimmed) {
                $q->where('npwp', $cleanNpwp)
                    ->orWhere('npwp', $rawNpwpTrimmed)
                    ->orWhere('npwp', $this->formatNpwp($cleanNpwp));
            });

            if ($ignoreProfileId !== null) {
                $query->where('id', '!=', $ignoreProfileId);
            }

            if ($query->exists()) {
                $npwpUniquePassed = false;
                $errors[] = 'NPWP tidak valid karena sudah terdaftar (ganda). Satu NPWP hanya dapat digunakan oleh satu akun perusahaan.';
            }
        } else {
            $npwpUniquePassed = false;
        }

        // 4. Validasi Dokumen Legalitas (NIB / SIUP / Akta / NPWP)
        $documentPassed = false;
        $documentDetail = 'Dokumen legalitas perusahaan belum dilampirkan.';
        $documentName = null;

        if ($document instanceof UploadedFile) {
            $documentName = $document->getClientOriginalName();
            $ext = strtolower($document->getClientOriginalExtension());
            $size = $document->getSize();

            if (! in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
                $errors[] = 'Format dokumen legalitas tidak didukung. Harap unggah berkas berekstensi PDF, JPG, atau PNG.';
                $documentDetail = 'Format ekstensi .'.$ext.' tidak didukung';
            } elseif ($size > 5 * 1024 * 1024) {
                $errors[] = 'Ukuran dokumen legalitas melebihi batas maksimum 5MB.';
                $documentDetail = 'Ukuran file terlalu besar (> 5MB)';
            } else {
                $documentPassed = true;
                $documentDetail = 'Berkas terunggah: '.$documentName.' ('.round($size / 1024, 1).' KB)';
            }
        } elseif (is_string($document) && $document !== '') {
            $documentPassed = true;
            $documentDetail = 'Dokumen legalitas tersimpan: '.basename($document);
            $documentName = basename($document);
        }

        // 5. Penentuan Skenario Pemeriksaan: valid, tidak valid, belum dapat dipastikan
        $scenario = 'invalid';
        $status = 'rejected';
        $statusLabel = 'DITOLAK (TIDAK VALID)';
        $badgeClass = 'bg-danger';
        $alertClass = 'alert-danger';
        $message = $errors[0] ?? 'Pemeriksaan gagal: data identitas perusahaan tidak memenuhi kriteria validasi.';

        // Jika terdapat error validasi mendasar (format NPWP salah, duplikat, dll) -> TIDAK VALID
        if (! empty($errors) || $requestedScenario === 'invalid') {
            $scenario = 'invalid';
            $status = 'rejected';
            $statusLabel = 'DITOLAK (TIDAK VALID)';
            $badgeClass = 'bg-danger';
            $alertClass = 'alert-danger';
            $message = ! empty($errors) ? $errors[0] : 'Identitas perusahaan dinyatakan tidak valid setelah pemeriksaan data.';
        } elseif ($requestedScenario === 'unconfirmed') {
            // Skenario 3: Belum Dapat Dipastikan (diminta secara eksplisit / simulasi)
            $scenario = 'unconfirmed';
            $status = 'pending';
            $statusLabel = 'BELUM DAPAT DIPASTIKAN (MENUNGGU PENINJAUAN)';
            $badgeClass = 'bg-warning text-dark';
            $alertClass = 'alert-warning';
            $message = 'Pemeriksaan tertunda: Data NPWP sesuai format, namun keabsahan berkas legalitas belum dapat dipastikan secara otomatis dan memerlukan verifikasi manual oleh petugas.';
        } elseif ($requestedScenario === 'valid') {
            // Skenario 1: Valid (diminta secara eksplisit / simulasi atau verifikasi otomatis penuh)
            $scenario = 'valid';
            $status = 'verified';
            $statusLabel = 'TERVERIFIKASI (VALID)';
            $badgeClass = 'bg-success';
            $alertClass = 'alert-success';
            $message = 'Pemeriksaan berhasil: Identitas legal perusahaan dinyatakan valid dan telah terverifikasi resmi.';
        } else {
            // Kondisi default pendaftaran ketika data NPWP dan format dokumen benar:
            // Jika dokumen dilampirkan dan NPWP format valid serta unik:
            // Dapat dikategorikan sebagai "Belum Dapat Dipastikan" (menunggu peninjauan manual petugas)
            // ATAU "Valid" jika dokumen lengkap dan lolos uji instansi.
            // Sesuai kriteria: pendaftaran awal dengan berkas masuk status "pending" (belum dapat dipastikan)
            // agar petugas dinas/sistem dapat memverifikasi.
            $scenario = 'unconfirmed';
            $status = 'pending';
            $statusLabel = 'BELUM DAPAT DIPASTIKAN (MENUNGGU PENINJAUAN)';
            $badgeClass = 'bg-warning text-dark';
            $alertClass = 'alert-warning';
            $message = 'Identitas perusahaan berhasil dikirim. Status saat ini belum dapat dipastikan secara otomatis dan sedang dalam antrean verifikasi manual petugas.';
        }

        $checks = [
            'company_name' => [
                'label' => 'Nama Resmi Perusahaan',
                'passed' => $nameValid,
                'detail' => $nameValid ? 'Nama perusahaan terisi: '.$companyName : 'Nama perusahaan belum diisi',
            ],
            'npwp_format' => [
                'label' => 'Panjang & Format NPWP (15 atau 16 Digit)',
                'passed' => $npwpFormatPassed,
                'detail' => $npwpFormatPassed
                    ? 'Format sesuai standar Direktorat Jenderal Pajak ('.$this->formatNpwp($cleanNpwp).')'
                    : 'Tidak sesuai format 15/16 digit angka numerik',
            ],
            'npwp_unique' => [
                'label' => 'Pemeriksaan NPWP Tidak Ganda (Keunikan)',
                'passed' => $npwpUniquePassed,
                'detail' => ! $npwpFormatPassed
                    ? 'Menunggu nomor NPWP yang valid'
                    : ($npwpUniquePassed ? 'NPWP belum pernah digunakan oleh akun lain (Unik)' : 'NPWP sudah terdaftar pada perusahaan lain (Ganda)'),
            ],
            'legal_document' => [
                'label' => 'Lampiran Dokumen Legalitas (NIB/SIUP/NPWP)',
                'passed' => $documentPassed,
                'detail' => $documentDetail,
            ],
            'authority_verification' => [
                'label' => 'Pemeriksaan Keabsahan Identitas & Verifikator',
                'passed' => $status === 'verified' ? true : ($status === 'rejected' ? false : null),
                'detail' => match ($status) {
                    'verified' => 'Identitas terbukti sah dan lolos verifikasi.',
                    'rejected' => 'Identitas ditolak karena data tidak memenuhi kriteria validasi.',
                    default => 'Menunggu verifikasi lanjutan / telaah manual oleh verifikator dinas.',
                },
            ],
        ];

        return [
            'valid' => $status === 'verified',
            'scenario' => $scenario,
            'status' => $status,
            'status_label' => $statusLabel,
            'badge_class' => $badgeClass,
            'alert_class' => $alertClass,
            'message' => $message,
            'errors' => $errors,
            'checks' => $checks,
            'extracted' => [
                'company_name' => $companyName,
                'raw_npwp' => $rawNpwpTrimmed,
                'clean_npwp' => $cleanNpwp,
                'formatted_npwp' => $this->formatNpwp($cleanNpwp),
                'document_name' => $documentName,
                'checked_at' => now()->format('d M Y H:i:s'),
            ],
        ];
    }
}
