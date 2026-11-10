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
        if (! Schema::hasTable('jobseeker_awards')) {
            Schema::create('jobseeker_awards', function (Blueprint $table) {
                $table->id();
                $table->foreignId('jobseeker_profile_id')->constrained('jobseeker_profiles')->cascadeOnDelete();
                $table->string('title');
                $table->string('issuer');
                $table->integer('year');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobseeker_awards');
    }
};

