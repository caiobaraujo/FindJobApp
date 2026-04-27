<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->json('target_roles')->nullable()->after('target_role');
            $table->json('preferred_locations')->nullable()->after('target_roles');
            $table->json('preferred_work_modes')->nullable()->after('preferred_locations');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'target_roles',
                'preferred_locations',
                'preferred_work_modes',
            ]);
        });
    }
};
