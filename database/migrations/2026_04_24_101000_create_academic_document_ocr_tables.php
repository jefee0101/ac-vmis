<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('academic_document_ocr_runs')) {
            Schema::create('academic_document_ocr_runs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academic_document_id')
                    ->constrained('academic_documents', 'id', 'academic_doc_ocr_runs_doc_fk')
                    ->cascadeOnDelete();
                $table->string('ocr_engine', 50);
                $table->string('ocr_engine_version', 50)->nullable();
                $table->enum('run_status', ['pending', 'processed', 'failed', 'needs_review'])->default('pending');
                $table->longText('raw_text')->nullable();
                $table->decimal('mean_confidence', 5, 2)->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index(['academic_document_id', 'run_status'], 'academic_document_ocr_runs_doc_status_index');
                $table->index(['processed_at', 'run_status'], 'academic_document_ocr_runs_processed_status_index');
            });
        }

        if (!Schema::hasTable('academic_document_parsed_summaries')) {
            Schema::create('academic_document_parsed_summaries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academic_document_ocr_run_id')
                    ->constrained('academic_document_ocr_runs', 'id', 'academic_doc_parsed_summaries_run_fk')
                    ->cascadeOnDelete();
                $table->decimal('gwa', 4, 2)->nullable();
                $table->decimal('total_units', 6, 2)->nullable();
                $table->enum('parser_status', ['pending', 'parsed', 'failed', 'needs_review'])->default('pending');
                $table->decimal('parser_confidence', 5, 2)->nullable();
                $table->timestamps();

                $table->unique('academic_document_ocr_run_id', 'academic_document_parsed_summaries_run_unique');
                $table->index(['parser_status', 'parser_confidence'], 'academic_document_summaries_status_confidence_index');
            });
        }

        if (!Schema::hasTable('academic_document_parsed_subjects')) {
            Schema::create('academic_document_parsed_subjects', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academic_document_ocr_run_id')
                    ->constrained('academic_document_ocr_runs', 'id', 'academic_doc_parsed_subjects_run_fk')
                    ->cascadeOnDelete();
                $table->unsignedInteger('row_number');
                $table->string('subject_code_raw', 50)->nullable();
                $table->string('subject_name_raw', 150);
                $table->decimal('units', 6, 2)->nullable();
                $table->decimal('grade_value', 4, 2)->nullable();
                $table->string('remarks_raw', 50)->nullable();
                $table->decimal('row_confidence', 5, 2)->nullable();
                $table->boolean('is_flagged')->default(false);
                $table->timestamps();

                $table->unique(['academic_document_ocr_run_id', 'row_number'], 'academic_document_subjects_run_row_unique');
                $table->index(['academic_document_ocr_run_id', 'is_flagged'], 'academic_document_subjects_run_flagged_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_document_parsed_subjects');
        Schema::dropIfExists('academic_document_parsed_summaries');
        Schema::dropIfExists('academic_document_ocr_runs');
    }
};
