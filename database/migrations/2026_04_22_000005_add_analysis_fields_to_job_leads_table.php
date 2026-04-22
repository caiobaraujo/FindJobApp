<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_leads', function (Blueprint $table): void {
            $table->longText('description_text')->nullable();
            $table->json('extracted_keywords')->nullable();
            $table->json('ats_hints')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('job_leads', function (Blueprint $table): void {
            $table->dropColumn([
                'description_text',
                'extracted_keywords',
                'ats_hints',
            ]);
        });
    }
};
