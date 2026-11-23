<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\JobPosting;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\GovernmentJobInspectionTestCase;

class CompanyJobSubmissionRegressionTest extends GovernmentJobInspectionTestCase
{
    private function company(): CompanyProfile
    {
        $company = CompanyProfile::factory()->create();
        $this->actingAs($company->user);

        return $company;
    }

    private function input(): array
    {
        return [
            'title' => 'Pengembang Aplikasi', 'category' => 'Teknologi Informasi', 'job_type' => 'Kontrak',
            'location' => 'Bandung', 'education_requirement' => 'S1', 'description' => 'Deskripsi pekerjaan lebih dari dua puluh karakter.',
            'requirements' => 'Persyaratan pekerjaan lebih dari dua puluh karakter.', 'quota' => 1,
            'salary_around' => 1000000, 'deadline' => today()->addDay()->toDateString(),
        ];
    }

    public function test_draft_and_direct_submission_preserve_existing_company_workflow(): void
    {
        $company = $this->company();
        $this->post(route('company.jobs.store'), $this->input() + ['is_draft' => true])->assertRedirect(route('company.jobs.index'));
        $this->post(route('company.jobs.store'), $this->input())->assertRedirect(route('company.jobs.index'));
        $this->assertSame(['draft', 'pending_approval'], JobPosting::orderBy('id')->pluck('status')->all());
        $this->assertSame('pending', $company->fresh()->verification_status);
    }

    #[DataProvider('invalidInputs')]
    public function test_extracted_validator_preserves_company_submission_errors(string $field, mixed $value): void
    {
        $this->company();
        if ($field === 'deadline' && $value === '__today__') $value = today()->toDateString();
        $this->post(route('company.jobs.store'), array_merge($this->input(), [$field => $value]))->assertSessionHasErrors($field);
        $this->assertSame(0, JobPosting::count());
    }

    public static function invalidInputs(): array
    {
        return [
            ['title', ''], ['title', 'Programmer 123'], ['category', str_repeat('a', 101)], ['job_type', str_repeat('a', 51)],
            ['location', 'Bandung 123'], ['education_requirement', str_repeat('a', 51)],
            ['description', 'Short'], ['requirements', 'Short'], ['quota', 0], ['salary_around', 999999],
            ['deadline', '__today__'], ['deadline', null],
        ];
    }

    public function test_published_edit_and_rejected_resubmission_preserve_existing_metadata_behavior(): void
    {
        $company = $this->company();
        $oldApprover = User::factory()->create(['role' => 'pemerintah'])->id;
        foreach (['published', 'rejected'] as $status) {
            $job = $this->listing(['status' => $status, 'approved_by' => $oldApprover, 'rejection_reason' => 'Catatan lama'], $company);
            $input = $this->input();
            if ($status === 'rejected') $input['resubmit'] = true;
            $this->put(route('company.jobs.update', $job->id), $input)->assertRedirect(route('company.jobs.index'));
            $this->assertSame('pending_approval', $job->fresh()->status);
            $this->assertNull($job->fresh()->rejection_reason);
            // Known coordination item, deliberately not changed by this UI subtask.
            $this->assertSame($oldApprover, $job->fresh()->approved_by);
        }
    }

    public function test_toggle_closes_published_and_resubmits_closed_without_changing_rules(): void
    {
        $company = $this->company();
        $job = $this->listing(['status' => 'published'], $company);
        $this->post(route('company.jobs.toggle', $job->id))->assertRedirect(route('company.jobs.index'));
        $this->assertSame('closed', $job->fresh()->status);
        $this->post(route('company.jobs.toggle', $job->id))->assertRedirect(route('company.jobs.index'));
        $this->assertSame('pending_approval', $job->fresh()->status);
    }

    public function test_company_cannot_update_or_toggle_another_company_job(): void
    {
        $this->company();
        $job = $this->listing();
        $this->put(route('company.jobs.update', $job->id), $this->input())->assertNotFound();
        $this->post(route('company.jobs.toggle', $job->id))->assertNotFound();
        $this->assertSame('pending_approval', $job->fresh()->status);
    }
}
