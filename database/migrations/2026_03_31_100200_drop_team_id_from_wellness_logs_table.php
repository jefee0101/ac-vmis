<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wellness_logs', function (Blueprint $table) {
            if (Schema::hasColumn('wellness_logs', 'team_id')) {
                $table->dropConstrainedForeignId('team_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wellness_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('wellness_logs', 'team_id')) {
                $table->foreignId('team_id')
                    ->nullable()
                    ->after('student_id')
                    ->constrained('teams')
                    ->nullOnDelete();
            }
        });
    }
};
