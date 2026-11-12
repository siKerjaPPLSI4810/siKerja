<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Services\CompanyVerificationService;
use App\Services\NikVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyProfileController extends Controller
{
    /**
     * Memastikan hanya user dengan role perusahaan yang dapat mengakses.
     */
    protected function ensureCompanyUser(Request $request): void
    {
        if ($request->user()->role !== 'perusahaan') {
            abort(403, 'Akses terbatas hanya untuk akun perusahaan.');
        }
    }

    /**
     * Menampilkan profil perusahaan & hasil pemeriksaan identitas (PBI 2.1.2 & PBI 2.1.1).
     */
    public function index(Request $request, CompanyVerificationService $verificationService): View
    {
        $this->ensureCompanyUser($request);

        $user = $request->user();
        $profile = $user->companyProfile()->firstOrCreate([
            'user_id' => $user->id,
        ], [
            'company_name' => $user->name,
            'verification_status' => 'pending',
            'verification_notes' => 'Profil perusahaan baru dibuat. Silakan lengkapi identitas perusahaan.',
        ]);

        $inspection = session('identity_inspection');
        if (! $inspection && $profile->npwp) {
            $inspection = $verificationService->inspect(
                $profile->company_name,
                $profile->npwp,
                $profile->company_document_path,
                $profile->id,
                $profile->verification_status === 'verified'
                    ? 'valid'
                    : ($profile->verification_status === 'rejected' ? 'invalid' : 'unconfirmed')
            );
        }

        return view('company.profile.index', [
            'user' => $user,
            'profile' => $profile,
            'inspection' => $inspection,
        ]);
    }

    /**
     * Menampilkan form ubah profil perusahaan (PBI 2.1.2).
     */
    public function edit(Request $request): View
    {
        $this->ensureCompanyUser($request);

        $user = $request->user();
        $profile = $user->companyProfile()->firstOrCreate([
            'user_id' => $user->id,
        ], [
            'company_name' => $user->name,
            'verification_status' => 'pending',
        ]);

        return view('company.profile.edit', [
            'user' => $user,
            'profile' => $profile,
            'provinces' => NikVerificationService::PROVINCE_CODES,
        ]);
    }

    /**
     * Menyimpan perubahan profil perusahaan dengan validasi data wajib dan format (PBI 2.1.2).
     */
    public function update(Request $request, CompanyVerificationService $verificationService): RedirectResponse
    {
        $this->ensureCompanyUser($request);

        $user = $request->user();
        $profile = $user->companyProfile()->firstOrCreate(['user_id' => $user->id], [
            'company_name' => $user->name,
        ]);

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['required', 'string', 'min:9', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'website' => ['nullable', 'string', 'max:255', 'regex:/^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/i'],
            'address' => ['nullable', 'string', 'max:1000'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'npwp' => ['nullable', 'string', 'max:50'],
            'company_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'company_name.required' => 'Nama perusahaan wajib diisi.',
            'name.required' => 'Nama penanggung jawab wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'email.unique' => 'Alamat email sudah digunakan oleh pengguna lain.',
            'phone.required' => 'Nomor telepon wajib diisi.',
            'phone.regex' => 'Format nomor telepon tidak valid. Gunakan format angka dan tanda telepon yang benar.',
            'phone.min' => 'Nomor telepon minimal 9 karakter.',
            'website.regex' => 'Format alamat website tidak valid (contoh: https://perusahaan.co.id atau www.perusahaan.com).',
            'company_document.mimes' => 'Format berkas dokumen hanya boleh berupa PDF, JPG, JPEG, atau PNG.',
            'company_document.max' => 'Ukuran berkas dokumen tidak boleh lebih dari 5MB.',
        ]);

        DB::transaction(function () use ($user, $profile, $validated, $request, $verificationService) {
            // Update User
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
            ]);

            // Update Dokumen jika ada upload baru
            $documentPath = $profile->company_document_path;
            if ($request->hasFile('company_document')) {
                $documentPath = $request->file('company_document')->store('company_documents', 'public');
            }

            // Format website jika tidak ada protokol
            $website = $validated['website'] ?? null;
            if ($website && ! preg_match('/^https?:\/\//i', $website)) {
                $website = 'https://'.$website;
            }

            $formattedNpwp = ! empty($validated['npwp'])
                ? $verificationService->formatNpwp($validated['npwp'])
                : $profile->npwp;

            $profile->update([
                'company_name' => $validated['company_name'],
                'address' => $validated['address'] ?? null,
                'province' => $validated['province'] ?? null,
                'city' => $validated['city'] ?? null,
                'website' => $website,
                'npwp' => $formattedNpwp,
                'company_document_path' => $documentPath,
            ]);
        });

        return redirect()
            ->route('company.profile.index')
            ->with('status', 'Profil perusahaan berhasil diperbarui dan perubahan tersimpan.');
    }

    /**
     * Memeriksa ulang / memvalidasi identitas perusahaan untuk skenario valid, tidak valid, dan belum dapat dipastikan (PBI 2.1.1).
     */
    public function verifyIdentity(Request $request, CompanyVerificationService $verificationService): RedirectResponse
    {
        $this->ensureCompanyUser($request);

        $user = $request->user();
        $profile = $user->companyProfile()->firstOrFail();

        $scenarioOverride = $request->input('scenario_override'); // 'valid', 'invalid', 'unconfirmed'
        $customNpwp = $request->input('npwp') ?: $profile->npwp;

        $inspection = $verificationService->inspect(
            $profile->company_name,
            $customNpwp,
            $profile->company_document_path,
            $profile->id,
            $scenarioOverride
        );

        $profile->update([
            'npwp' => $customNpwp ? $verificationService->formatNpwp($customNpwp) : $profile->npwp,
            'verification_status' => $inspection['status'],
            'verification_notes' => $inspection['message'],
        ]);

        return redirect()
            ->route('company.profile.index')
            ->with('identity_inspection', $inspection)
            ->with(
                $inspection['status'] === 'rejected' ? 'error' : 'status',
                'Hasil pemeriksaan identitas diperbarui: '.$inspection['status_label']
            );
    }

    /**
     * Mengunduh berkas dokumen legalitas perusahaan.
     */
    public function downloadDocument(Request $request): StreamedResponse|RedirectResponse
    {
        $this->ensureCompanyUser($request);

        $profile = $request->user()->companyProfile;

        if (! $profile || ! $profile->company_document_path || ! Storage::disk('public')->exists($profile->company_document_path)) {
            return back()->with('error', 'Dokumen legalitas perusahaan belum diunggah atau tidak ditemukan.');
        }

        return Storage::disk('public')->download($profile->company_document_path);
    }
}
