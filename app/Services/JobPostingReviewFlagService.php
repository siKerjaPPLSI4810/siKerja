<?php

namespace App\Services;

use App\Models\JobPosting;
use App\Models\JobPostingReviewFlag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class JobPostingReviewFlagService
{
    private function validate(array $input, bool $marking): array
    {
        if (isset($input['reason']) && is_string($input['reason'])) $input['reason'] = trim($input['reason']);
        $rules = ['reason' => $marking ? ['required', 'string', 'max:1000'] : ['prohibited']];
        foreach (['status', 'approved_by', 'rejection_reason', 'flagged_by', 'flagged_at', 'cleared_by', 'cleared_at', 'job_posting_id'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return Validator::make($input, $rules)->validate();
    }

    public function mark(string $identifier, User $actor, array $input): JobPostingReviewFlag
    {
        Gate::forUser($actor)->authorize('access-government');
        $data = $this->validate($input, true);

        return DB::transaction(function () use ($identifier, $actor, $data) {
            // Lock the parent even when no flag exists; all flag operations take the same lock first.
            $job = JobPosting::query()->lockForUpdate()->findOrFail($identifier);
            if ($job->status !== 'published') throw new ConflictHttpException('Hanya lowongan published yang dapat ditandai.');
            $flag = JobPostingReviewFlag::where('job_posting_id', $job->id)->lockForUpdate()->first();
            if ($flag?->isActive()) throw new ConflictHttpException('Lowongan sudah memiliki tanda aktif. Cabut tanda sebelum menandai ulang.');
            $flag ??= new JobPostingReviewFlag(['job_posting_id' => $job->id]);
            $flag->fill(['reason' => $data['reason'], 'flagged_by' => $actor->id, 'flagged_at' => now(), 'cleared_by' => null, 'cleared_at' => null]);
            $flag->save();

            return $flag;
        }, 3);
    }

    public function clear(string $identifier, User $actor, array $input = []): ?JobPostingReviewFlag
    {
        Gate::forUser($actor)->authorize('access-government');
        $this->validate($input, false);

        return DB::transaction(function () use ($identifier, $actor) {
            $job = JobPosting::query()->lockForUpdate()->findOrFail($identifier);
            $flag = JobPostingReviewFlag::where('job_posting_id', $job->id)->lockForUpdate()->first();
            // Clearing is manual, including retained flags after a job changes status. Never create a row here.
            if ($flag?->isActive()) {
                $flag->fill(['cleared_by' => $actor->id, 'cleared_at' => now()])->save();
            }

            return $flag;
        }, 3);
    }
}
