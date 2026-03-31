<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_periods', function (Blueprint $table) {
            if (Schema::hasColumn('academic_periods', 'locked_by')) {
                $table->dropConstrainedForeignId('locked_by');
            }

            if (Schema::hasColumn('academic_periods', 'is_submission_open')) {
                $table->dropColumn('is_submission_open');
            }
            if (Schema::hasColumn('academic_periods', 'is_locked')) {
                $table->dropColumn('is_locked');
            }
            if (Schema::hasColumn('academic_periods', 'locked_at')) {
                $table->dropColumn('locked_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('academic_periods', function (Blueprint $table) {
            if (!Schema::hasColumn('academic_periods', 'is_submission_open')) {
                $table->boolean('is_submission_open')->default(false)->after('status');
            }
            if (!Schema::hasColumn('academic_periods', 'is_locked')) {
                $table->boolean('is_locked')->default(false)->after('announcement');
            }
            if (!Schema::hasColumn('academic_periods', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('is_locked');
            }
            if (!Schema::hasColumn('academic_periods', 'locked_by')) {
                $table->foreignId('locked_by')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
            }
        });
    }
};
