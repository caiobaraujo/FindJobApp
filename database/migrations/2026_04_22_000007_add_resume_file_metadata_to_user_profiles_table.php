<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->string('resume_file_path')->nullable()->after('base_resume_text');
            $table->string('resume_file_name')->nullable()->after('resume_file_path');
            $table->string('resume_file_mime')->nullable()->after('resume_file_name');
            $table->unsignedBigInteger('resume_file_size')->nullable()->after('resume_file_mime');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'resume_file_path',
                'resume_file_name',
                'resume_file_mime',
                'resume_file_size',
            ]);
        });
    }
};
