<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Profil Pencari Kerja (JOBSEEKER_PROFILES)
        Schema::create('jobseeker_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('nik', 20)->nullable()->unique();
            $table->boolean('nik_verified')->default(false);
            $table->date('birth_date')->nullable();
            $table->string('gender', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('province', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->text('bio')->nullable();
            $table->decimal('expected_salary', 15, 2)->nullable();
            $table->string('cv_file_path')->nullable();
            $table->timestamps();
        });

        // 2. Profil Perusahaan (COMPANY_PROFILES)
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('company_name');
            $table->string('npwp', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('province', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('website')->nullable();
            $table->string('company_document_path')->nullable();
            $table->string('verification_status', 30)->default('pending'); // pending, verified, rejected
            $table->text('verification_notes')->nullable();
            $table->timestamps();
        });

        // 3. Pendidikan Pencari Kerja (JOBSEEKER_EDUCATIONS)
        Schema::create('jobseeker_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jobseeker_profile_id')->constrained('jobseeker_profiles')->cascadeOnDelete();
            $table->string('institution_name');
            $table->string('degree');
            $table->string('major');
            $table->integer('start_year');
            $table->integer('end_year')->nullable();
            $table->timestamps();
        });

        // 4. Pengalaman Kerja (JOBSEEKER_EXPERIENCES)
        Schema::create('jobseeker_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jobseeker_profile_id')->constrained('jobseeker_profiles')->cascadeOnDelete();
            $table->string('company_name');
            $table->string('position');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 5. Keahlian Pencari Kerja (JOBSEEKER_SKILLS)
        Schema::create('jobseeker_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jobseeker_profile_id')->constrained('jobseeker_profiles')->cascadeOnDelete();
            $table->string('skill_name');
            $table->string('proficiency_level', 30)->default('Menengah'); // Pemula, Menengah, Mahir
            $table->timestamps();
        });

        // 6. Lowongan Kerja (JOB_POSTINGS)
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_profile_id')->constrained('company_profiles')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('category');
            $table->string('job_type')->default('Penuh Waktu'); // Penuh Waktu, Paruh Waktu, Kontrak, Magang, Remote
            $table->string('location');
            $table->string('education_requirement');
            $table->text('description');
            $table->text('requirements');
            $table->integer('quota')->default(1);
            $table->decimal('salary_around', 15, 2)->nullable();
            $table->string('status', 30)->default('pending_approval'); // draft, pending_approval, published, rejected, closed
            $table->text('rejection_reason')->nullable();
            $table->date('deadline')->nullable();
            $table->timestamps();
        });

        // 7. Lamaran Pekerjaan (JOB_APPLICATIONS)
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained('job_postings')->cascadeOnDelete();
            $table->foreignId('jobseeker_profile_id')->constrained('jobseeker_profiles')->cascadeOnDelete();
            $table->string('status', 30)->default('terkirim'); // terkirim, review, wawancara, offering, diterima, ditolak
            $table->decimal('offered_salary', 15, 2)->nullable();
            $table->text('applicant_notes')->nullable();
            $table->string('resume_snapshot_path')->nullable();
            $table->timestamp('applied_at')->useCurrent();
            $table->timestamps();

            $table->unique(['job_posting_id', 'jobseeker_profile_id']);
        });

        // 8. Pekerja Diterima (HIRED_WORKERS)
        Schema::create('hired_workers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_profile_id')->constrained('company_profiles')->cascadeOnDelete();
            $table->foreignId('jobseeker_profile_id')->constrained('jobseeker_profiles')->cascadeOnDelete();
            $table->foreignId('job_application_id')->nullable()->constrained('job_applications')->nullOnDelete();
            $table->string('position');
            $table->decimal('agreed_salary', 15, 2);
            $table->date('start_date');
            $table->string('employment_status', 30)->default('aktif'); // aktif, cuti, selesai, phk
            $table->string('contract_file')->nullable();
            $table->timestamps();
        });

        // 9. Laporan Gaji (SALARY_REPORTS)
        Schema::create('salary_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hired_worker_id')->constrained('hired_workers')->cascadeOnDelete();
            $table->foreignId('company_profile_id')->constrained('company_profiles')->cascadeOnDelete();
            $table->integer('period_month');
            $table->integer('period_year');
            $table->decimal('reported_amount', 15, 2);
            $table->date('payment_date');
            $table->string('payment_proof_file')->nullable();
            $table->string('worker_confirmation_status', 30)->default('pending'); // pending, sesuai, tidak_sesuai
            $table->text('worker_confirmation_notes')->nullable();
            $table->decimal('minimum_wage_snapshot', 15, 2)->nullable();
            $table->timestamps();
        });

        // 10. Pengaduan Pekerja (WORKER_COMPLAINTS)
        Schema::create('worker_complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jobseeker_profile_id')->constrained('jobseeker_profiles')->cascadeOnDelete();
            $table->foreignId('company_profile_id')->constrained('company_profiles')->cascadeOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ticket_number', 50)->unique();
            $table->string('complaint_type'); // gaji_di_bawah_umr, keterlambatan_gaji, jam_kerja_berlebih, pemutusan_sepihak, pelecehan_diskriminasi, lainnya
            $table->string('status', 30)->default('masuk'); // masuk, diverifikasi, mediasi, tindak_lanjut, selesai, ditolak
            $table->string('title');
            $table->text('description');
            $table->string('evidence_file')->nullable();
            $table->text('handling_notes')->nullable();
            $table->timestamps();
        });

        // 11. Modul Tes Psikologi Standar Pemerintah (PBI 1.3.1, 1.3.2, 3.3.3)
        Schema::create('psychological_tests', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('duration_minutes')->default(30);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('psychological_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('psychological_test_id')->constrained('psychological_tests')->cascadeOnDelete();
            $table->text('question_text');
            $table->string('category', 50)->default('Karakteristik Umum');
            $table->integer('question_order')->default(1);
            $table->string('option_a');
            $table->string('option_b');
            $table->string('option_c');
            $table->string('option_d');
            $table->integer('score_a')->default(1);
            $table->integer('score_b')->default(2);
            $table->integer('score_c')->default(3);
            $table->integer('score_d')->default(4);
            $table->timestamps();
        });

        Schema::create('psychological_test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('psychological_test_id')->constrained('psychological_tests')->cascadeOnDelete();
            $table->foreignId('jobseeker_profile_id')->constrained('jobseeker_profiles')->cascadeOnDelete();
            $table->integer('total_score');
            $table->json('category_scores')->nullable();
            $table->text('assessment_summary')->nullable();
            $table->boolean('passed_standard')->default(true);
            $table->timestamp('completed_at')->useCurrent();
            $table->timestamps();
        });

        // 12. Master Standar Upah Minimum Regional (PBI 3.4.1)
        Schema::create('regional_minimum_wages', function (Blueprint $table) {
            $table->id();
            $table->string('province', 100);
            $table->string('city', 100);
            $table->integer('year');
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regional_minimum_wages');
        Schema::dropIfExists('psychological_test_results');
        Schema::dropIfExists('psychological_questions');
        Schema::dropIfExists('psychological_tests');
        Schema::dropIfExists('worker_complaints');
        Schema::dropIfExists('salary_reports');
        Schema::dropIfExists('hired_workers');
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('job_postings');
        Schema::dropIfExists('jobseeker_skills');
        Schema::dropIfExists('jobseeker_experiences');
        Schema::dropIfExists('jobseeker_educations');
        Schema::dropIfExists('company_profiles');
        Schema::dropIfExists('jobseeker_profiles');
    }
};
