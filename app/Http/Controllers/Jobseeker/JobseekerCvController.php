<?php

namespace App\Http\Controllers\Jobseeker;

use App\Http\Controllers\Controller;
use App\Models\JobseekerProfile;
use App\Services\CvPdfGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class JobseekerCvController extends Controller
{
    private function getLoadedProfile(Request $request): JobseekerProfile
    {
        $user = $request->user();

        $profile = JobseekerProfile::firstOrCreate(
            ['user_id' => $user->id],
            ['nik_verified' => false]
        );

        $profile->load(['user', 'educations', 'experiences', 'skills', 'awards']);

        return $profile;
    }

    /**
     * Menampilkan pratinjau CV otomatis berdasarkan data profil terbaru (PBI 1.1.3).
     */
    public function show(Request $request): View
    {
        $profile = $this->getLoadedProfile($request);
        $completeness = $profile->calculateCompleteness();

        return view('jobseeker.cv.show', [
            'user' => $request->user(),
            'profile' => $profile,
            'completeness' => $completeness,
        ]);
    }

    /**
     * Menampilkan tampilan siap cetak / PDF untuk CV otomatis (PBI 1.1.3).
     */
    public function print(Request $request): View
    {
        $profile = $this->getLoadedProfile($request);

        return view('jobseeker.cv.print', [
            'user' => $request->user(),
            'profile' => $profile,
        ]);
    }

    /**
     * Mengunduh CV otomatis dalam format PDF yang mencerminkan perubahan profil terbaru (PBI 1.1.3).
     */
    public function download(Request $request, CvPdfGenerator $pdfGenerator): Response
    {
        $profile = $this->getLoadedProfile($request);
        $pdfContent = $pdfGenerator->generate($profile);

        $slugName = Str::slug($profile->user->name ?? 'pencari-kerja');
        $nikSuffix = $profile->nik ?: $profile->id;
        $filename = "CV_{$slugName}_{$nikSuffix}.pdf";
        $storagePath = "cv/{$filename}";

        Storage::disk('local')->put($storagePath, $pdfContent);
        $profile->update(['cv_file_path' => $storagePath]);

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}

