<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('academic_evaluation_condition_results');
        Schema::dropIfExists('academic_period_rule_conditions');
        Schema::dropIfExists('academic_period_eligibility_rules');
        Schema::dropIfExists('subject_group_members');
        Schema::dropIfExists('subject_groups');

        if (Schema::hasTable('academic_document_parsed_subjects') && Schema::hasColumn('academic_document_parsed_subjects', 'matched_subject_id')) {
            Schema::table('academic_document_parsed_subjects', function (Blueprint $table) {
                $table->dropForeign('academic_doc_parsed_subjects_subject_fk');
                $table->dropIndex('academic_document_subjects_match_grade_index');
                $table->dropColumn('matched_subject_id');
            });
        }

        Schema::dropIfExists('subjects');
        Schema::dropIfExists('academic_evaluation_documents');
    }

    public function down(): void
    {
        if (!Schema::hasTable('subjects')) {
            Schema::create('subjects', function (Blueprint $table) {
                $table->id();
                $table->string('subject_code', 50)->unique();
                $table->string('subject_name', 120);
                $table->string('academic_level', 50)->nullable();
                $table->timestamps();
                $table->unique(['subject_name', 'academic_level'], 'subjects_name_level_unique');
            });
        }

        if (!Schema::hasTable('subject_groups')) {
            Schema::create('subject_groups', function (Blueprint $table) {
                $table->id();
                $table->string('group_code', 50)->unique();
                $table->string('group_name', 120)->unique();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('subject_group_members')) {
            Schema::create('subject_group_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subject_group_id')->constrained('subject_groups')->cascadeOnDelete();
                $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['subject_group_id', 'subject_id'], 'subject_group_members_group_subject_unique');
            });
        }

        if (Schema::hasTable('academic_document_parsed_subjects') && !Schema::hasColumn('academic_document_parsed_subjects', 'matched_subject_id')) {
            Schema::table('academic_document_parsed_subjects', function (Blueprint $table) {
                $table->foreignId('matched_subject_id')->nullable()->after('subject_name_raw')->constrained('subjects', 'id', 'academic_doc_parsed_subjects_subject_fk')->nullOnDelete();
                $table->index(['matched_subject_id', 'grade_value'], 'academic_document_subjects_match_grade_index');
            });
        }

        if (!Schema::hasTable('academic_period_eligibility_rules')) {
            Schema::create('academic_period_eligibility_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academic_period_id')->constrained('academic_periods')->cascadeOnDelete();
                $table->string('rule_code', 50);
                $table->string('rule_name', 120);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['academic_period_id', 'rule_code'], 'academic_period_rules_period_code_unique');
            });
        }

        if (!Schema::hasTable('academic_period_rule_conditions')) {
            Schema::create('academic_period_rule_conditions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academic_period_eligibility_rule_id')
                    ->constrained('academic_period_eligibility_rules')
                    ->cascadeOnDelete();
                $table->enum('condition_type', [
                    'gwa_max',
                    'gwa_min',
                    'subject_grade_min',
                    'subject_grade_max',
                    'failed_subjects_max',
                    'incomplete_subjects_max',
                    'required_subject_pass',
                ]);
                $table->enum('operator', ['<', '<=', '=', '>=', '>']);
                $table->decimal('threshold_value', 6, 2);
                $table->enum('applies_to_scope', ['overall', 'subject', 'subject_group'])->default('overall');
                $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
                $table->foreignId('subject_group_id')->nullable()->constrained('subject_groups')->nullOnDelete();
                $table->unsignedSmallInteger('sort_order')->default(1);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('academic_evaluation_condition_results')) {
            Schema::create('academic_evaluation_condition_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academic_eligibility_evaluation_id')
                    ->constrained('academic_eligibility_evaluations')
                    ->cascadeOnDelete();
                $table->foreignId('academic_period_rule_condition_id')
                    ->constrained('academic_period_rule_conditions')
                    ->cascadeOnDelete();
                $table->decimal('actual_value', 6, 2)->nullable();
                $table->boolean('passed');
                $table->string('failure_reason', 255)->nullable();
                $table->timestamps();
                $table->unique(
                    ['academic_eligibility_evaluation_id', 'academic_period_rule_condition_id'],
                    'academic_eval_condition_results_eval_condition_unique'
                );
            });
        }

        if (!Schema::hasTable('academic_evaluation_documents')) {
            Schema::create('academic_evaluation_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('evaluation_id')->constrained('academic_eligibility_evaluations')->cascadeOnDelete();
                $table->foreignId('document_id')->constrained('academic_documents')->cascadeOnDelete();
                $table->timestamps();
                $table->unique('evaluation_id', 'academic_evaluation_documents_evaluation_unique');
                $table->unique(['evaluation_id', 'document_id'], 'academic_evaluation_documents_eval_doc_unique');
            });
        }
    }
};
