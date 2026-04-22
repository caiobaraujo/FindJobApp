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
        Schema::create('job_leads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->string('job_title');
            $table->string('source_name')->nullable();
            $table->string('source_url');
            $table->string('location')->nullable();
            $table->string('work_mode')->nullable();
            $table->string('salary_range')->nullable();
            $table->text('description_excerpt')->nullable();
            $table->unsignedInteger('relevance_score')->nullable();
            $table->string('lead_status');
            $table->date('discovered_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'lead_status']);
            $table->index(['user_id', 'company_name']);
            $table->index(['user_id', 'job_title']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_leads');
    }
};
