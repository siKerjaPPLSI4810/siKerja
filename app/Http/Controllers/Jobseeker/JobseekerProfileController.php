<?php

namespace App\Http\Controllers\Jobseeker;

use App\Http\Controllers\Controller;
use App\Models\JobseekerAward;
use App\Models\JobseekerEducation;
use App\Models\JobseekerExperience;
use App\Models\JobseekerProfile;
use App\Models\JobseekerSkill;
use App\Services\NikVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobseekerProfileController extends Controller
{
    private function getOrCreateProfile(Request $request): JobseekerProfile
    {
        $user = $request->user();

        return JobseekerProfile::firstOrCreate(
            ['user_id' => $user->id],
            ['nik_verified' => false]
        );
    }

    /**
     * Menampilkan halaman pengelolaan profil pencari kerja, hasil verifikasi NIK,
     * dan indikator kelengkapan profil (PBI 1.1.1 & PBI 1.1.2).
     */
    public function index(Request $request, NikVerificationService $nikService): View
    {
        $profile = $this->getOrCreateProfile($request);
        $profile->load(['user', 'educations', 'experiences', 'skills', 'awards']);

        $nikInspection = session('nik_inspection');
        if (! $nikInspection && ! empty($profile->nik)) {
            $nikInspection = $nikService->inspect($profile->nik, $profile->id);
        }

        $completeness = $profile->calculateCompleteness();

        return view('jobseeker.profile.index', [
            'user' => $request->user(),
            'profile' => $profile,
            'completeness' => $completeness,
            'nikInspection' => $nikInspection,
            'provinces' => NikVerificationService::PROVINCE_CODES,
            'activeTab' => $request->query('tab', 'data-diri'),
        ]);
    }

    /**
     * Memvalidasi & memverifikasi identitas NIK dari halaman profil (PBI 1.1.1).
     */
    public function verifyNik(Request $request, NikVerificationService $nikService): RedirectResponse
    {
        $profile = $this->getOrCreateProfile($request);
        $nik = trim((string) $request->input('nik', ''));

        $inspection = $nikService->inspect($nik, $profile->id);

        if (! $inspection['valid']) {
            return redirect()
                ->route('jobseeker.profile.index', ['tab' => 'verifikasi-nik'])
                ->withInput()
                ->with('nik_inspection', $inspection)
                ->withErrors(['nik' => $inspection['errors']]);
        }

        $updateData = [
            'nik' => $nik,
            'nik_verified' => true,
        ];

        if (empty($profile->birth_date) && ! empty($inspection['extracted']['birth_date'])) {
            $updateData['birth_date'] = $inspection['extracted']['birth_date'];
        }
        if (empty($profile->gender) && ! empty($inspection['extracted']['gender'])) {
            $updateData['gender'] = $inspection['extracted']['gender'];
        }
        if (empty($profile->province) && ! empty($inspection['extracted']['province_name'])) {
            $updateData['province'] = $inspection['extracted']['province_name'];
        }

        $profile->update($updateData);

        return redirect()
            ->route('jobseeker.profile.index', ['tab' => 'verifikasi-nik'])
            ->with('nik_inspection', $inspection)
            ->with('status', 'Identitas NIK berhasil diperiksa dan terverifikasi!');
    }

    /**
     * Menyimpan dan memperbarui Data Diri pencari kerja (PBI 1.1.2).
     */
    public function updatePersonal(Request $request): RedirectResponse
    {
        $profile = $this->getOrCreateProfile($request);
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'min:9', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'birth_date' => ['required', 'date'],
            'gender' => ['required', 'string', 'in:Laki-laki,Perempuan'],
            'province' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:1000'],
            'bio' => ['required', 'string', 'max:2000'],
            'expected_salary' => ['nullable', 'numeric', 'min:0'],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'phone.required' => 'Nomor telepon wajib diisi.',
            'birth_date.required' => 'Tanggal lahir wajib diisi.',
            'gender.required' => 'Jenis kelamin wajib dipilih.',
            'province.required' => 'Provinsi domisili wajib diisi.',
            'city.required' => 'Kabupaten/Kota domisili wajib diisi.',
            'address.required' => 'Alamat lengkap wajib diisi.',
            'bio.required' => 'Ringkasan diri (bio profesional) wajib diisi.',
        ]);

        $user->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
        ]);

        $profile->update([
            'birth_date' => $validated['birth_date'],
            'gender' => $validated['gender'],
            'province' => $validated['province'],
            'city' => $validated['city'],
            'address' => $validated['address'],
            'bio' => $validated['bio'],
            'expected_salary' => $validated['expected_salary'] ?? null,
        ]);

        return redirect()
            ->route('jobseeker.profile.index', ['tab' => 'data-diri'])
            ->with('status', 'Data diri berhasil disimpan dan diperbarui.');
    }

    /**
     * Menambahkan riwayat pendidikan (PBI 1.1.2).
     */
    public function storeEducation(Request $request): RedirectResponse
    {
        $profile = $this->getOrCreateProfile($request);

        $validated = $request->validate([
            'institution_name' => ['required', 'string', 'max:255'],
            'degree' => ['required', 'string', 'max:100'],
            'major' => ['required', 'string', 'max:255'],
            'start_year' => ['required', 'integer', 'min:1950', 'max:2100'],
            'end_year' => ['nullable', 'integer', 'min:1950', 'max:2100', 'gte:start_year'],
        ], [
            'institution_name.required' => 'Nama institusi pendidikan wajib diisi.',
            'degree.required' => 'Jenjang pendidikan wajib dipilih/diisi.',
            'major.required' => 'Jurusan / program studi wajib diisi.',
            'start_year.required' => 'Tahun mulai pendidikan wajib diisi.',
            'end_year.gte' => 'Tahun lulus tidak boleh lebih kecil dari tahun mulai.',
        ]);

        $profile->educations()->create($validated);

        return redirect()
            ->route('jobseeker.profile.index', ['tab' => 'pendidikan'])
            ->with('status', 'Data pendidikan berhasil ditambahkan.');
    }

    /**
     * Memperbarui riwayat pendidikan (PBI 1.1.2).
     */
    public function updateEducation(Request $request, JobseekerEducation $education): RedirectResponse
    {
        $profile = $this->getOrCreateProfile($request);
        abort_unless($education->jobseeker_profile_id === $profile->id, 403);

        $validated = $request->validate([
            'institution_name' => ['required', 'string', 'max:255'],
            'degree' => ['required', 'string', 'max:100'],
            'major' => ['required', 'string', 'max:255'],
            'start_year' => ['required', 'integer', 'min:1950', 'max:2100'],
            'end_year' => ['nullable', 'integer', 'min:1950', 'max:2100', 'gte:start_year'],
        ]);

        $education->update($validated);

        return redirect()
            ->route('jobseeker.profile.index', ['tab' => 'pendidikan'])
            ->with('status', 'Data pendidikan berhasil diperbarui.');
    }

    /**
     * Menghapus riwayat pendidikan (PBI 1.1.2).
     */
    public function destroyEducation(Request $request, JobseekerEducation $education): RedirectResponse
    {
        $profile = $this->getOrCreateProfile($request);
        abort_unless($education->jobseeker_profile_id === $profile->id, 403);

        $education->delete();

        return redirect()
            ->route('jobseeker.profile.index', ['tab' => 'pendidikan'])
            ->with('status', 'Data pendidikan berhasil dihapus.');
    }

    /**
     * Menambahkan kompetensi / keahlian (PBI 1.1.2).
     */
    public function storeSkill(Request $request): RedirectResponse
    {
        $profile = $this->getOrCreateProfile($request);

        $validated = $request->validate([
            'skill_name' => ['required', 'string', 'max:255'],
            'proficiency_level' => ['required', 'string', 'in:Pemula,Menengah,Mahir,Ahli'],
        ], [
            'skill_name.required' => 'Nama kompetensi / keahlian wajib diisi.',
            'proficiency_level.required' => 'Tingkat kemahiran wajib dipilih.',
        ]);

        $profile->skills()->create($validated);

        return redirect()
            ->route('jobseeker.profile.index', ['tab' => 'kompetensi'])
            ->with('status', 'Data kompetensi berhasil ditambahkan.');
    }

    /**
     * Memperbarui kompetensi / keahlian (PBI 1.1.2).
     */
    public function updateSkill(Request $request, JobseekerSkill $skill): RedirectResponse
    {
        $profile = $this->getOrCreateProfile($request);
        abort_unless($skill->jobseeker_profile_id === $profile->id, 403);

        $validated = $request->validate([
            'skill_name' => ['required', 'string', 'max:255'],
            'proficiency_level' => ['required', 'string', 'in:Pemula,Menengah,Mahir,Ahli'],
        ]);

        $skill->update($validated);

        return redirect()
            ->route('jobseeker.profile.index', ['tab' => 'kompetensi'])
            ->with('status', 'Data kompetensi berhasil diperbarui.');
    }

    /**
     * Menghapus kompetensi / keahlian (PBI 1.1.2).
     */
    public function destroySkill(Request $request, JobseekerSkill $skill): RedirectResponse
    {
        $profile = $this->getOrCreateProfile($request);
        abort_unless($skill->jobseeker_profile_id === $profile->id, 403);

        $skill->delete();

        return redirect()
            ->route('jobseeker.profile.index', ['tab' => 'kompetensi'])
            ->with('status', 'Data kompetensi berhasil dihapus.');
    }

    /**
     * Menambahkan pengalaman kerja (PBI 1.1.2).
     */
    public function storeExperience(Request $request): RedirectResponse
    {
        $profile = $this->getOrCreateProfile($request);

        $isCurrent = $request->boolean('is_current');

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => [$isCurrent ? 'nullable' : 'required', 'nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [
            'company_name.required' => 'Nama perusahaan / instansi wajib diisi.',
            'position.required' => 'Posisi / jabatan wajib diisi.',
            'start_date.required' => 'Tanggal mulai bekerja wajib diisi.',
            'end_date.required' => 'Tanggal selesai wajib diisi jika sudah tidak bekerja di posisi ini.',
            'end_date.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
        ]);

        $profile->experiences()->create([
            'company_name' => $validated['company_name'],
            'position' => $validated['position'],
            'start_date' => $validated['start_date'],
            'end_date' => $isCurrent ? null : ($validated['end_date'] ?? null),
            'is_current' => $isCurrent,
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()
            ->route('jobseeker.profile.index', ['tab' => 'pengalaman'])
            ->with('status', 'Data pengalaman kerja berhasil ditambahkan.');
    }

    /**
     * Memperbarui pengalaman kerja (PBI 1.1.2).
     */
    public function updateExperience(Request $request, JobseekerExperience $experience): RedirectResponse
    {
        $profile = $this->getOrCreateProfile($request);
        abort_unless($experience->jobseeker_profile_id === $profile->id, 403);

        $isCurrent = $request->boolean('is_current');

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => [$isCurrent ? 'nullable' : 'required', 'nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $experience->update([
            'company_name' => $validated['company_name'],
            'position' => $validated['position'],
            'start_date' => $validated['start_date'],
            'end_date' => $isCurrent ? null : ($validated['end_date'] ?? null),
            'is_current' => $isCurrent,
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()
            ->route('jobseeker.profile.index', ['tab' => 'pengalaman'])
            ->with('status', 'Data pengalaman kerja berhasil diperbarui.');
    }

    /**
     * Menghapus pengalaman kerja (PBI 1.1.2).
     */
    public function destroyExperience(Request $request, JobseekerExperience $experience): RedirectResponse
    {
        $profile = $this->getOrCreateProfile($request);
        abort_unless($experience->jobseeker_profile_id === $profile->id, 403);

        $experience->delete();

        return redirect()
            ->route('jobseeker.profile.index', ['tab' => 'pengalaman'])
            ->with('status', 'Data pengalaman kerja berhasil dihapus.');
    }

    /**
     * Menambahkan penghargaan / prestasi (PBI 1.1.2).
     */
    public function storeAward(Request $request): RedirectResponse
    {
        $profile = $this->getOrCreateProfile($request);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'issuer' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:1950', 'max:2100'],
            'description' => ['nullable', 'string', 'max:1500'],
        ], [
            'title.required' => 'Nama penghargaan / sertifikasi wajib diisi.',
            'issuer.required' => 'Penyelenggara / pemberi penghargaan wajib diisi.',
            'year.required' => 'Tahun penghargaan wajib diisi.',
        ]);

        $profile->awards()->create($validated);

        return redirect()
            ->route('jobseeker.profile.index', ['tab' => 'penghargaan'])
            ->with('status', 'Data penghargaan berhasil ditambahkan.');
    }

    /**
     * Memperbarui penghargaan / prestasi (PBI 1.1.2).
     */
    public function updateAward(Request $request, JobseekerAward $award): RedirectResponse
    {
        $profile = $this->getOrCreateProfile($request);
        abort_unless($award->jobseeker_profile_id === $profile->id, 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'issuer' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:1950', 'max:2100'],
            'description' => ['nullable', 'string', 'max:1500'],
        ]);

        $award->update($validated);

        return redirect()
            ->route('jobseeker.profile.index', ['tab' => 'penghargaan'])
            ->with('status', 'Data penghargaan berhasil diperbarui.');
    }

    /**
     * Menghapus penghargaan / prestasi (PBI 1.1.2).
     */
    public function destroyAward(Request $request, JobseekerAward $award): RedirectResponse
    {
        $profile = $this->getOrCreateProfile($request);
        abort_unless($award->jobseeker_profile_id === $profile->id, 403);

        $award->delete();

        return redirect()
            ->route('jobseeker.profile.index', ['tab' => 'penghargaan'])
            ->with('status', 'Data penghargaan berhasil dihapus.');
    }
}

