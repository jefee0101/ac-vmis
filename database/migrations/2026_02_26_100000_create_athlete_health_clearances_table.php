<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('athlete_health_clearances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            $table->date('clearance_date');
            $table->date('valid_until')->nullable();
            $table->string('physician_name');

            $table->text('conditions')->nullable();
            $table->text('allergies')->nullable();
            $table->text('restrictions')->nullable();

            $table->enum('clearance_status', [
                'fit',
                'fit_with_restrictions',
                'not_fit',
                'expired',
            ])->default('fit');

            $table->string('certificate_path')->nullable();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'clearance_date']);
            $table->index(['clearance_status', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('athlete_health_clearances');
    }
};

