<?php

namespace App\Services;

use App\Models\CompanyProfile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyInspectionDocument
{
    public const DISK = 'company_documents';

    public function path(CompanyProfile $company): ?string
    {
        $reference = $company->company_document_path;
        if (! is_string($reference)
            || ! preg_match('/\Acompany_documents\/[a-zA-Z0-9_-]+\.(pdf|jpg|jpeg|png)\z/i', $reference)) {
            return null;
        }

        // Never fall back to the legacy public disk or accept a request-supplied path.
        // Ambiguous cross-company references must be reviewed, not silently shared.
        if (CompanyProfile::query()->where('company_document_path', $reference)->where('id', '!=', $company->id)->exists()) {
            return null;
        }
        $disk = Storage::disk(self::DISK);
        $base = realpath($disk->path(''));
        $root = realpath($disk->path('company_documents'));
        $target = realpath($disk->path($reference));
        if ($base === false || $root === false || $target === false || ! is_file($target)) {
            return null;
        }
        $insidePrivate = PHP_OS_FAMILY === 'Windows'
            ? str_starts_with(strtolower($root.DIRECTORY_SEPARATOR), strtolower($base.DIRECTORY_SEPARATOR))
            : str_starts_with($root.DIRECTORY_SEPARATOR, $base.DIRECTORY_SEPARATOR);
        if (! $insidePrivate) {
            return null;
        }
        $prefix = $root.DIRECTORY_SEPARATOR;
        $contained = PHP_OS_FAMILY === 'Windows'
            ? str_starts_with(strtolower($target), strtolower($prefix))
            : str_starts_with($target, $prefix);

        return $contained ? $reference : null;
    }

    public function presentation(CompanyProfile $company): array
    {
        $path = $this->path($company);

        return [
            'available' => $path !== null,
            'display_name' => $path ? 'Dokumen legalitas perusahaan' : null,
            'download_url' => $path ? route('government.company-inspections.document', $company) : null,
        ];
    }

    public function download(CompanyProfile $company): StreamedResponse
    {
        $path = $this->path($company);
        abort_if($path === null, 404, 'Dokumen belum tersedia pada penyimpanan private atau belum dimigrasikan.');

        return Storage::disk(self::DISK)->download($path, 'dokumen-legalitas.'.pathinfo($path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
