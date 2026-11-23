<?php

namespace App\Http\Controllers\Government;

use App\Http\Controllers\Controller;
use App\Http\Requests\Government\StoreCompanyInspectionRequest;
use App\Models\CompanyProfile;
use App\Services\CompanyInspectionService;
use App\Services\CompanyInspectionQuery;
use App\Services\CompanyInspectionDocument;
use App\Http\Requests\Government\ListCompanyInspectionsRequest;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

class CompanyInspectionController extends Controller
{
    public function index(ListCompanyInspectionsRequest $request, CompanyInspectionQuery $query, CompanyInspectionDocument $documents): Response|RedirectResponse
    {
        $input = $request->validated();
        $companies = $query->paginate($input);
        if ($companies->currentPage() > $companies->lastPage()) {
            return redirect()->route('government.company-inspections.index', [
                'q' => $input['q'] ?? '', 'per_page' => $companies->perPage(), 'page' => $companies->lastPage(),
            ]);
        }
        foreach ($companies as $company) {
            $company->setAttribute('document_available', $documents->path($company) !== null);
        }

        return response()->view('government.company-inspections.index', [
            'companies' => $companies, 'filters' => ['q' => trim($input['q'] ?? '')],
        ])->header('Cache-Control', 'no-store, private');
    }

    private function detailData(CompanyProfile $company, CompanyInspectionService $service, CompanyInspectionDocument $documents): array
    {
        $company->load(['user:id,name,email,phone', 'inspection.reviewer:id,name']);
        $record = $company->inspection;
        $canSubmit = request()->user()->can('access-government')
            && in_array($company->verification_status, ['pending', 'verified', 'rejected'], true)
            && (! $record || $record->decision === 'pending');

        return [
            'company' => $company,
            'inspectionRecord' => $record ? [
                'oss_check_result' => $record->oss_check_result,
                'administrative_result' => $record->administrative_result,
                'checked_on' => $record->checked_on->format('d/m/Y'),
                'decision' => $record->decision, 'notes' => $record->notes,
                'reviewer_name' => $record->reviewer?->name ?? 'Petugas tidak tersedia',
                'recorded_at' => $record->updated_at->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            ] : null,
            'document' => $documents->presentation($company),
            'dataVersion' => $service->dataVersion($company),
            'canSubmit' => $canSubmit, 'canRecordInspection' => $canSubmit,
            'ossPortalUrl' => $service->ossPortalUrl(),
        ];
    }

    public function show(CompanyProfile $companyProfile, CompanyInspectionService $service, CompanyInspectionDocument $documents): Response
    {
        return response()->view('government.company-inspections.show', $this->detailData($companyProfile, $service, $documents))
            ->header('Cache-Control', 'no-store, private');
    }

    public function document(CompanyProfile $companyProfile, CompanyInspectionDocument $documents): StreamedResponse
    {
        return $documents->download($companyProfile);
    }

    public function store(StoreCompanyInspectionRequest $request, CompanyProfile $companyProfile, CompanyInspectionService $service, CompanyInspectionDocument $documents): JsonResponse|RedirectResponse|Response
    {
        try {
            $inspection = $service->record($companyProfile, $request->user(), $request->validated());
        } catch (ConflictHttpException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            return response()->view('government.company-inspections.show', array_merge(
                $this->detailData($companyProfile->fresh(), $service, $documents),
                ['conflictMessage' => $exception->getMessage(), 'canSubmit' => false, 'canRecordInspection' => false],
            ), 409)->header('Cache-Control', 'no-store, private');
        }

        if ($request->expectsJson() || ! Route::has('government.company-inspections.show')) {
            return response()->json([
                'message' => 'Hasil pemeriksaan berhasil disimpan.',
                'data' => ['id' => $inspection->id, 'decision' => $inspection->decision],
            ])->header('Cache-Control', 'no-store, private');
        }

        return redirect()->route('government.company-inspections.show', $companyProfile)
            ->with('status', 'Hasil pemeriksaan berhasil disimpan.');
    }
}
