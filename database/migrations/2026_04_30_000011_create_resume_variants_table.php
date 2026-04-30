<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resume_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_lead_id')->constrained()->cascadeOnDelete();
            $table->string('mode');
            $table->longText('generated_text');
            $table->timestamps();

            $table->index(['user_id', 'job_lead_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resume_variants');
    }
};
