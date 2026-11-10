<?php

namespace App\Services;

use App\Models\JobseekerProfile;

class CvPdfGenerator
{
    /**
     * Menghasilkan dokumen PDF A4 murni (%PDF-1.4) dari data profil pencari kerja (PBI 1.1.3).
     */
    public function generate(JobseekerProfile $profile): string
    {
        $profile->loadMissing(['user', 'educations', 'experiences', 'skills', 'awards']);
        $user = $profile->user;

        $pages = [];
        $currentCommands = [];
        $y = 780.0;

        $startNewPage = function () use (&$pages, &$currentCommands, &$y) {
            if (! empty($currentCommands)) {
                $pages[] = implode("\n", $currentCommands);
            }
            $currentCommands = [];
            $y = 780.0;
        };

        $ensureSpace = function (float $neededHeight) use (&$y, $startNewPage) {
            if ($y - $neededHeight < 55.0) {
                $startNewPage();
            }
        };

        $addText = function (float $x, float $yPos, string $text, string $font = 'F1', int $size = 10, array $rgb = [0.12, 0.16, 0.23]) use (&$currentCommands) {
            $safe = $this->escapePdfText($text);
            $r = number_format($rgb[0], 2, '.', '');
            $g = number_format($rgb[1], 2, '.', '');
            $b = number_format($rgb[2], 2, '.', '');
            $yFormatted = number_format($yPos, 2, '.', '');
            $xFormatted = number_format($x, 2, '.', '');
            $currentCommands[] = "BT /{$font} {$size} Tf {$r} {$g} {$b} rg {$xFormatted} {$yFormatted} Td ({$safe}) Tj ET";
        };

        $addLine = function (float $x1, float $y1, float $x2, float $y2, float $width = 0.8, array $rgb = [0.82, 0.86, 0.91]) use (&$currentCommands) {
            $r = number_format($rgb[0], 2, '.', '');
            $g = number_format($rgb[1], 2, '.', '');
            $b = number_format($rgb[2], 2, '.', '');
            $w = number_format($width, 2, '.', '');
            $currentCommands[] = "{$w} w {$r} {$g} {$b} RG ".number_format($x1, 2, '.', '').' '.number_format($y1, 2, '.', '').' m '.number_format($x2, 2, '.', '').' '.number_format($y2, 2, '.', '').' l S';
        };

        $addRect = function (float $x, float $yPos, float $w, float $h, array $rgb = [0.09, 0.32, 0.68]) use (&$currentCommands) {
            $r = number_format($rgb[0], 2, '.', '');
            $g = number_format($rgb[1], 2, '.', '');
            $b = number_format($rgb[2], 2, '.', '');
            $currentCommands[] = "{$r} {$g} {$b} rg ".number_format($x, 2, '.', '').' '.number_format($yPos, 2, '.', '').' '.number_format($w, 2, '.', '').' '.number_format($h, 2, '.', '').' re f';
        };

        $addWrappedText = function (float $x, string $text, string $font = 'F1', int $size = 10, int $maxChars = 90, float $lineHeight = 14.0, array $rgb = [0.20, 0.25, 0.33]) use (&$y, $ensureSpace, $addText) {
            $paragraphs = preg_split('/\r\n|\r|\n/', trim($text)) ?: [];
            foreach ($paragraphs as $paragraph) {
                $wrapped = wordwrap(trim($paragraph), $maxChars, "\n", true);
                $lines = explode("\n", $wrapped);
                foreach ($lines as $line) {
                    $ensureSpace($lineHeight);
                    $addText($x, $y, $line, $font, $size, $rgb);
                    $y -= $lineHeight;
                }
            }
        };

        $addSectionHeader = function (string $title) use (&$y, $ensureSpace, $addText, $addLine) {
            $ensureSpace(36.0);
            $y -= 8.0;
            $addText(50.0, $y, strtoupper($title), 'F2', 11, [0.09, 0.32, 0.68]);
            $y -= 5.0;
            $addLine(50.0, $y, 545.0, $y, 1.2, [0.09, 0.32, 0.68]);
            $y -= 14.0;
        };

        // Banner Header CV
        $addRect(0.0, 735.0, 595.28, 107.0, [0.08, 0.24, 0.49]);
        $addText(50.0, 800.0, strtoupper($user?->name ?? 'PENCARI KERJA'), 'F2', 20, [1.0, 1.0, 1.0]);

        $nikStatus = $profile->nik_verified ? 'Terverifikasi SIkERJA' : 'Belum Terverifikasi';
        $nikLine = 'NIK: '.($profile->nik ?: '-').' ('.$nikStatus.')';
        $addText(50.0, 780.0, $nikLine, 'F2', 10, [0.75, 0.90, 1.0]);

        $contactParts = array_filter([
            $user?->email,
            $user?->phone ? 'Telp: '.$user->phone : null,
            ($profile->city || $profile->province) ? trim(($profile->city ?? '').', '.($profile->province ?? ''), ', ') : null,
        ]);
        $addText(50.0, 763.0, implode('  |  ', $contactParts), 'F1', 9, [0.92, 0.95, 1.0]);

        $metaParts = array_filter([
            $profile->gender ? 'Jenis Kelamin: '.$profile->gender : null,
            $profile->birth_date ? 'Tgl Lahir: '.$profile->birth_date->format('d M Y') : null,
            $profile->expected_salary ? 'Ekspektasi Gaji: Rp '.number_format((float) $profile->expected_salary, 0, ',', '.') : null,
        ]);
        if (! empty($metaParts)) {
            $addText(50.0, 748.0, implode('  |  ', $metaParts), 'F1', 9, [0.85, 0.91, 0.98]);
        }

        $y = 715.0;

        if (! empty($profile->address)) {
            $addText(50.0, $y, 'Alamat Domisili: '.$profile->address, 'F1', 9, [0.30, 0.35, 0.42]);
            $y -= 14.0;
        }

        // 1. Ringkasan Profil
        $addSectionHeader('Ringkasan Profil');
        if (! empty($profile->bio)) {
            $addWrappedText(50.0, $profile->bio, 'F1', 10, 92, 14.0);
        } else {
            $addText(50.0, $y, 'Belum ada ringkasan profil yang ditambahkan.', 'F1', 10, [0.45, 0.50, 0.56]);
            $y -= 14.0;
        }

        // 2. Riwayat Pendidikan
        $addSectionHeader('Riwayat Pendidikan');
        if ($profile->educations->isNotEmpty()) {
            foreach ($profile->educations as $edu) {
                $ensureSpace(34.0);
                $period = $edu->start_year.' - '.($edu->end_year ?: 'Sekarang');
                $addText(50.0, $y, $edu->degree.' - '.$edu->major, 'F2', 10, [0.10, 0.14, 0.20]);
                $addText(430.0, $y, $period, 'F2', 9, [0.09, 0.32, 0.68]);
                $y -= 13.0;
                $addText(50.0, $y, $edu->institution_name, 'F1', 9, [0.30, 0.36, 0.44]);
                $y -= 16.0;
            }
        } else {
            $addText(50.0, $y, 'Belum ada data pendidikan.', 'F1', 10, [0.45, 0.50, 0.56]);
            $y -= 14.0;
        }

        // 3. Pengalaman Kerja
        $addSectionHeader('Pengalaman Kerja');
        if ($profile->experiences->isNotEmpty()) {
            foreach ($profile->experiences as $exp) {
                $ensureSpace(44.0);
                $start = $exp->start_date ? $exp->start_date->format('M Y') : '-';
                $end = $exp->is_current ? 'Sekarang' : ($exp->end_date ? $exp->end_date->format('M Y') : '-');
                $addText(50.0, $y, $exp->position.' - '.$exp->company_name, 'F2', 10, [0.10, 0.14, 0.20]);
                $addText(410.0, $y, "{$start} - {$end}", 'F2', 9, [0.09, 0.32, 0.68]);
                $y -= 14.0;
                if (! empty($exp->description)) {
                    $addWrappedText(60.0, $exp->description, 'F1', 9, 88, 13.0, [0.28, 0.33, 0.40]);
                }
                $y -= 6.0;
            }
        } else {
            $addText(50.0, $y, 'Belum ada data pengalaman kerja.', 'F1', 10, [0.45, 0.50, 0.56]);
            $y -= 14.0;
        }

        // 4. Kompetensi & Keahlian
        $addSectionHeader('Kompetensi & Keahlian');
        if ($profile->skills->isNotEmpty()) {
            $skillItems = $profile->skills->map(fn ($skill) => $skill->skill_name.' ('.$skill->proficiency_level.')')->implode('   •   ');
            $addWrappedText(50.0, $skillItems, 'F1', 10, 90, 14.0, [0.15, 0.20, 0.28]);
        } else {
            $addText(50.0, $y, 'Belum ada data kompetensi/keahlian.', 'F1', 10, [0.45, 0.50, 0.56]);
            $y -= 14.0;
        }

        // 5. Penghargaan & Sertifikasi
        $addSectionHeader('Penghargaan & Sertifikasi');
        if ($profile->awards->isNotEmpty()) {
            foreach ($profile->awards as $award) {
                $ensureSpace(36.0);
                $addText(50.0, $y, $award->title.' - '.$award->issuer, 'F2', 10, [0.10, 0.14, 0.20]);
                $addText(480.0, $y, (string) $award->year, 'F2', 9, [0.09, 0.32, 0.68]);
                $y -= 13.0;
                if (! empty($award->description)) {
                    $addWrappedText(60.0, $award->description, 'F1', 9, 88, 13.0, [0.28, 0.33, 0.40]);
                }
                $y -= 6.0;
            }
        } else {
            $addText(50.0, $y, 'Belum ada data penghargaan atau sertifikasi.', 'F1', 10, [0.45, 0.50, 0.56]);
            $y -= 14.0;
        }

        // Footer timestamp
        $ensureSpace(25.0);
        $y -= 10.0;
        $addLine(50.0, $y, 545.0, $y, 0.5, [0.82, 0.86, 0.91]);
        $y -= 12.0;
        $addText(50.0, $y, 'Dokumen CV Otomatis SIkERJA - Dihasilkan pada '.now()->format('d M Y H:i'), 'F1', 8, [0.50, 0.55, 0.62]);

        if (! empty($currentCommands)) {
            $pages[] = implode("\n", $currentCommands);
        }

        return $this->compilePdfDocument($pages);
    }

