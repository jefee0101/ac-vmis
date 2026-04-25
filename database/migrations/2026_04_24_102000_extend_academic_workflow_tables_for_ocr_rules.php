<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('academic_documents', 'review_status')) {
                $table->enum('review_status', ['pending', 'auto_processed', 'needs_review', 'reviewed'])
                    ->default('pending')
                    ->after('notes');
            }

            if (!Schema::hasColumn('academic_documents', 'reviewed_by')) {
                $table->foreignId('reviewed_by')
                    ->nullable()
                    ->after('review_status')
                    ->constrained('users', 'id', 'academic_documents_reviewed_by_fk')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('academic_documents', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
        });

        Schema::table('academic_documents', function (Blueprint $table) {
            $table->index(['academic_period_id', 'review_status'], 'academic_documents_period_review_status_index');
        });

        Schema::table('academic_eligibility_evaluations', function (Blueprint $table) {
            if (!Schema::hasColumn('academic_eligibility_evaluations', 'academic_document_ocr_run_id')) {
                $table->foreignId('academic_document_ocr_run_id')
                    ->nullable()
                    ->after('document_id')
                    ->constrained('academic_document_ocr_runs', 'id', 'academic_eligibility_ocr_run_fk')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('academic_eligibility_evaluations', 'evaluation_source')) {
                $table->enum('evaluation_source', ['manual', 'rule_based', 'rule_based_reviewed'])
                    ->default('manual')
                    ->after('gpa');
            }

            if (!Schema::hasColumn('academic_eligibility_evaluations', 'final_status')) {
                $table->enum('final_status', ['eligible', 'pending_review', 'ineligible'])
                    ->nullable()
                    ->after('evaluation_source');
            }

            if (!Schema::hasColumn('academic_eligibility_evaluations', 'review_required')) {
                $table->boolean('review_required')->default(false)->after('final_status');
            }
        });

        Schema::table('academic_eligibility_evaluations', function (Blueprint $table) {
            $table->index(
                ['academic_period_id', 'final_status'],
                'academic_eligibility_period_final_status_index'
            );
            $table->index(
                ['evaluation_source', 'review_required'],
                'academic_eligibility_source_review_index'
            );
        });

    }

    public function down(): void
    {
        Schema::table('academic_eligibility_evaluations', function (Blueprint $table) {
            $table->dropIndex('academic_eligibility_period_final_status_index');
            $table->dropIndex('academic_eligibility_source_review_index');
            $table->dropConstrainedForeignId('academic_document_ocr_run_id');
            $table->dropColumn(['evaluation_source', 'final_status', 'review_required']);
        });

        Schema::table('academic_documents', function (Blueprint $table) {
            $table->dropIndex('academic_documents_period_review_status_index');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review_status', 'reviewed_at']);
        });
    }
};
