<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('academic_holds')
            ->whereNull('reason')
            ->orWhereNotIn('reason', ['missing_submissions', 'legacy_student_status', 'manual_hold'])
            ->update([
                'reason' => 'manual_hold',
            ]);

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("
                ALTER TABLE academic_holds
                MODIFY reason ENUM('missing_submissions', 'legacy_student_status', 'manual_hold') NOT NULL
            ");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("
                ALTER TABLE academic_holds
                DROP CONSTRAINT IF EXISTS academic_holds_reason_check
            ");

            DB::statement("
                ALTER TABLE academic_holds
                ADD CONSTRAINT academic_holds_reason_check
                CHECK (reason IN ('missing_submissions', 'legacy_student_status', 'manual_hold'))
            ");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("
                ALTER TABLE academic_holds
                MODIFY reason VARCHAR(255) NOT NULL
            ");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("
                ALTER TABLE academic_holds
                DROP CONSTRAINT IF EXISTS academic_holds_reason_check
            ");
        }
    }
};
