<?php

use App\Http\Controllers\Jobseeker\JobseekerAuthController;
use App\Http\Controllers\Jobseeker\JobseekerCvController;
use App\Http\Controllers\Jobseeker\JobseekerProfileController;
use App\Services\NikVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request, NikVerificationService $nikService) {
    if (Auth::check()) {
        return redirect()->route('jobseeker.profile.index');
    }

    return app(JobseekerAuthController::class)->showRegister($request, $nikService);
})->name('home');

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
