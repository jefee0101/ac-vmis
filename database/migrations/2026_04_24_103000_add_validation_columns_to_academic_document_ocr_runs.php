<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_document_ocr_runs', function (Blueprint $table) {
            if (!Schema::hasColumn('academic_document_ocr_runs', 'validation_status')) {
                $table->enum('validation_status', ['pending', 'valid', 'manual_review'])
                    ->default('pending')
                    ->after('mean_confidence');
            }

            if (!Schema::hasColumn('academic_document_ocr_runs', 'validation_summary')) {
                $table->text('validation_summary')->nullable()->after('validation_status');
            }

            if (!Schema::hasColumn('academic_document_ocr_runs', 'validation_flags')) {
                $table->json('validation_flags')->nullable()->after('validation_summary');
            }

            if (!Schema::hasColumn('academic_document_ocr_runs', 'validation_checked_at')) {
                $table->timestamp('validation_checked_at')->nullable()->after('validation_flags');
            }
        });

        Schema::table('academic_document_ocr_runs', function (Blueprint $table) {
            $table->index(
                ['run_status', 'validation_status'],
                'academic_document_ocr_runs_status_validation_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('academic_document_ocr_runs', function (Blueprint $table) {
            $table->dropIndex('academic_document_ocr_runs_status_validation_index');
            $table->dropColumn([
                'validation_status',
                'validation_summary',
                'validation_flags',
                'validation_checked_at',
            ]);
        });
    }
};
