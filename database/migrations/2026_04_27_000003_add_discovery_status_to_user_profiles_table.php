<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->timestamp('last_discovered_at')->nullable()->after('auto_discover_jobs');
            $table->unsignedInteger('last_discovered_new_count')->nullable()->after('last_discovered_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'last_discovered_at',
                'last_discovered_new_count',
            ]);
        });
    }
};
