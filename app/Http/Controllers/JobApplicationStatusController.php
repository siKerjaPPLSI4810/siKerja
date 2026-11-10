<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobApplicationStatusController extends Controller
{
    public function myApplications(Request $request): View
    {
        $applications = JobApplication::query()
            ->forUser($request->user())
            ->with('jobPosting.company')
            ->orderByDesc('applied_at')
            ->orderByDesc('id')
            ->paginate(10);

        return view('jobseeker.applications', compact('applications'));
    }
}
