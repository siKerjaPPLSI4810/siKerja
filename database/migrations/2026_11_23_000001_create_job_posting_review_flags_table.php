<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_posting_review_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->unique()->constrained('job_postings')->cascadeOnDelete();
            $table->text('reason');
            $table->foreignId('flagged_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('flagged_at');
            $table->foreignId('cleared_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cleared_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_posting_review_flags');
    }
};
