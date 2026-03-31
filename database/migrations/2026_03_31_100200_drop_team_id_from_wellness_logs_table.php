<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wellness_logs', function (Blueprint $table) {
            if (Schema::hasColumn('wellness_logs', 'team_id')) {
                $driver = DB::getDriverName();
                if ($driver === 'sqlite') {
                    DB::statement('DROP INDEX IF EXISTS wellness_logs_team_id_schedule_id_index');
                    DB::statement('DROP INDEX IF EXISTS wellness_logs_team_id_index');
                }
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
