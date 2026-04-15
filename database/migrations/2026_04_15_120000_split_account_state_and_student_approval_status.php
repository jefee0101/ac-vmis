<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'account_state')) {
                $table->enum('account_state', ['active', 'deactivated'])
                    ->default('active')
                    ->after('role');
            }
        });

        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'approval_status')) {
                $table->enum('approval_status', ['pending', 'approved', 'rejected'])
                    ->default('pending')
                    ->after('current_grade_level');
            }
        });

        DB::table('users')
            ->where('status', 'deactivated')
            ->update(['account_state' => 'deactivated']);

        DB::table('users')
            ->whereIn('role', ['admin', 'coach'])
            ->where('status', 'deactivated')
            ->update(['account_state' => 'deactivated']);

        DB::table('users')
            ->whereIn('role', ['admin', 'coach'])
            ->where('status', '!=', 'deactivated')
            ->update(['account_state' => 'active']);

        $studentApprovals = DB::table('users')
            ->join('students', 'students.user_id', '=', 'users.id')
            ->whereIn('users.role', ['student', 'student-athlete'])
            ->select('students.id as student_id', 'users.status', 'users.account_state')
            ->get();

        foreach ($studentApprovals as $row) {
            $approvalStatus = match ((string) $row->status) {
                'approved' => 'approved',
                'rejected' => 'rejected',
                default => 'pending',
            };

            DB::table('students')
                ->where('id', $row->student_id)
                ->update(['approval_status' => $approvalStatus]);

            if ((string) $row->status === 'deactivated') {
                DB::table('users')
                    ->where('id', DB::table('students')->where('id', $row->student_id)->value('user_id'))
                    ->update(['account_state' => 'deactivated']);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('students', 'approval_status')) {
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('approval_status');
            });
        }

        if (Schema::hasColumn('users', 'account_state')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('account_state');
            });
        }
    }
};
