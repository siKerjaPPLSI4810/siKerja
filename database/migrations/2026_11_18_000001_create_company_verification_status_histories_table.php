<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_verification_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_profile_id');
            $table->foreignId('company_inspection_id');
            $table->unsignedBigInteger('sequence');
            $table->enum('event_type', ['status_changed'])->default('status_changed');
            $table->enum('old_status', ['pending', 'verified', 'rejected']);
            $table->enum('new_status', ['pending', 'verified', 'rejected']);
            $table->foreignId('actor_user_id')->nullable();
            $table->string('actor_name_snapshot');
            $table->string('reason_code', 64);
            $table->text('reason');
            $table->dateTime('changed_at');
            $table->timestamp('created_at')->nullable();
            $table->foreign('company_profile_id', 'cvsh_company_fk')->references('id')->on('company_profiles')->restrictOnDelete();
            $table->foreign('company_inspection_id', 'cvsh_inspection_fk')->references('id')->on('company_inspections')->restrictOnDelete();
            $table->foreign('actor_user_id', 'cvsh_actor_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique(['company_profile_id', 'sequence'], 'cvsh_company_sequence_unique');
            $table->index(['company_profile_id', 'changed_at', 'id'], 'cvsh_company_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_verification_status_histories');
    }
};
