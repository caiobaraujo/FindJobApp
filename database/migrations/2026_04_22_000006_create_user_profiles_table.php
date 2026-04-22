<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('target_role')->nullable();
            $table->text('professional_summary')->nullable();
            $table->json('core_skills')->nullable();
            $table->text('work_experience_text')->nullable();
            $table->text('education_text')->nullable();
            $table->text('certifications_text')->nullable();
            $table->text('languages_text')->nullable();
            $table->longText('base_resume_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
