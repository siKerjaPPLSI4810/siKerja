<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_profile_id')->unique()->constrained('company_profiles')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('method', ['manual_oss_rba'])->default('manual_oss_rba');
            $table->enum('oss_check_result', ['matched', 'mismatched', 'unconfirmed'])->default('unconfirmed');
            $table->enum('administrative_result', ['complete', 'incomplete', 'unconfirmed'])->default('unconfirmed');
            $table->enum('decision', ['pending', 'verified', 'rejected'])->default('pending');
            $table->text('notes');
            $table->date('checked_on');
            $table->json('company_snapshot');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_inspections');
    }
};
