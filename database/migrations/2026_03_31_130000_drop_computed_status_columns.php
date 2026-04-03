<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('academic_periods', 'status')) {
            try {
                Schema::table('academic_periods', function (Blueprint $table) {
                    $table->dropIndex(['status']);
                });
            } catch (\Throwable $e) {
                // SQLite may already have rebuilt or skipped this index.
            }

            Schema::table('academic_periods', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }

        if (Schema::hasColumn('athlete_health_clearances', 'clearance_status')) {
            try {
                Schema::table('athlete_health_clearances', function (Blueprint $table) {
                    $table->dropIndex(['clearance_status', 'valid_until']);
                });
            } catch (\Throwable $e) {
                // Best-effort cleanup before dropping the column.
            }

            Schema::table('athlete_health_clearances', function (Blueprint $table) {
                $table->dropColumn('clearance_status');
            });
        }

        if (Schema::hasColumn('academic_eligibility_evaluations', 'status')) {
            try {
                Schema::table('academic_eligibility_evaluations', function (Blueprint $table) {
                    $table->dropIndex('academic_eligibility_status_evaluated_index');
                });
            } catch (\Throwable $e) {
                // Best-effort cleanup before dropping the column.
            }

            Schema::table('academic_eligibility_evaluations', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }

        if (Schema::hasColumn('students', 'education_level')) {
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('education_level');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('academic_periods', 'status')) {
            Schema::table('academic_periods', function (Blueprint $table) {
                $table->string('status', 16)->default('draft')->after('ends_on');
                $table->index('status');
            });
        }

        if (!Schema::hasColumn('athlete_health_clearances', 'clearance_status')) {
            Schema::table('athlete_health_clearances', function (Blueprint $table) {
                $table->enum('clearance_status', [
                    'fit',
                    'fit_with_restrictions',
                    'not_fit',
                    'expired',
                ])->default('fit')->after('restrictions');
                $table->index(['clearance_status', 'valid_until']);
            });
        }

        if (!Schema::hasColumn('academic_eligibility_evaluations', 'status')) {
            Schema::table('academic_eligibility_evaluations', function (Blueprint $table) {
                $table->enum('status', ['eligible', 'probation', 'ineligible'])->after('gpa');
                $table->index(['status', 'evaluated_at'], 'academic_eligibility_status_evaluated_index');
            });
        }

        if (!Schema::hasColumn('students', 'education_level')) {
            Schema::table('students', function (Blueprint $table) {
                $table->enum('education_level', ['Senior High', 'College'])
                    ->nullable()
                    ->after('course_or_strand');
            });
        }
    }
};
