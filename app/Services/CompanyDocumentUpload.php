<?php

namespace App\Services;

use App\Models\CompanyInspection;
use App\Models\CompanyProfile;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CompanyDocumentUpload
{
    public function persist(?UploadedFile $file, Closure $save): mixed
    {
        if ($file) {
            Validator::make(['company_document' => $file], [
                'company_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            ])->validate();
        }
        $newPath = null;

        try {
            return DB::transaction(function () use ($file, $save, &$newPath) {
                if ($file) {
                    $extension = strtolower($file->extension());
                    if (! in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
                        throw ValidationException::withMessages(['company_document' => 'Tipe dokumen tidak didukung.']);
                    }
                    $filename = Str::random(64).'.'.$extension;
                    $candidate = 'company_documents/'.$filename;
                    try {
                        $disk = Storage::disk(CompanyInspectionDocument::DISK);
                        if ($disk->exists($candidate)) {
                            throw new \RuntimeException('Generated reference collision.');
                        }
                        $newPath = $candidate;
                        $stored = $disk->putFileAs('company_documents', $file, $filename, ['visibility' => 'private']);
                        if ($stored !== $newPath || ! $disk->exists($newPath) || $disk->size($newPath) !== filesize($file->getRealPath())) {
                            throw new \RuntimeException('Storage write not confirmed.');
                        }
                    } catch (Throwable $exception) {
                        throw ValidationException::withMessages(['company_document' => 'Dokumen gagal disimpan. Silakan unggah kembali.']);
                    }
                }

                return $save($newPath);
            });
        } catch (Throwable $exception) {
            // Only this attempt's newly generated reference can be compensated.
            // Never remove the previous document or a referenced inspection snapshot.
            if ($newPath !== null && DB::transactionLevel() === 0) {
                try {
                    $referenced = CompanyProfile::query()->where('company_document_path', $newPath)->exists()
                        || CompanyInspection::query()->where('company_snapshot->company_document_path', $newPath)->exists();
                    if (! $referenced && ! Storage::disk(CompanyInspectionDocument::DISK)->delete($newPath)) {
                        Log::warning('New company document cleanup requires review.');
                    }
                } catch (Throwable $cleanupException) {
                    Log::warning('New company document cleanup could not be confirmed.');
                }
            }
            if ($exception instanceof QueryException) {
                report($exception);
                throw ValidationException::withMessages(['company_document' => 'Data belum tersimpan. Silakan coba kembali.']);
            }

            throw $exception;
        }
    }
}
