<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wellness_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            $table->foreignId('team_id')
                ->nullable()
                ->constrained('teams')
                ->nullOnDelete();

            $table->foreignId('schedule_id')
                ->nullable()
                ->constrained('team_schedules')
                ->nullOnDelete();

            $table->foreignId('logged_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->date('log_date');

            $table->boolean('injury_observed')->default(false);
            $table->text('injury_notes')->nullable();

            $table->unsignedTinyInteger('fatigue_level');
            $table->enum('performance_condition', ['excellent', 'good', 'fair', 'poor']);

            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'log_date']);
            $table->index(['team_id', 'schedule_id']);
            $table->index('logged_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wellness_logs');
    }
};

