<?php

namespace App\Services;

use App\Models\JobseekerProfile;

class NikVerificationService
{
    /**
     * Daftar kode provinsi resmi Indonesia (2 digit pertama NIK).
     *
     * @var array<string, string>
     */
    public const PROVINCE_CODES = [
        '11' => 'Aceh',
        '12' => 'Sumatera Utara',
        '13' => 'Sumatera Barat',
        '14' => 'Riau',
        '15' => 'Jambi',
        '16' => 'Sumatera Selatan',
        '17' => 'Bengkulu',
        '18' => 'Lampung',
        '19' => 'Kepulauan Bangka Belitung',
        '21' => 'Kepulauan Riau',
        '31' => 'DKI Jakarta',
        '32' => 'Jawa Barat',
        '33' => 'Jawa Tengah',
        '34' => 'DI Yogyakarta',
        '35' => 'Jawa Timur',
        '36' => 'Banten',
        '51' => 'Bali',
        '52' => 'Nusa Tenggara Barat',
        '53' => 'Nusa Tenggara Timur',
        '61' => 'Kalimantan Barat',
        '62' => 'Kalimantan Tengah',
        '63' => 'Kalimantan Selatan',
        '64' => 'Kalimantan Timur',
        '65' => 'Kalimantan Utara',
        '71' => 'Sulawesi Utara',
        '72' => 'Sulawesi Tengah',
        '73' => 'Sulawesi Selatan',
        '74' => 'Sulawesi Tenggara',
        '75' => 'Gorontalo',
        '76' => 'Sulawesi Barat',
        '81' => 'Maluku',
        '82' => 'Maluku Utara',
        '91' => 'Papua',
        '92' => 'Papua Barat',
        '93' => 'Papua Selatan',
        '94' => 'Papua Tengah',
        '95' => 'Papua Pegunungan',
        '96' => 'Papua Barat Daya',
    ];

