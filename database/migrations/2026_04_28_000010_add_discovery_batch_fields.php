<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_leads', function (Blueprint $table): void {
            $table->string('discovery_batch_id')->nullable()->after('source_host');
            $table->index(['user_id', 'discovery_batch_id']);
        });

        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->string('last_discovery_batch_id')->nullable()->after('last_discovered_new_count');
        });
    }

    public function down(): void
    {
        Schema::table('job_leads', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'discovery_batch_id']);
            $table->dropColumn('discovery_batch_id');
        });

        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->dropColumn('last_discovery_batch_id');
        });
    }
};
