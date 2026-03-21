<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'email')) {
                $table->dropColumn('email');
            }
        });

        Schema::table('coaches', function (Blueprint $table) {
            if (Schema::hasColumn('coaches', 'coach_type')) {
                $table->dropColumn('coach_type');
            }
        });

        try {
            Schema::table('students', function (Blueprint $table) {
                $table->unique('user_id', 'students_user_id_unique');
            });
        } catch (\Throwable $e) {
            // no-op
        }

        try {
            Schema::table('coaches', function (Blueprint $table) {
                $table->unique('user_id', 'coaches_user_id_unique');
            });
        } catch (\Throwable $e) {
            // no-op
        }

        // Keep this best-effort to avoid breaking non-MySQL environments.
        try {
            DB::statement('ALTER TABLE sports MODIFY name VARCHAR(255) NOT NULL');
        } catch (\Throwable $e) {
            // no-op
        }

        try {
            Schema::table('sports', function (Blueprint $table) {
                $table->unique('name', 'sports_name_unique');
            });
        } catch (\Throwable $e) {
            // no-op
        }
    }

    public function down(): void
    {
        try {
            Schema::table('sports', function (Blueprint $table) {
                $table->dropUnique('sports_name_unique');
            });
        } catch (\Throwable $e) {
            // no-op
        }

        try {
            Schema::table('coaches', function (Blueprint $table) {
                $table->dropUnique('coaches_user_id_unique');
            });
        } catch (\Throwable $e) {
            // no-op
        }

        try {
            Schema::table('students', function (Blueprint $table) {
                $table->dropUnique('students_user_id_unique');
            });
        } catch (\Throwable $e) {
            // no-op
        }

        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'email')) {
                $table->string('email')->nullable()->after('student_status');
            }
        });

        Schema::table('coaches', function (Blueprint $table) {
            if (!Schema::hasColumn('coaches', 'coach_type')) {
                $table->enum('coach_type', ['Head Coach', 'Assistant Coach'])
                    ->default('Assistant Coach')
                    ->after('home_address');
            }
        });
    }
};
