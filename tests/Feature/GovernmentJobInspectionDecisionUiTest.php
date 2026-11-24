<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\JobPosting;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\GovernmentJobInspectionTestCase;

class GovernmentJobInspectionDecisionUiTest extends GovernmentJobInspectionTestCase
{
    private function job(): JobPosting
    {
        return $this->listing([
            'title' => 'Pengembang Aplikasi', 'description' => 'Deskripsi pekerjaan lengkap untuk pemeriksaan.',
            'requirements' => 'Persyaratan pekerjaan lengkap untuk pemeriksaan.',
            'deadline' => today()->addDay()->toDateString(),
        ], CompanyProfile::factory()->create(['verification_status' => 'verified']));
    }

    private function page(JobPosting $job): DOMXPath
    {
        $html = $this->get(route('government.job-inspections.show', $job))->assertOk()
            ->assertSee('Setujui Lowongan')->assertSee('Tolak Lowongan')->assertSee('Minta Perbaikan')
            ->assertSee('js/job-inspection-decision.js', false)->getContent();
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($dom);
    }

    private function form(DOMXPath $xpath, string $action): array
    {
        $forms = $xpath->query('//form[@data-decision-action="'.$action.'"]');
        $this->assertSame(1, $forms->length);
        $form = $forms->item(0);
        $this->assertSame('POST', $form->getAttribute('method'));
        $payload = [];
        foreach ($xpath->query('.//input[@type="hidden"]', $form) as $input) {
            $payload[$input->getAttribute('name')] = $input->getAttribute('value');
        }
        $this->assertSame($action, $payload['action']);
        $this->assertNotEmpty($payload['_token']);

        return [$form->getAttribute('action'), $payload, $form];
    }

    private function enforceCsrf(): void
    {
        $this->app->bind(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
            fn ($app) => new class($app, $app['encrypter']) extends \Illuminate\Foundation\Http\Middleware\PreventRequestForgery {
                protected function runningUnitTests() { return false; }
            });
    }

    public function test_forms_have_correct_payload_tokens_constraints_and_accessible_feedback(): void
    {
        $this->admin();
        $job = $this->job();
        $xpath = $this->page($job);
        $this->assertSame(3, $xpath->query('//form[@data-decision-action]')->length);
        foreach (['approve', 'reject', 'request_changes'] as $action) {
            [$url, $payload, $form] = $this->form($xpath, $action);
            $this->assertSame(route('government.job-inspections.api.decisions.store', $job), $url);
            $this->assertSame(csrf_token(), $payload['_token']);
            $this->assertSame(1, $xpath->query('.//fieldset[@disabled and @data-decision-fields]', $form)->length);
            if ($action !== 'approve') {
                $textarea = $xpath->query('.//textarea[@name="rejection_reason"]', $form)->item(0);
                $this->assertTrue($textarea->hasAttribute('required'));
                $action === 'reject' ? $this->assertSame('1000', $textarea->getAttribute('maxlength')) : $this->assertFalse($textarea->hasAttribute('maxlength'));
            }
        }
        $this->assertSame(1, $xpath->query('//*[@data-decision-error and @role="alert"]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-decision-status and @role="status"]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-decision-reload]')->length);
        $this->assertSame(1, $xpath->query('//noscript')->length);
    }

    #[DataProvider('actions')]
    public function test_rendered_form_payload_works_with_real_csrf_and_existing_api(string $action, string $status): void
    {
        $this->admin();
        $job = $this->job();
        $this->enforceCsrf();
        [$url, $payload] = $this->form($this->page($job), $action);
        if ($action !== 'approve') {
            $payload['rejection_reason'] = '  Perbaiki informasi lowongan.  ';
        }
        $this->post($url, $payload, ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.status', $status);
        $this->assertSame($status, $job->fresh()->status);
        $action === 'approve' ? $this->assertNull($job->fresh()->rejection_reason) : $this->assertSame('Perbaiki informasi lowongan.', $job->fresh()->rejection_reason);
        $this->get(route('government.job-inspections.index'))->assertDontSee('Pengembang Aplikasi');
    }

    public static function actions(): array
    {
        return [['approve', 'published'], ['reject', 'rejected'], ['request_changes', 'rejected']];
    }

    #[DataProvider('badReasons')]
    public function test_form_errors_are_returned_without_changing_pending_job(string $action, string $reason): void
    {
        $this->admin();
        $job = $this->job();
        [$url, $payload] = $this->form($this->page($job), $action);
        $this->post($url, $payload + ['rejection_reason' => $reason])->assertUnprocessable()->assertJsonValidationErrors('rejection_reason');
        $this->assertSame('pending_approval', $job->fresh()->status);
    }

    public static function badReasons(): array
    {
        return [['reject', '   '], ['reject', str_repeat('a', 1001)], ['request_changes', '   ']];
    }

    public function test_form_without_csrf_is_rejected_and_final_status_cannot_be_replayed(): void
    {
        $this->admin();
        $job = $this->job();
        $this->enforceCsrf();
        [$url, $payload] = $this->form($this->page($job), 'approve');
        $this->post($url, ['action' => 'approve'])->assertStatus(419);
        $this->assertSame('pending_approval', $job->fresh()->status);
        $this->post($url, $payload)->assertOk();
        $this->post($url, $payload)->assertStatus(409);
        $this->assertSame('published', $job->fresh()->status);
        $this->get(route('government.job-inspections.show', $job))->assertNotFound()->assertDontSee('data-job-decision-panel', false);
    }

    public function test_unauthorized_detail_does_not_expose_forms(): void
    {
        $job = $this->job();
        $this->actingAs(User::factory()->create(['role' => 'pemerintah', 'is_active' => false]));
        $this->get(route('government.job-inspections.show', $job))->assertForbidden()->assertDontSee('data-job-decision-panel', false);
    }
}
