<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\JobPosting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyJobController extends Controller
{
    
     // ini isian pilihan standar Bidang/Kategori, Tipe Pekerjaan, dan Pendidikan Minimum
    public const CATEGORIES = [
        'Teknologi Informasi' => 'Teknologi Informasi & Telekomunikasi',
        'Keuangan & Perbankan' => 'Keuangan & Perbankan',
        'Manufaktur & Pabrik' => 'Manufaktur & Produksi',
        'Logistik & Transportasi' => 'Logistik, Gudang & Transportasi',
        'Kesehatan & Farmasi' => 'Kesehatan & Farmasi',
        'Pendidikan & Pelatihan' => 'Pendidikan & Pelatihan',
        'Konstruksi & Properti' => 'Konstruksi & Properti',
        'Pelayanan & Ritel' => 'Pelayanan, Perhotelan & Ritel',
        'Pertanian & Perkebunan' => 'Pertanian & Kehutanan',
        'Lainnya' => 'Bidang Lainnya',
    ];

    public const JOB_TYPES = [
        'Penuh Waktu' => 'Penuh Waktu (Full-time)',
        'Paruh Waktu' => 'Paruh Waktu (Part-time)',
        'Kontrak' => 'Kontrak (PKWT)',
        'Magang' => 'Magang (Internship)',
        'Remote' => 'Jarak Jauh (Remote)',
    ];

    public const EDUCATIONS = [
        'SMA/SMK' => 'SMA / SMK Sederajat',
        'D3' => 'Diploma III (D3)',
        'D4/S1' => 'Diploma IV / Sarjana (S1)',
        'S2' => 'Magister (S2)',
        'Semua Jenjang' => 'Semua Jenjang Pendidikan',
    ];


    protected function ensureCompanyUser(Request $request): void
    {
        if ($request->user()->role !== 'perusahaan') {
            abort(403, 'Akses terbatas hanya untuk akun perusahaan.');
        }
    }

    
    protected function getCompanyProfile(Request $request): CompanyProfile
    {
        $user = $request->user();

        return $user->companyProfile()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'company_name' => $user->name,
                'verification_status' => 'pending',
                'verification_notes' => 'Menunggu verifikasi administratif oleh Kemnaker.',
            ]
        );
    }

    // aturan pengisian form loker
    protected function validateJobRequest(Request $request): array
    {
        return $request->validate([
            'title' => ['required','string','min:3','max:255','regex:/^[a-zA-Z\s]+$/', ],
            'category' => ['required', 'string', 'max:100'],
            'job_type' => ['required', 'string', 'max:50'],
            'location' => ['required','string','min:3','max:150','regex:/^[a-zA-Z\s\.\-]+$/',],
            'education_requirement' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string', 'min:20'],
            'requirements' => ['required', 'string', 'min:20'],
            'quota' => ['required', 'integer', 'min:1'],
            'salary_around' => ['required', 'numeric', 'min:1000000'],
            'deadline' => ['required', 'date', 'after:today'],
            'is_draft' => ['nullable', 'boolean'],
        ], 
        [
            //kumpulan pesan error untuk pengisian form loker
            'title.required' => 'Judul posisi lowongan wajib diisi.',
            'title.min' => 'Judul posisi lowongan minimal 3 huruf.',
            'title.regex' => 'Judul posisi lowongan hanya boleh berisi huruf dan spasi, tidak boleh mengandung angka atau simbol.',
            'category.required' => 'Silakan pilih bidang / kategori pekerjaan dari daftar yang tersedia.',
            'job_type.required' => 'Silakan pilih tipe pekerjaan dari daftar yang tersedia.',
            'location.required' => 'Lokasi penempatan kerja wajib diisi.',
            'location.min' => 'Nama lokasi penempatan minimal 3 karakter.',
            'location.regex' => 'Lokasi penempatan hanya boleh berisi huruf nama kota/wilayah, tidak boleh mengandung angka.',
            'education_requirement.required' => 'Silakan tentukan syarat pendidikan minimum.',
            'description.required' => 'Deskripsi tugas & tanggung jawab pekerjaan wajib diisi.',
            'description.min' => 'Deskripsi pekerjaan minimal 20 karakter agar informatif bagi calon pelamar.',
            'requirements.required' => 'Kualifikasi & persyaratan khusus pelamar wajib diisi.',
            'requirements.min' => 'Kualifikasi pekerjaan minimal 20 karakter agar persyaratan seleksi jelas.',
            'quota.required' => 'Kuota penerimaan wajib diisi.',
            'quota.min' => 'Kuota penerimaan minimal 1 orang.',
            'salary_around.required' => 'Estimasi gaji bulanan wajib diisi.',
            'salary_around.min' => 'Estimasi gaji minimal Rp 1.000.000.',
            'deadline.required' => 'Batas akhir penerimaan (deadline) wajib ditentukan.',
            'deadline.date' => 'Format tanggal batas akhir penerimaan tidak valid.',
            'deadline.after' => 'Batas akhir penerimaan lowongan harus berupa tanggal setelah hari ini.',
        ]);
    }

    // PBI 2.2.2 — Mengelola dan Memantau Status Lowongan Kerja
    public function index(Request $request): View
    {
        $this->ensureCompanyUser($request);
        $profile = $this->getCompanyProfile($request);

        $statusFilter = $request->query('status');

        $query = JobPosting::withCount('applications')
            ->where('company_profile_id', $profile->id);

        if ($statusFilter && in_array($statusFilter, ['draft', 'pending_approval', 'published', 'rejected', 'closed'], true)) {
            $query->where('status', $statusFilter);
        }

        $jobs = $query->latest()->orderByDesc('id')->paginate(10)->withQueryString();

        // ini ringkasan jumlah per status untuk pemantauan perusahaan
        $summary = [
            'total' => JobPosting::where('company_profile_id', $profile->id)->count(),
            'draft' => JobPosting::where('company_profile_id', $profile->id)->where('status', 'draft')->count(),
            'pending_approval' => JobPosting::where('company_profile_id', $profile->id)->where('status', 'pending_approval')->count(),
            'published' => JobPosting::where('company_profile_id', $profile->id)->where('status', 'published')->count(),
            'rejected' => JobPosting::where('company_profile_id', $profile->id)->where('status', 'rejected')->count(),
            'closed' => JobPosting::where('company_profile_id', $profile->id)->where('status', 'closed')->count(),
        ];

        return view('company.jobs.index', [
            'user' => $request->user(),
            'profile' => $profile,
            'jobs' => $jobs,
            'summary' => $summary,
            'statusFilter' => $statusFilter,
        ]);
    }

    // PBI 2.2.1 — Membuat dan Mengajukan Lowongan Kerja
    public function create(Request $request): View
    {
        $this->ensureCompanyUser($request);
        $profile = $this->getCompanyProfile($request);

        return view('company.jobs.create', [
            'user' => $request->user(),
            'profile' => $profile,
            'categories' => self::CATEGORIES,
            'jobTypes' => self::JOB_TYPES,
            'educations' => self::EDUCATIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCompanyUser($request);
        $profile = $this->getCompanyProfile($request);

        $validated = $this->validateJobRequest($request);

        // ini digunakan ketika tombol "Simpan Draf" ditekan, sehingga status awal lowongan menjadi 'draft'
        $status = $request->boolean('is_draft') ? 'draft' : 'pending_approval';

        JobPosting::create([
            'company_profile_id' => $profile->id,
            'title' => $validated['title'],
            'category' => $validated['category'],
            'job_type' => $validated['job_type'],
            'location' => $validated['location'],
            'education_requirement' => $validated['education_requirement'],
            'description' => $validated['description'],
            'requirements' => $validated['requirements'],
            'quota' => $validated['quota'],
            'salary_around' => $validated['salary_around'],
            'deadline' => $validated['deadline'],
            'status' => $status,
        ]);

        $message = $status === 'pending_approval'
            ? 'Lowongan kerja berhasil diajukan ke Pengawas Kemnaker untuk verifikasi kelayakan sebelum dipublikasikan.'
            : 'Draf lowongan kerja berhasil disimpan.';

        return redirect()
            ->route('company.jobs.index')
            ->with('status', $message);
    }

    // PBI 2.2.3 — Mengubah dan Menutup Lowongan Kerja
    
    public function edit(Request $request, int $id): View
    {
        $this->ensureCompanyUser($request);
        $profile = $this->getCompanyProfile($request);

        $job = JobPosting::where('company_profile_id', $profile->id)->findOrFail($id);

        return view('company.jobs.edit', [
            'user' => $request->user(),
            'profile' => $profile,
            'job' => $job,
            'categories' => self::CATEGORIES,
            'jobTypes' => self::JOB_TYPES,
            'educations' => self::EDUCATIONS,
        ]);
    }

    
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->ensureCompanyUser($request);
        $profile = $this->getCompanyProfile($request);

        $job = JobPosting::where('company_profile_id', $profile->id)->findOrFail($id);

        $validated = $this->validateJobRequest($request);
        unset($validated['is_draft']);

        
        if ($job->status === 'published' || $request->boolean('resubmit')) {
            $validated['status'] = 'pending_approval';
            $validated['rejection_reason'] = null;
            $msg = 'Informasi lowongan berhasil diperbarui dan diajukan ulang ke Pengawas Kemnaker untuk peninjauan.';
        } else {
            $msg = 'Informasi lowongan kerja berhasil diperbarui.';
        }

        $job->update($validated);

        return redirect()
            ->route('company.jobs.index')
            ->with('status', $msg);
    }

    
    public function toggleStatus(Request $request, int $id): RedirectResponse
    {
        $this->ensureCompanyUser($request);
        $profile = $this->getCompanyProfile($request);

        $job = JobPosting::where('company_profile_id', $profile->id)->findOrFail($id);

        if ($job->status === 'published') {
            $job->update(['status' => 'closed']);
            $msg = 'Lowongan resmi ditutup dan tidak lagi menerima lamaran baru.';
        } else {
            $job->update([
                'status' => 'pending_approval',
                'rejection_reason' => null,
            ]);
            $msg = 'Lowongan berhasil diajukan untuk peninjauan Pengawas Kemnaker.';
        }

        return redirect()
            ->route('company.jobs.index')
            ->with('status', $msg);
    }
}