    /**
     * Memeriksa dan memvalidasi NIK pencari kerja (PBI 1.1.1).
     *
     * @return array{
     *     valid: bool,
     *     status_label: string,
     *     message: string,
     *     errors: array<int, string>,
     *     checks: array<string, array{label: string, passed: bool, detail: string}>,
     *     extracted: array{
     *         nik: string,
     *         province_code: string,
     *         province_name: string,
     *         city_code: string,
     *         district_code: string,
     *         birth_date: ?string,
     *         gender: ?string,
     *         serial_number: string,
     *         checked_at: string
     *     }
     * }
     */
    public function inspect(?string $rawNik, ?int $ignoreProfileId = null): array
    {
        $nik = trim((string) $rawNik);
        $errors = [];

        $isNotEmpty = $nik !== '';
        $isNumeric = $isNotEmpty && ctype_digit($nik);
        $is16Digits = $isNotEmpty && strlen($nik) === 16;

        if (! $isNotEmpty) {
            $errors[] = 'NIK wajib diisi untuk memverifikasi identitas pencari kerja.';
        } else {
            if (! $isNumeric) {
                $errors[] = 'NIK tidak valid: hanya boleh berisi angka (0-9) tanpa spasi atau karakter khusus.';
            }
            if (! $is16Digits) {
                $errors[] = 'NIK tidak valid: wajib terdiri dari tepat 16 digit angka (saat ini '.strlen($nik).' digit).';
            }
        }

        $provinceCode = $is16Digits ? substr($nik, 0, 2) : substr($nik, 0, 2);
        $cityCode = strlen($nik) >= 4 ? substr($nik, 2, 2) : '--';
        $districtCode = strlen($nik) >= 6 ? substr($nik, 4, 2) : '--';
        $serialNumber = strlen($nik) >= 16 ? substr($nik, 12, 4) : '----';

        $structureValid = false;
        $provinceName = self::PROVINCE_CODES[$provinceCode] ?? 'Wilayah Nasional (Kode '.$provinceCode.')';

        if ($isNumeric && $is16Digits) {
            $allSameDigit = preg_match('/^(\d)\1{15}$/', $nik) === 1;
            $invalidRegionOrSerial = $provinceCode === '00' || $cityCode === '00' || $districtCode === '00' || $serialNumber === '0000';

            if ($allSameDigit || $invalidRegionOrSerial) {
                $errors[] = 'NIK tidak valid: kode wilayah atau nomor urut pada NIK tidak sesuai format identitas kependudukan.';
            } else {
                $structureValid = true;
            }
        }

        // Pemeriksaan keunikan NIK (Tidak Ganda)
        $isUnique = true;
        if ($isNumeric && $is16Digits) {
            $query = JobseekerProfile::query()->where('nik', $nik);
            if ($ignoreProfileId !== null) {
                $query->where('id', '!=', $ignoreProfileId);
            }
            $isUnique = ! $query->exists();

            if (! $isUnique) {
                $errors[] = 'NIK tidak valid karena sudah terdaftar (ganda). Satu NIK hanya dapat digunakan untuk satu akun pencari kerja.';
            }
        }

        // Ekstraksi informasi tanggal lahir & jenis kelamin dari digit ke-7 s/d 12 (DDMMYY)
        $extractedBirthDate = null;
        $extractedGender = null;

        if ($isNumeric && $is16Digits) {
            $dayRaw = (int) substr($nik, 6, 2);
            $monthRaw = (int) substr($nik, 8, 2);
            $yearRaw = (int) substr($nik, 10, 2);

            $gender = $dayRaw > 40 ? 'Perempuan' : 'Laki-laki';
            $day = $dayRaw > 40 ? $dayRaw - 40 : $dayRaw;

            $currentTwoDigitYear = (int) date('y');
            $fullYear = $yearRaw <= $currentTwoDigitYear ? 2000 + $yearRaw : 1900 + $yearRaw;

            if ($day >= 1 && $day <= 31 && $monthRaw >= 1 && $monthRaw <= 12 && checkdate($monthRaw, $day, $fullYear)) {
                $extractedBirthDate = sprintf('%04d-%02d-%02d', $fullYear, $monthRaw, $day);
                $extractedGender = $gender;
            }
        }

        $checks = [
            'format_16_digit' => [
                'label' => 'Panjang & Format 16 Digit Angka',
                'passed' => $isNumeric && $is16Digits,
                'detail' => ($isNumeric && $is16Digits)
                    ? 'Sesuai (16 digit numerik)'
                    : 'Tidak sesuai (harus tepat 16 digit angka)',
            ],
            'struktur_nik' => [
                'label' => 'Pemeriksaan Struktur & Kode Wilayah NIK',
                'passed' => $structureValid,
                'detail' => $structureValid
                    ? "Kode Wilayah {$provinceCode}.{$cityCode}.{$districtCode} ({$provinceName}) & No. Urut {$serialNumber} valid"
                    : 'Kode wilayah atau nomor urut NIK tidak valid',
            ],
            'keunikan_nik' => [
                'label' => 'Pemeriksaan NIK Tidak Ganda',
                'passed' => $isNumeric && $is16Digits && $isUnique,
                'detail' => ! ($isNumeric && $is16Digits)
                    ? 'Menunggu format NIK 16 digit yang valid'
                    : ($isUnique ? 'NIK belum terdaftar pada akun lain (Unik)' : 'NIK sudah digunakan oleh akun lain (Ganda)'),
            ],
        ];

        $valid = empty($errors);

        return [
            'valid' => $valid,
            'status_label' => $valid ? 'TERVERIFIKASI (VALID)' : 'DITOLAK (TIDAK VALID)',
            'message' => $valid
                ? 'Pemeriksaan berhasil: Identitas NIK 16 digit valid, unik, dan telah terverifikasi.'
                : ($errors[0] ?? 'NIK tidak valid.'),
            'errors' => $errors,
            'checks' => $checks,
            'extracted' => [
                'nik' => $nik,
                'province_code' => $provinceCode,
                'province_name' => $provinceName,
                'city_code' => $cityCode,
                'district_code' => $districtCode,
                'birth_date' => $extractedBirthDate,
                'gender' => $extractedGender,
                'serial_number' => $serialNumber,
                'checked_at' => now()->format('d M Y H:i:s'),
            ],
        ];
    }
}

