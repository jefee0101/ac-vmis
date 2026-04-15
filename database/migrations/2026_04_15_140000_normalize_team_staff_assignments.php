<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_staff_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('coaches')->cascadeOnDelete();
            $table->enum('role', ['head', 'assistant']);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['team_id', 'role', 'ends_at'], 'team_staff_assignments_team_role_active_idx');
            $table->index(['coach_id', 'ends_at'], 'team_staff_assignments_coach_active_idx');
        });

        $now = now();
        $teams = DB::table('teams')
            ->select('id', 'coach_id', 'assistant_coach_id', 'created_at', 'updated_at')
            ->get();

        foreach ($teams as $team) {
            if (!empty($team->coach_id)) {
                DB::table('team_staff_assignments')->insert([
                    'team_id' => $team->id,
                    'coach_id' => $team->coach_id,
                    'role' => 'head',
                    'starts_at' => $team->created_at ?? $now,
                    'ends_at' => null,
                    'created_by' => null,
                    'created_at' => $team->created_at ?? $now,
                    'updated_at' => $team->updated_at ?? $now,
                ]);
            }

            if (!empty($team->assistant_coach_id)) {
                DB::table('team_staff_assignments')->insert([
                    'team_id' => $team->id,
                    'coach_id' => $team->assistant_coach_id,
                    'role' => 'assistant',
                    'starts_at' => $team->created_at ?? $now,
                    'ends_at' => null,
                    'created_by' => null,
                    'created_at' => $team->created_at ?? $now,
                    'updated_at' => $team->updated_at ?? $now,
                ]);
            }
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['coach_id']);
            $table->dropForeign(['assistant_coach_id']);
            $table->dropColumn(['coach_id', 'assistant_coach_id']);
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->unsignedBigInteger('coach_id')->nullable()->after('year');
            $table->unsignedBigInteger('assistant_coach_id')->nullable()->after('coach_id');
            $table->foreign('coach_id')->references('id')->on('coaches')->nullOnDelete();
            $table->foreign('assistant_coach_id')->references('id')->on('coaches')->nullOnDelete();
        });

        $headAssignments = DB::table('team_staff_assignments')
            ->where('role', 'head')
            ->whereNull('ends_at')
            ->orderBy('id')
            ->get()
            ->keyBy('team_id');

        $assistantAssignments = DB::table('team_staff_assignments')
            ->where('role', 'assistant')
            ->whereNull('ends_at')
            ->orderBy('id')
            ->get()
            ->keyBy('team_id');

        foreach (DB::table('teams')->select('id')->get() as $team) {
            DB::table('teams')
                ->where('id', $team->id)
                ->update([
                    'coach_id' => $headAssignments[$team->id]->coach_id ?? null,
                    'assistant_coach_id' => $assistantAssignments[$team->id]->coach_id ?? null,
                ]);
        }

        Schema::dropIfExists('team_staff_assignments');
    }
};
