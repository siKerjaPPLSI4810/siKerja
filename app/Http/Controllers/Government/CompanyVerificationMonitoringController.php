<?php

namespace App\Http\Controllers\Government;

use App\Http\Controllers\Controller;
use App\Http\Requests\Government\ListCompanyVerificationStatusesRequest;
use App\Http\Requests\Government\ShowCompanyVerificationStatusRequest;
use App\Models\CompanyProfile;
use App\Services\CompanyVerificationMonitoringQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class CompanyVerificationMonitoringController extends Controller
{
    private function returnFilters(array $input): array
    {
        return ['status' => $input['status'] ?? '', 'q' => trim($input['q'] ?? ''),
            'page' => (int) ($input['page'] ?? 1), 'per_page' => (int) ($input['per_page'] ?? 15)];
    }

    public function index(ListCompanyVerificationStatusesRequest $request, CompanyVerificationMonitoringQuery $query): Response|RedirectResponse
    {
        $input = $request->validated();
        $companies = $query->paginate($input);
        if ($companies->currentPage() > $companies->lastPage()) {
            return redirect()->route('government.company-verification-statuses.index',
                array_replace($this->returnFilters($input), ['page' => $companies->lastPage()]));
        }
        $companies->setCollection($companies->getCollection()->map(fn (CompanyProfile $company): array => [
            'id' => $company->id, 'company_name' => $company->company_name, 'npwp' => $company->npwp,
            'verification_status' => $company->verification_status,
            'provenance' => CompanyVerificationMonitoringQuery::provenance($company),
        ]));

        return response()->view('government.company-verification-statuses.index', [
            'companies' => $companies, 'filters' => $this->returnFilters($input), 'pageError' => null,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function show(ShowCompanyVerificationStatusRequest $request, CompanyProfile $companyProfile, CompanyVerificationMonitoringQuery $query): Response
    {
        $input = $request->validated();
        $companyProfile->load('inspection.reviewer:id,name');
        $record = $companyProfile->inspection;
        $provenance = CompanyVerificationMonitoringQuery::provenance($companyProfile);
        $companyData = array_merge($companyProfile->only(['id', 'company_name', 'npwp', 'address', 'city', 'province', 'verification_status']), ['provenance' => $provenance]);
        $canViewNotes = $request->user()->can('access-government');
        $inspectionData = $record ? [
            'oss_check_result' => $record->oss_check_result, 'administrative_result' => $record->administrative_result,
            'checked_on' => $record->checked_on->format('d/m/Y'),
            'reviewer_name' => $record->reviewer?->name ?? 'Petugas tidak tersedia',
        ] : null;
        if ($record && $canViewNotes) {
            $inspectionData['notes'] = $record->notes;
        }
        $history = $query->historyQuery($companyProfile)->paginate(
            (int) ($input['history_per_page'] ?? 15), ['*'], 'history_page', (int) ($input['history_page'] ?? 1),
        );
        $labels = ['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected'];
        $timezone = config('app.timezone');
        $history->setCollection($history->getCollection()->map(fn ($event): array => [
            'old_status_label' => $labels[$event->old_status] ?? 'Status tidak dikenal',
            'new_status_label' => $labels[$event->new_status] ?? 'Status tidak dikenal',
            'changed_at_label' => $event->changed_at->setTimezone($timezone)->format('d/m/Y H:i:s').' '.$timezone,
            'actor_label' => $event->actor_name_snapshot, 'reason_label' => $event->reason,
        ]));
        $history->appends(['history_per_page' => $history->perPage()]);

        return response()->view('government.company-verification-statuses.show', [
            'companyData' => $companyData, 'inspectionData' => $inspectionData,
            'canViewInspectionNotes' => $canViewNotes, 'returnFilters' => $this->returnFilters($input),
            'historyAvailable' => true, 'historyEntries' => $history, 'pageError' => null,
            'canInspect' => $companyProfile->verification_status === 'pending'
                && in_array($provenance, ['official', 'uninspected'], true),
        ])->header('Cache-Control', 'no-store, private');
    }
}
