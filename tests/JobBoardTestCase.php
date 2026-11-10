<?php

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

abstract class JobBoardTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->app->environment('testing')) {
            throw new \RuntimeException('Pengujian lowongan dan lamaran memerlukan APP_ENV=testing.');
        }

        config([
            'app.key' => str_repeat('a', 32),
            'cache.default' => 'array',
            'session.driver' => 'array',
            'database.default' => 'job_board_testing',
            'database.connections.job_board_testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        $schema = DB::connection('job_board_testing')->getSchemaBuilder();
        $this->beforeApplicationDestroyed(fn () => DB::purge('job_board_testing'));

        $schema->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default('pencari_kerja');
            $table->rememberToken();
            $table->timestamps();
        });

        $schema->create('company_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->timestamps();
        });

        $schema->create('job_postings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_profile_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('category');
            $table->string('job_type')->default('Penuh Waktu');
            $table->string('location');
            $table->string('education_requirement');
            $table->text('description');
            $table->text('requirements');
            $table->integer('quota')->default(1);
            $table->decimal('salary_around', 15, 2)->nullable();
            $table->string('status', 30)->default('pending_approval');
            $table->date('deadline')->nullable();
            $table->timestamps();
        });

        $schema->create('jobseeker_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('nik', 20)->nullable()->unique();
            $table->boolean('nik_verified')->default(false);
            $table->timestamps();
        });

        $schema->create('job_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('jobseeker_profile_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('terkirim');
            $table->decimal('offered_salary', 15, 2)->nullable();
            $table->text('applicant_notes')->nullable();
            $table->timestamp('applied_at')->useCurrent();
            $table->timestamps();
            $table->unique(['job_posting_id', 'jobseeker_profile_id']);
        });
    }
}
