<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_attendances', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('schedule_id')
                ->constrained('team_schedules')
                ->cascadeOnDelete();

            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            // Attendance status
            $table->enum('status', [
                'present',
                'absent',
                'late',
                'excused'
            ])->default('present');

            $table->string('verification_method')->default('manual_override');

            // Who recorded attendance
            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('recorded_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('override_reason')->nullable();

            $table->timestamps();

            // Prevent duplicate attendance per schedule
            $table->unique(['schedule_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_attendances');
    }
};
