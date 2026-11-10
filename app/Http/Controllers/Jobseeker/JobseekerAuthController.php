<?php

namespace App\Http\Controllers\Jobseeker;

use App\Http\Controllers\Controller;
use App\Models\JobseekerProfile;
use App\Models\User;
use App\Services\NikVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class JobseekerAuthController extends Controller
{
    public function showRegister(Request $request, NikVerificationService $nikService): View
    {
        $inspection = session('nik_inspection');

        if (! $inspection && $request->filled('nik')) {
            $inspection = $nikService->inspect((string) $request->query('nik'));
        }

        return view('jobseeker.auth.register', [
            'inspection' => $inspection,
            'provinces' => NikVerificationService::PROVINCE_CODES,
        ]);
    }

    /**
     * Memeriksa validitas dan keunikan NIK sebelum atau saat pendaftaran (PBI 1.1.1).
     */
    public function checkNik(Request $request, NikVerificationService $nikService): JsonResponse|RedirectResponse
    {
        $nik = (string) $request->input('nik', '');
        $inspection = $nikService->inspect($nik);

        if ($request->expectsJson()) {
            return response()->json($inspection, $inspection['valid'] ? 200 : 422);
        }

        return back()
            ->withInput()
            ->with('nik_inspection', $inspection)
            ->with(
                $inspection['valid'] ? 'status' : 'error',
                $inspection['message']
            );
    }

    /**
     * Mendaftarkan akun pencari kerja dan memverifikasi identitas dengan NIK (PBI 1.1.1).
     */
    public function register(Request $request, NikVerificationService $nikService): RedirectResponse
    {
        $nik = trim((string) $request->input('nik', ''));
        $inspection = $nikService->inspect($nik);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'min:9', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'nik' => ['required', 'string', 'digits:16', 'unique:jobseeker_profiles,nik'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'in:Laki-laki,Perempuan'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'email.unique' => 'Alamat email sudah terdaftar. Gunakan email lain atau silakan masuk.',
            'phone.required' => 'Nomor telepon wajib diisi.',
            'phone.min' => 'Nomor telepon minimal 9 karakter.',
            'phone.regex' => 'Nomor telepon hanya boleh berisi angka dan tanda telepon yang valid.',
            'nik.required' => 'NIK wajib diisi untuk memverifikasi identitas pencari kerja.',
            'nik.digits' => 'NIK tidak valid: wajib terdiri dari tepat 16 digit angka.',
            'nik.unique' => 'NIK tidak valid karena sudah terdaftar (ganda). Satu NIK hanya untuk satu akun.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        if (! $inspection['valid']) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->with('nik_inspection', $inspection)
                ->withErrors(['nik' => $inspection['errors']]);
        }

        $user = DB::transaction(function () use ($validated, $inspection) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => $validated['password'],
                'role' => 'pencari_kerja',
                'is_active' => true,
            ]);

            JobseekerProfile::create([
                'user_id' => $user->id,
                'nik' => $validated['nik'],
                'nik_verified' => true,
                'birth_date' => $validated['birth_date'] ?? $inspection['extracted']['birth_date'],
                'gender' => $validated['gender'] ?? $inspection['extracted']['gender'],
                'province' => $validated['province'] ?? ($inspection['extracted']['province_name'] ?? null),
                'city' => $validated['city'] ?? null,
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('jobseeker.profile.index')
            ->with('nik_inspection', $inspection)
            ->with('status', 'Pendaftaran berhasil! Identitas Anda dengan NIK '.$validated['nik'].' telah terverifikasi.');
    }

    public function showLogin(): View
    {
        return view('jobseeker.auth.login');
    }

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

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()
                ->intended(route('jobseeker.profile.index'))
                ->with('status', 'Selamat datang kembali di portal Pencari Kerja SIkERJA.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors([
                'email' => 'Email atau kata sandi yang Anda masukkan tidak sesuai.',
            ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('jobseeker.login')
            ->with('status', 'Anda telah keluar dari akun pencari kerja.');
    }
}

