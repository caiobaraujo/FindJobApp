<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_leads', function (Blueprint $table): void {
            $table->string('company_name')->nullable()->change();
            $table->string('job_title')->nullable()->change();
            $table->string('source_url')->nullable()->change();
            $table->string('source_type')->nullable()->after('source_name');
            $table->string('source_platform')->nullable()->after('source_type');
            $table->string('source_post_url')->nullable()->after('source_platform');
            $table->string('source_author')->nullable()->after('source_post_url');
            $table->text('source_context_text')->nullable()->after('source_author');
            $table->index(['user_id', 'source_post_url']);
        });
    }

    public function down(): void
    {
        Schema::table('job_leads', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'source_post_url']);
            $table->dropColumn([
                'source_type',
                'source_platform',
                'source_post_url',
                'source_author',
                'source_context_text',
            ]);
            $table->string('source_url')->nullable(false)->change();
            $table->string('job_title')->nullable(false)->change();
            $table->string('company_name')->nullable(false)->change();
        });
    }
};
