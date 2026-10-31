<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\JobseekerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JobApplicationController extends Controller
{
    // PBI 1.2.2 - Mengajukan Lamaran Kerja
    public function applyJob(Request $request, $id)
    {
        $job = JobPosting::where('status', 'published')
            ->findOrFail($id);

        // Validasi batas waktu pendaftaran
        if ($job->deadline && $job->deadline->lt(today())) {
            return back()->with(
                'error',
                'Batas waktu pendaftaran lowongan ini sudah berakhir.'
            );
        }

        // Mengambil atau membuat profil pencari kerja
        $profile = JobseekerProfile::firstOrCreate(
            ['user_id' => Auth::id()],
            ['nik_verified' => false]
        );

        // Mencegah lamaran ganda
        $exists = JobApplication::where('job_posting_id', $job->id)
            ->where('jobseeker_profile_id', $profile->id)
            ->exists();

        if ($exists) {
            return back()->with(
                'error',
                'Anda telah mengirimkan lamaran untuk lowongan ini sebelumnya.'
            );
        }

        // Validasi catatan pelamar
        $validated = $request->validate([
            'applicant_notes' => 'nullable|string|max:2000',
        ]);

        // Menyimpan lamaran pekerjaan
        JobApplication::create([
            'job_posting_id' => $job->id,
            'jobseeker_profile_id' => $profile->id,
            'status' => 'terkirim',
            'applicant_notes' => $validated['applicant_notes']
                ?? 'Melamar menggunakan profil terintegrasi siKERJA.',
            'applied_at' => now(),
        ]);

        return redirect()
            ->route('jobseeker.applications')
            ->with(
                'success',
                'Lamaran Anda berhasil dikirimkan ke pihak perusahaan!'
            );
    }
}
