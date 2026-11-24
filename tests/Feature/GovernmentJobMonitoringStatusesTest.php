<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\JobPostingReviewFlag;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\GovernmentJobInspectionTestCase;

class GovernmentJobMonitoringStatusesTest extends GovernmentJobInspectionTestCase
{
    private const HTML = '/pemerintah/monitoring-lowongan';
    private const API = '/pemerintah/api/monitoring-lowongan';

    #[DataProvider('statusChoices')]
    public function test_default_and_selected_status_match_html_api(?string $selection, string $expected): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create(['company_name' => 'Sasaran Bersama']);
        $jobs = [];
        foreach (['published', 'closed', 'draft', 'pending_approval', 'rejected'] as $status) {
            $jobs[$status] = $this->listing(['status' => $status, 'title' => 'Sasaran '.$status, 'deadline' => null], $company);
        }
        $query = '?'.http_build_query(['q' => 'Sasaran', 'status' => $selection]);
        $html = $this->get(self::HTML.$query)->assertOk();
        $api = $this->get(self::API.$query)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('filters.status', $expected);
        $this->assertSame([$jobs[$expected]->id], $html->viewData('jobs')->modelKeys());
        $this->assertSame([$jobs[$expected]->id], array_column($api->json('data'), 'id'));
        $this->assertSame($html->viewData('filters'), $api->json('filters'));
        $this->assertSame(['id', 'title', 'company', 'category', 'location', 'status', 'deadline'], array_keys($api->json('data.0')));
        $this->assertNull($api->json('data.0.deadline'));
    }

    public static function statusChoices(): array
    {
        return [[null, 'published'], ['', 'published'], ['   ', 'published'], ['published', 'published'], [' closed ', 'closed']];
    }

    public function test_closed_filters_pagination_reset_and_global_options_are_consistent(): void
    {
        $this->admin();
        $closedCompany = CompanyProfile::factory()->create(['company_name' => 'Sasaran Closed']);
        $publishedCompany = CompanyProfile::factory()->create(['company_name' => 'Sasaran Published']);
        $hiddenCompany = CompanyProfile::factory()->create(['company_name' => 'Sasaran Pending']);
        $closed = [];
        foreach (['Analis Satu', 'Analis Dua'] as $title) {
            $closed[] = $this->listing(['status' => 'closed', 'title' => $title, 'category' => 'Kategori Closed', 'location' => 'Jakarta Selatan', 'deadline' => today()->subDay()], $closedCompany);
        }
        $published = $this->listing(['status' => 'published', 'title' => 'Analis Published', 'category' => 'Kategori Published', 'location' => 'Jakarta Selatan'], $publishedCompany);
        $this->listing(['status' => 'rejected', 'title' => 'Sasaran', 'category' => 'Kategori Rahasia'], $hiddenCompany);
        // Both branches of search OR must remain within selected status/company/category/location.
        $this->listing(['status' => 'published', 'title' => 'Analis Tersembunyi', 'category' => 'Kategori Closed', 'location' => 'Jakarta Selatan'], $closedCompany);
        $filters = ['status' => 'closed', 'company' => $closedCompany->id, 'category' => 'Kategori Closed', 'location' => 'Selatan', 'q' => 'Analis', 'per_page' => 1, 'page' => 2];
        $query = '?'.http_build_query($filters);
        $html = $this->get(self::HTML.$query)->assertOk();
        $api = $this->get(self::API.$query)->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.deadline', today()->subDay()->toDateString());
        $this->assertSame([$closed[1]->id], $html->viewData('jobs')->modelKeys());
        $this->assertSame([$closed[1]->id], array_column($api->json('data'), 'id'));
        $this->assertSame([$closedCompany->id, $publishedCompany->id], $html->viewData('companies')->modelKeys());
        $this->assertSame(['Kategori Closed', 'Kategori Published'], $html->viewData('categories')->all());
        $dom = new DOMDocument;
        @$dom->loadHTML($html->getContent());
        $xpath = new DOMXPath($dom);
        $this->assertSame('closed', $xpath->query('//select[@name="status"]/option[@selected]')->item(0)->getAttribute('value'));
        $this->assertSame(2, $xpath->query('//select[@name="status"]/option')->length);
        $this->assertSame(0, $xpath->query('//form//input[@name="page"]')->length);
        foreach ([$api->json('links.prev'), $html->viewData('jobs')->previousPageUrl()] as $url) {
            parse_str(parse_url($url, PHP_URL_QUERY), $parameters);
            foreach ($filters as $key => $value) $this->assertSame($key === 'page' ? '1' : (string) $value, $parameters[$key]);
        }
        $reset = $xpath->query('//a[normalize-space(.)="Reset filter"]')->item(0)->getAttribute('href');
        $this->get($reset)->assertOk()->assertViewHas('jobs', fn ($jobs) => $jobs->currentPage() === 1 && $jobs->contains('id', $published->id))
            ->assertViewHas('filters', fn ($filters) => $filters['status'] === 'published' && $filters['company'] === null && $filters['q'] === '');
        $this->get(self::HTML.'?status=closed&q=TidakAda')->assertOk()->assertSee('Tidak ada lowongan yang sesuai');
        $this->get(self::API.'?status=closed&q=TidakAda')->assertOk()->assertJsonCount(0, 'data');
        $this->get(self::HTML.'?status=published&per_page=1')->assertViewHas('companies', fn ($options) => $options->contains('id', $closedCompany->id));
    }

    public function test_closed_only_company_and_category_remain_options_under_published_default(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create();
        $this->listing(['status' => 'closed', 'category' => 'Closed Saja'], $company);
        $this->get(self::HTML)->assertOk()->assertViewHas('jobs', fn ($jobs) => $jobs->isEmpty())
            ->assertViewHas('companies', fn ($options) => $options->modelKeys() === [$company->id])
            ->assertViewHas('categories', fn ($options) => $options->all() === ['Closed Saja']);
    }

    public function test_closed_detail_escapes_content_hides_private_data_and_never_offers_new_mark(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create(['company_name' => '<script>company()</script>', 'verification_notes' => 'Secret company note']);
        $job = $this->listing(['status' => 'closed', 'title' => '<script>job()</script>', 'rejection_reason' => 'Secret rejection', 'deadline' => null], $company);
        $before = $job->fresh()->getRawOriginal();
        $this->get(self::HTML.'/'.$job->id)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertSee('Ditutup')
            ->assertDontSee('Disetujui / Tayang')->assertSee('<script>job()</script>')->assertDontSee('<script>job()</script>', false)
            ->assertSee('<script>company()</script>')->assertDontSee('<script>company()</script>', false)
            ->assertDontSee('Secret company note')->assertDontSee('Secret rejection')->assertDontSee('Tandai untuk Ditinjau')->assertDontSee('name="reason"', false);
        $this->post(self::API.'/'.$job->id.'/penandaan', ['reason' => 'Catatan'])->assertStatus(409);
        $this->assertSame(0, JobPostingReviewFlag::count());
        $this->assertSame($before, $job->fresh()->getRawOriginal());
    }

    public function test_retained_closed_flag_can_be_cleared_through_csrf_protected_html_form(): void
    {
        $this->admin();
        $job = $this->listing(['status' => 'published']);
        $url = self::API.'/'.$job->id.'/penandaan';
        $this->post($url, ['reason' => '<script>private flag</script>'])->assertOk();
        $job->update(['status' => 'closed']);
        $before = $job->fresh()->getRawOriginal();
        $flag = JobPostingReviewFlag::sole();
        $this->get(self::HTML.'?status=closed')->assertOk()->assertSee('Perlu Ditinjau')->assertDontSee('private flag');
        $this->get(self::HTML.'/'.$job->id)->assertOk()->assertSee('Perlu Ditinjau')->assertSee('Hapus Tanda')
            ->assertSee(auth()->user()->name)->assertSee('Waktu penandaan')->assertSee('<script>private flag</script>')->assertDontSee('<script>private flag</script>', false)
            ->assertDontSee('Tandai untuk Ditinjau')->assertSee('window.confirm', false);
        $this->get(self::API.'?status=closed')->assertOk()->assertDontSee('private flag')->assertDontSee('flagged_by');
        $this->app->bind(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
            fn ($app) => new class($app, $app['encrypter']) extends \Illuminate\Foundation\Http\Middleware\PreventRequestForgery { protected function runningUnitTests() { return false; } });
        $this->post($url, ['_method' => 'DELETE', '_flag_form' => '1'], ['Accept' => 'text/html'])->assertStatus(419);
        $this->assertTrue($flag->fresh()->isActive());
        $this->withSession(['_token' => 'synthetic-closed-token'])->post($url, ['_method' => 'DELETE', '_flag_form' => '1', '_token' => 'synthetic-closed-token'], ['Accept' => 'text/html'])
            ->assertRedirect(self::HTML.'/'.$job->id);
        $this->assertFalse($flag->fresh()->isActive());
        $this->assertSame(auth()->id(), $flag->fresh()->cleared_by);
        $this->assertNotNull($flag->fresh()->cleared_at);
        $this->get(self::HTML.'/'.$job->id)->assertOk()->assertSee('Ditutup')->assertSee('Tanda terakhir dicabut oleh')->assertDontSee('Tandai untuk Ditinjau')->assertDontSee('Hapus Tanda');
        $this->assertSame($before, $job->fresh()->getRawOriginal());
    }

    #[DataProvider('unauthorized')]
    public function test_closed_detail_and_list_require_active_government(?string $role): void
    {
        $job = $this->listing(['status' => 'closed']);
        if ($role !== null) $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => false]));
        foreach ([self::HTML.'?status=closed', self::HTML.'/'.$job->id] as $url) {
            $response = $this->get($url);
            $role === null ? $response->assertRedirect(route('login')) : $response->assertForbidden();
        }
        $this->get(self::API.'?status=closed')->assertStatus($role === null ? 401 : 403);
    }

    public static function unauthorized(): array
    {
        return [[null], ['pemerintah'], ['perusahaan'], ['pencari_kerja']];
    }
}