    private function escapePdfText(string $text): string
    {
        $normalized = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
        if ($normalized === false) {
            $normalized = preg_replace('/[^\x20-\x7E]/', ' ', $text) ?? '';
        }

        return str_replace(
            ['\\', '(', ')', "\r", "\n"],
            ['\\\\', '\\(', '\\)', ' ', ' '],
            $normalized
        );
    }

    /**
     * @param  array<int, string>  $pages
     */
    private function compilePdfDocument(array $pages): string
    {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $kids = [];
        $nextId = 5;

        foreach ($pages as $streamContent) {
            $pageObjId = $nextId++;
            $contentObjId = $nextId++;
            $kids[] = "{$pageObjId} 0 R";

            $length = strlen($streamContent);
            $objects[$contentObjId] = "<< /Length {$length} >>\nstream\n{$streamContent}\nendstream";
            $objects[$pageObjId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$contentObjId} 0 R >>";
        }

        $kidsList = implode(' ', $kids);
        $pageCount = count($kids);
        $objects[2] = "<< /Type /Pages /Kids [ {$kidsList} ] /Count {$pageCount} >>";

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $totalObjects = count($objects) + 1;

        $pdf .= "xref\n0 {$totalObjects}\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i < $totalObjects; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }

        $pdf .= "trailer\n<< /Size {$totalObjects} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }
}
