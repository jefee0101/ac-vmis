<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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

        DB::statement("
            ALTER TABLE academic_holds
            MODIFY reason ENUM('missing_submissions', 'legacy_student_status', 'manual_hold') NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE academic_holds
            MODIFY reason VARCHAR(255) NOT NULL
        ");
    }
};
