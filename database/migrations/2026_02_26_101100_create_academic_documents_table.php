<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            $table->enum('document_type', ['tor', 'grade_report', 'other']);

            $table->foreignId('academic_period_id')
                ->nullable()
                ->constrained('academic_periods')
                ->nullOnDelete();

            $table->string('file_path');

            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('uploaded_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'document_type'], 'academic_documents_student_type_index');
            $table->index(['academic_period_id', 'document_type'], 'academic_documents_period_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_documents');
    }
};

