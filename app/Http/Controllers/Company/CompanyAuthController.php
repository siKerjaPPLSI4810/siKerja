<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\User;
use App\Services\CompanyVerificationService;
use App\Services\NikVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CompanyAuthController extends Controller
{
    /**
     * Menampilkan halaman pendaftaran akun perusahaan dan pengiriman identitas (PBI 2.1.1).
     */
    public function showRegister(Request $request, CompanyVerificationService $verificationService): View
    {
        $inspection = session('identity_inspection');

        if (! $inspection && ($request->filled('npwp') || $request->filled('company_name'))) {
            $inspection = $verificationService->inspect(
                (string) $request->query('company_name', ''),
                (string) $request->query('npwp', ''),
                null,
                null,
                $request->query('scenario_override')
            );
        }

        return view('company.auth.register', [
            'inspection' => $inspection,
            'provinces' => NikVerificationService::PROVINCE_CODES,
        ]);
    }

    /**
     * Memeriksa validitas identitas dan NPWP perusahaan sebelum pendaftaran (PBI 2.1.1).
     */
    public function checkIdentity(Request $request, CompanyVerificationService $verificationService): JsonResponse|RedirectResponse
    {
        $companyName = (string) $request->input('company_name', '');
        $npwp = (string) $request->input('npwp', '');
        $document = $request->file('company_document');
        $scenarioOverride = $request->input('scenario_override');

        $inspection = $verificationService->inspect(
            $companyName,
            $npwp,
            $document,
            null,
            $scenarioOverride
        );

        if ($request->expectsJson()) {
            return response()->json($inspection, $inspection['status'] === 'rejected' ? 422 : 200);
        }

        return back()
            ->withInput($request->except(['password', 'password_confirmation', 'company_document']))
            ->with('identity_inspection', $inspection)
            ->with(
                $inspection['status'] === 'rejected' ? 'error' : 'status',
                $inspection['message']
            );
    }

    /**
     * Mendaftarkan akun perusahaan dan memvalidasi identitas legal (PBI 2.1.1).
     */
    public function register(Request $request, CompanyVerificationService $verificationService): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'min:9', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'npwp' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'string', 'max:255'],
            'company_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'scenario_override' => ['nullable', 'string', 'in:valid,invalid,unconfirmed'],
        ], [
            'company_name.required' => 'Nama perusahaan wajib diisi.',
            'name.required' => 'Nama penanggung jawab wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email perusahaan sudah terdaftar. Silakan gunakan email lain atau masuk.',
            'phone.required' => 'Nomor telepon perusahaan wajib diisi.',
            'phone.min' => 'Nomor telepon minimal 9 karakter.',
            'phone.regex' => 'Format nomor telepon hanya boleh berisi angka dan tanda telepon yang valid.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'npwp.required' => 'NPWP perusahaan wajib diisi untuk verifikasi identitas.',
            'company_document.mimes' => 'Format berkas dokumen hanya boleh berupa PDF, JPG, JPEG, atau PNG.',
            'company_document.max' => 'Ukuran berkas dokumen tidak boleh lebih dari 5MB.',
        ]);

        // Jalankan inspeksi identitas perusahaan
        $inspection = $verificationService->inspect(
            $validated['company_name'],
            $validated['npwp'],
            $request->file('company_document'),
            null,
            $validated['scenario_override'] ?? null
        );

        if ($inspection['status'] === 'rejected') {
            return back()
                ->withInput($request->except(['password', 'password_confirmation', 'company_document']))
                ->with('identity_inspection', $inspection)
                ->withErrors([
                    'npwp' => $inspection['errors'][0] ?? 'Identitas perusahaan ditolak karena tidak memenuhi ketentuan.',
                ]);
        }

        $documentPath = null;
        if ($request->hasFile('company_document')) {
            $documentPath = $request->file('company_document')->store('company_documents', 'public');
        }

        $user = DB::transaction(function () use ($validated, $documentPath, $inspection, $verificationService) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => $validated['password'],
                'role' => 'perusahaan',
                'is_active' => true,
            ]);

            CompanyProfile::create([
                'user_id' => $user->id,
                'company_name' => $validated['company_name'],
                'npwp' => $verificationService->formatNpwp($validated['npwp']),
                'address' => $validated['address'] ?? null,
                'province' => $validated['province'] ?? null,
                'city' => $validated['city'] ?? null,
                'website' => $validated['website'] ?? null,
                'company_document_path' => $documentPath,
                'verification_status' => $inspection['status'], // 'verified' atau 'pending'
                'verification_notes' => $inspection['message'],
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('company.profile.index')
            ->with('identity_inspection', $inspection)
            ->with('status', 'Pendaftaran akun perusahaan berhasil! Identitas perusahaan telah dicatat.');
    }

    /**
     * Menampilkan halaman masuk akun perusahaan.
     */
    public function showLogin(): View
    {
        return view('company.auth.login');
    }

    /**
     * Masuk ke portal perusahaan.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if ($user && $user->role !== 'perusahaan') {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Akun ini bukan akun perusahaan. Silakan masuk melalui portal pencari kerja.',
                ]);
        }

        if (Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password'], 'role' => 'perusahaan'], $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()
                ->intended(route('company.profile.index'))
                ->with('status', 'Selamat datang kembali di Portal Perusahaan SIkERJA.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors([
                'email' => 'Email atau kata sandi yang Anda masukkan tidak sesuai.',
            ]);
    }

    /**
     * Keluar dari portal perusahaan.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('company.login')
            ->with('status', 'Anda telah berhasil keluar dari portal perusahaan.');
    }
}
