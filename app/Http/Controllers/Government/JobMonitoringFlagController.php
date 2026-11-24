<?php

namespace App\Http\Controllers\Government;

use App\Http\Controllers\Controller;
use App\Services\JobPostingReviewFlagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class JobMonitoringFlagController extends Controller
{
    private function browserForm(Request $request): bool
    {
        return $request->input('_flag_form') === '1' && ! $request->expectsJson() && str_contains($request->header('Accept', ''), 'text/html');
    }

    private function respond(Request $request, string $identifier, callable $operation, string $message): JsonResponse|RedirectResponse
    {
        try {
            $flag = $operation();
        } catch (ValidationException|ConflictHttpException $exception) {
            if (! $this->browserForm($request)) throw $exception;

            return redirect()->route('government.job-monitoring.show', ['jobPosting' => $identifier])
                ->withInput($request->only('reason'))
                ->withErrors($exception instanceof ValidationException ? $exception->errors() : ['flag' => $exception->getMessage()])
                ->header('Cache-Control', 'no-store, private');
        }
        if ($this->browserForm($request)) {
            return redirect()->route('government.job-monitoring.show', ['jobPosting' => $identifier])
                ->with('status', $message)->header('Cache-Control', 'no-store, private');
        }

        return response()->json(['message' => $message, 'data' => [
            'job_posting_id' => (string) $identifier, 'active' => $flag?->isActive() ?? false,
        ]])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, string $jobPosting, JobPostingReviewFlagService $service): JsonResponse|RedirectResponse
    {
        return $this->respond($request, $jobPosting,
            fn () => $service->mark($jobPosting, $request->user(), $request->all()), 'Lowongan berhasil ditandai untuk ditinjau.');
    }

    public function destroy(Request $request, string $jobPosting, JobPostingReviewFlagService $service): JsonResponse|RedirectResponse
    {
        return $this->respond($request, $jobPosting,
            fn () => $service->clear($jobPosting, $request->user(), $request->all()), 'Tanda tinjauan tidak aktif.');
    }
}
