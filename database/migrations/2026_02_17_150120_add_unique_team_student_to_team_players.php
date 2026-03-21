<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_players', function (Blueprint $table) {
            $table->unique(['team_id', 'student_id'], 'team_student_unique');
        });
    }

    public function down(): void
    {
        // MySQL may reuse the composite unique index for the team_id foreign key.
        // Ensure a dedicated team_id index exists before dropping the unique index.
        try {
            DB::statement('CREATE INDEX team_players_team_id_idx ON team_players (team_id)');
        } catch (\Throwable $e) {
            // index may already exist
        }

        try {
            Schema::table('team_players', function (Blueprint $table) {
                $table->dropUnique('team_student_unique');
            });
        } catch (\Throwable $e) {
            // unique may already be dropped / absent
        }
    }
};
