<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use App\Models\JobPosting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JobApplicationController extends Controller
{
    public function applyJob(Request $request, int $id): RedirectResponse
    {
        return DB::transaction(function () use ($request, $id): RedirectResponse {
            $job = JobPosting::query()->published()->lockForUpdate()->findOrFail($id);

            if ($job->applicationDeadlinePassed()) {
                return redirect()->route('jobs.show', $job)
                    ->with('error', 'Batas waktu pendaftaran lowongan ini sudah berakhir.');
            }

            $validated = $request->validate([
                'applicant_notes' => ['nullable', 'string', 'max:2000'],
            ]);

            $profile = $request->user()->jobseekerProfile()->firstOrCreate([]);
            $application = JobApplication::query()->firstOrCreate([
                'job_posting_id' => $job->id,
                'jobseeker_profile_id' => $profile->id,
            ], [
                'status' => 'terkirim',
                'applicant_notes' => $validated['applicant_notes']
                    ?? 'Melamar menggunakan profil terintegrasi siKERJA.',
                'applied_at' => now(),
            ]);

            if (! $application->wasRecentlyCreated) {
                return redirect()->route('jobs.show', $job)
                    ->with('error', 'Anda telah mengirimkan lamaran untuk lowongan ini sebelumnya.');
            }

            return redirect()->route('jobseeker.applications')
                ->with('status', 'Lamaran Anda berhasil dikirim.');
        }, attempts: 3);
    }
}
