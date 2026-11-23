<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\GovernmentJobInspectionTestCase;

class GovernmentJobMonitoringFiltersTest extends GovernmentJobInspectionTestCase
{
    private const HTML = '/pemerintah/monitoring-lowongan';
    private const API = '/pemerintah/api/monitoring-lowongan';

    private function dataset(): array
    {
        $a = CompanyProfile::factory()->create(['company_name' => 'Perusahaan Sasaran']);
        $b = CompanyProfile::factory()->create(['company_name' => 'Perusahaan Sasaran Lain']);
        $hidden = CompanyProfile::factory()->create(['company_name' => 'Perusahaan Pending Saja']);
        $empty = CompanyProfile::factory()->create(['company_name' => 'Perusahaan Tanpa Lowongan']);
        $specs = [
            [$a, 'Analis Satu', 'Teknologi', 'Jakarta Selatan'], [$a, 'Analis Dua', 'Teknologi', 'Jakarta Selatan'],
            [$a, 'Insinyur', 'Teknologi', 'Jakarta Utara'], [$a, 'Teknisi', 'Teknologi Lanjutan', 'Jakarta Selatan'],
            [$a, 'Akuntan', 'Keuangan', 'Bandung'], [$b, 'Analis Lain', 'Teknologi', 'Jakarta Selatan'],
        ];
        $jobs = [];
        foreach ($specs as [$company, $title, $category, $location]) {
            $jobs[] = $this->listing(['status' => 'published', 'title' => $title, 'category' => $category, 'location' => $location], $company);
        }
        foreach (['draft', 'pending_approval', 'closed', 'rejected'] as $status) {
            $this->listing(['status' => $status, 'title' => 'Analis Tidak Publik', 'category' => 'Teknologi', 'location' => 'Jakarta Selatan'], $a);
        }
        $this->listing(['category' => 'Kategori Pending Saja'], $hidden);

        return compact('a', 'b', 'hidden', 'empty', 'jobs');
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($dom);
    }

    #[DataProvider('filterCases')]
    public function test_individual_and_combined_filters_match_html_api(array $filters, array $positions): void
    {
        $this->admin(); $data = $this->dataset();
        if (($filters['company'] ?? null) === '__A__') $filters['company'] = $data['a']->id;
        $query = '?'.http_build_query($filters);
        $html = $this->get(self::HTML.$query)->assertOk();
        $api = $this->get(self::API.$query)->assertOk();
        $expected = array_map(fn ($position) => $data['jobs'][$position]->id, $positions);
        $this->assertSame($expected, $html->viewData('jobs')->getCollection()->modelKeys());
        $this->assertSame($expected, array_column($api->json('data'), 'id'));
        $this->assertSame($html->viewData('filters'), $api->json('filters'));
        $this->assertSame(count($expected), $api->json('meta.total'));
        foreach ($api->json('data') as $job) $this->assertSame('published', $job['status']);
    }

    public static function filterCases(): array
    {
        return [
            'company ID exact' => [['company' => '__A__'], [0,1,2,3,4]],
            'category exact' => [['category' => 'Teknologi'], [0,1,2,5]],
            'partial location' => [['location' => 'Selatan'], [0,1,3,5]],
            'published status' => [['status' => 'published'], [0,1,2,3,4,5]],
            'all four filters' => [['company' => '__A__', 'category' => 'Teknologi', 'location' => 'Selatan', 'status' => 'published'], [0,1]],
            'company OR search with all filters' => [['q' => '  Sasaran  ', 'company' => '__A__', 'category' => ' Teknologi ', 'location' => ' Selatan ', 'status' => ' published '], [0,1]],
            'title OR search with all filters' => [['q' => 'Dua', 'company' => '__A__', 'category' => 'Teknologi', 'location' => 'Selatan', 'status' => 'published'], [1]],
        ];
    }

