<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_eligibility_evaluations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            $table->foreignId('academic_period_id')
                ->nullable()
                ->constrained('academic_periods')
                ->nullOnDelete();

            $table->foreignId('document_id')
                ->constrained('academic_documents')
                ->cascadeOnDelete();

            $table->decimal('gpa', 4, 2)->nullable();
            $table->enum('status', ['eligible', 'probation', 'ineligible']);

            $table->foreignId('evaluated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('evaluated_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'academic_period_id'], 'academic_eligibility_student_period_unique');
            $table->index(['status', 'evaluated_at'], 'academic_eligibility_status_evaluated_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_eligibility_evaluations');
    }
};

