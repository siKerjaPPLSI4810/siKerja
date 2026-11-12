<?php

use App\Http\Controllers\Company\CompanyAuthController;
use App\Http\Controllers\Company\CompanyProfileController;
use App\Http\Controllers\JobApplicationController;
use App\Http\Controllers\JobApplicationStatusController;
use App\Http\Controllers\Jobseeker\JobseekerAuthController;
use App\Http\Controllers\Jobseeker\JobseekerCvController;
use App\Http\Controllers\Jobseeker\JobseekerProfileController;
use App\Http\Controllers\PublicController;
use App\Services\NikVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request, NikVerificationService $nikService) {
    if (Auth::check()) {
        if (Auth::user()->role === 'perusahaan') {
            return redirect()->route('company.profile.index');
        }

        return redirect()->route('jobseeker.profile.index');
    }

    return app(JobseekerAuthController::class)->showRegister($request, $nikService);
})->name('home');

Route::get('/lowongan', [PublicController::class, 'jobs'])->name('jobs.index');
Route::get('/lowongan/{id}', [PublicController::class, 'jobDetail'])->whereNumber('id')->name('jobs.show');

// Alias standar autentikasi Laravel
Route::get('/login', [JobseekerAuthController::class, 'showLogin'])->middleware('guest')->name('login');
Route::get('/register', [JobseekerAuthController::class, 'showRegister'])->middleware('guest')->name('register');

// Epic 1 - Feature 1.1: Pendaftaran, Verifikasi NIK, Profil, & CV Otomatis Pencari Kerja
Route::prefix('pencari-kerja')->name('jobseeker.')->group(function () {
    Route::middleware('guest')->group(function () {
        // PBI 1.1.1 - Mendaftar dan Memverifikasi Identitas Pencari Kerja (KF-01)
        Route::get('/daftar', [JobseekerAuthController::class, 'showRegister'])->name('register');
        Route::post('/daftar', [JobseekerAuthController::class, 'register'])->name('register.submit');
        Route::post('/cek-nik', [JobseekerAuthController::class, 'checkNik'])->name('nik.check');

        Route::get('/masuk', [JobseekerAuthController::class, 'showLogin'])->name('login');
        Route::post('/masuk', [JobseekerAuthController::class, 'login'])->name('login.submit');
    });

    Route::middleware(['auth', 'can:access-job-applications'])->group(function () {
        Route::post('/lowongan/{id}/lamar', [JobApplicationController::class, 'applyJob'])->whereNumber('id')->name('apply');
        Route::get('/lamaran', [JobApplicationStatusController::class, 'myApplications'])->name('applications');
    });

    Route::middleware('auth')->group(function () {
        Route::post('/keluar', [JobseekerAuthController::class, 'logout'])->name('logout');

        // PBI 1.1.1 & PBI 1.1.2 - Mengelola Profil Pencari Kerja (KF-02)
        Route::get('/profil', [JobseekerProfileController::class, 'index'])->name('profile.index');
        Route::post('/profil/verifikasi-nik', [JobseekerProfileController::class, 'verifyNik'])->name('profile.nik.verify');
        Route::put('/profil/data-diri', [JobseekerProfileController::class, 'updatePersonal'])->name('profile.personal.update');

        // Pendidikan
        Route::post('/profil/pendidikan', [JobseekerProfileController::class, 'storeEducation'])->name('profile.education.store');
        Route::put('/profil/pendidikan/{education}', [JobseekerProfileController::class, 'updateEducation'])->name('profile.education.update');
        Route::delete('/profil/pendidikan/{education}', [JobseekerProfileController::class, 'destroyEducation'])->name('profile.education.destroy');

        // Kompetensi / Keahlian
        Route::post('/profil/kompetensi', [JobseekerProfileController::class, 'storeSkill'])->name('profile.skill.store');
        Route::put('/profil/kompetensi/{skill}', [JobseekerProfileController::class, 'updateSkill'])->name('profile.skill.update');
        Route::delete('/profil/kompetensi/{skill}', [JobseekerProfileController::class, 'destroySkill'])->name('profile.skill.destroy');

        // Pengalaman Kerja
        Route::post('/profil/pengalaman', [JobseekerProfileController::class, 'storeExperience'])->name('profile.experience.store');
        Route::put('/profil/pengalaman/{experience}', [JobseekerProfileController::class, 'updateExperience'])->name('profile.experience.update');
        Route::delete('/profil/pengalaman/{experience}', [JobseekerProfileController::class, 'destroyExperience'])->name('profile.experience.destroy');

        // Penghargaan
        Route::post('/profil/penghargaan', [JobseekerProfileController::class, 'storeAward'])->name('profile.award.store');
        Route::put('/profil/penghargaan/{award}', [JobseekerProfileController::class, 'updateAward'])->name('profile.award.update');
        Route::delete('/profil/penghargaan/{award}', [JobseekerProfileController::class, 'destroyAward'])->name('profile.award.destroy');

        // PBI 1.1.3 - Membuat CV Otomatis dari Profil Pencari Kerja (KF-02)
        Route::get('/cv', [JobseekerCvController::class, 'show'])->name('cv.show');
        Route::get('/cv/cetak', [JobseekerCvController::class, 'print'])->name('cv.print');
        Route::get('/cv/unduh', [JobseekerCvController::class, 'download'])->name('cv.download');
    });
});

// Epic 2 - Feature 2.1: Pendaftaran dan Profil Perusahaan
Route::prefix('perusahaan')->name('company.')->group(function () {
    Route::middleware('guest')->group(function () {
        // PBI 2.1.1 - Mendaftar dan Memvalidasi Identitas Perusahaan
        Route::get('/daftar', [CompanyAuthController::class, 'showRegister'])->name('register');
        Route::post('/daftar', [CompanyAuthController::class, 'register'])->name('register.submit');
        Route::post('/cek-identitas', [CompanyAuthController::class, 'checkIdentity'])->name('identity.check');

        Route::get('/masuk', [CompanyAuthController::class, 'showLogin'])->name('login');
        Route::post('/masuk', [CompanyAuthController::class, 'login'])->name('login.submit');
    });

    Route::middleware('auth')->group(function () {
        Route::post('/keluar', [CompanyAuthController::class, 'logout'])->name('logout');

        // PBI 2.1.2 - Mengelola Profil Perusahaan & PBI 2.1.1 Hasil Pemeriksaan
        Route::get('/profil', [CompanyProfileController::class, 'index'])->name('profile.index');
        Route::get('/profil/edit', [CompanyProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profil', [CompanyProfileController::class, 'update'])->name('profile.update');
        Route::post('/profil/verifikasi-identitas', [CompanyProfileController::class, 'verifyIdentity'])->name('profile.identity.verify');
        Route::get('/profil/dokumen', [CompanyProfileController::class, 'downloadDocument'])->name('profile.document.download');
    });
});