    public function test_selection_pagination_and_reset_preserve_then_clear_all_filters(): void
    {
        $this->admin(); $data = $this->dataset();
        $filters = ['company' => $data['a']->id, 'category' => 'Teknologi', 'location' => 'Selatan', 'status' => 'published', 'q' => 'Sasaran', 'per_page' => 1, 'page' => 2];
        $html = $this->get(self::HTML.'?'.http_build_query($filters))->assertOk();
        $api = $this->get(self::API.'?'.http_build_query($filters))->assertOk();
        $this->assertSame([$data['jobs'][1]->id], $html->viewData('jobs')->getCollection()->modelKeys());
        $this->assertSame([$data['jobs'][1]->id], array_column($api->json('data'), 'id'));
        $dom = $this->xpath($html->getContent());
        foreach (['company', 'category', 'status'] as $name) {
            $this->assertSame((string) $filters[$name], $dom->query('//select[@name="'.$name.'"]/option[@selected]')->item(0)->getAttribute('value'));
        }
        foreach (['q', 'location', 'per_page'] as $name) $this->assertSame((string) $filters[$name], $dom->query('//input[@name="'.$name.'"]')->item(0)->getAttribute('value'));
        $this->assertSame(0, $dom->query('//form//input[@name="page"]')->length);
        parse_str(parse_url($api->json('links.prev'), PHP_URL_QUERY), $apiPrevious);
        parse_str(parse_url($html->viewData('jobs')->previousPageUrl(), PHP_URL_QUERY), $htmlPrevious);
        foreach ($filters as $key => $value) {
            $expected = $key === 'page' ? '1' : (string) $value;
            $this->assertSame($expected, $apiPrevious[$key]); $this->assertSame($expected, $htmlPrevious[$key]);
        }
        $reset = $dom->query('//a[normalize-space(.)="Reset filter"]')->item(0)->getAttribute('href');
        $this->assertSame(route('government.job-monitoring.index'), $reset);
        $resetResponse = $this->get($reset)->assertOk()->assertViewHas('jobs', fn ($jobs) => $jobs->total() === 6 && $jobs->currentPage() === 1);
        $this->assertSame(['q'=>'', 'company'=>null, 'category'=>'', 'location'=>'', 'status'=>'published', 'per_page'=>15], $resetResponse->viewData('filters'));
    }

    public function test_dynamic_options_cover_published_scope_independent_of_filters_and_page(): void
    {
        $this->admin(); $data = $this->dataset();
        $response = $this->get(self::HTML.'?'.http_build_query(['company'=>$data['a']->id,'category'=>'Teknologi','q'=>'Dua','per_page'=>1]))->assertOk();
        $this->assertSame([$data['a']->id,$data['b']->id], $response->viewData('companies')->modelKeys());
        $this->assertSame(['Keuangan','Teknologi','Teknologi Lanjutan'], $response->viewData('categories')->all());
        $response->assertDontSee('Perusahaan Pending Saja')->assertDontSee('Perusahaan Tanpa Lowongan')->assertDontSee('Kategori Pending Saja');
        $dom=$this->xpath($response->getContent());
        $options=$dom->query('//select[@name="status"]/option');
        $this->assertSame(1,$options->length); $this->assertSame('published',$options->item(0)->getAttribute('value'));
        $response->assertSee('Saat ini hanya published')->assertSee('Belum disepakati')->assertDontSee('Semua status dalam cakupan monitoring');
    }

    public function test_valid_but_unmatched_filter_values_remain_visible_without_dummy_choices(): void
    {
        $this->admin(); $this->dataset(); $category='<script>alert(1)</script>';
        $query='?'.http_build_query(['company'=>999999,'category'=>$category]);
        $html=$this->get(self::HTML.$query)->assertOk()->assertSee('Tidak ada lowongan yang sesuai')->assertSee('tidak ada dalam opsi monitoring')->assertDontSee($category,false);
        $api=$this->get(self::API.$query)->assertOk()->assertJsonCount(0,'data');
        $this->assertSame($html->viewData('filters'),$api->json('filters'));
        $dom=$this->xpath($html->getContent());
        $this->assertSame('999999',$dom->query('//select[@name="company"]/option[@selected]')->item(0)->getAttribute('value'));
        $this->assertSame($category,$dom->query('//select[@name="category"]/option[@selected]')->item(0)->getAttribute('value'));
        $this->assertCount(2,$html->viewData('companies')); $this->assertCount(3,$html->viewData('categories'));
    }

    #[DataProvider('invalidFilters')]
    public function test_html_and_api_reject_same_invalid_filter_without_writes(string $field,mixed $value): void
    {
        $this->admin(); $job=$this->listing(['status'=>'published']); $before=$job->fresh()->getRawOriginal();
        $query='?'.http_build_query([$field=>$value]);
        $this->from(self::HTML)->get(self::HTML.$query)->assertRedirect(self::HTML)->assertSessionHasErrors($field);
        $this->get(self::API.$query)->assertUnprocessable()->assertHeader('Content-Type','application/json')->assertJsonValidationErrors($field);
        $this->assertSame($before,$job->fresh()->getRawOriginal());
    }

    public static function invalidFilters(): array
    {
        return [ ['company',0], ['company',['bad']], ['category',['bad']], ['location',str_repeat('a',256)],
            ['status','closed'], ['status','rejected'], ['status','pending_approval'], ['status','draft'], ['status',['published']],
            ['q',['bad']], ['per_page',51] ];
    }
}
