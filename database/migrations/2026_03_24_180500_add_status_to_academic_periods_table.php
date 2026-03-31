<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_periods', function (Blueprint $table) {
            $table->string('status', 16)->default('draft')->after('ends_on');
            $table->index('status');
        });

        $today = Carbon::today();
        $rows = DB::table('academic_periods')
            ->select('id', 'starts_on', 'is_submission_open', 'is_locked')
            ->get();

        foreach ($rows as $row) {
            $status = 'closed';
            if (property_exists($row, 'is_locked') && (int) $row->is_locked === 1) {
                $status = 'locked';
            } elseif (property_exists($row, 'is_submission_open') && (int) $row->is_submission_open === 1) {
                $status = 'open';
            } elseif (!empty($row->starts_on) && Carbon::parse($row->starts_on)->isFuture()) {
                $status = 'draft';
            }

            DB::table('academic_periods')
                ->where('id', $row->id)
                ->update(['status' => $status]);
        }
    }

    public function down(): void
    {
        Schema::table('academic_periods', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
