<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNIQUE_INDEX = 'wellness_logs_schedule_student_unique';

    public function up(): void
    {
        $duplicates = DB::table('wellness_logs')
            ->select('schedule_id', 'student_id', DB::raw('MAX(id) as keep_id'))
            ->whereNotNull('schedule_id')
            ->groupBy('schedule_id', 'student_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('wellness_logs')
                ->where('schedule_id', $duplicate->schedule_id)
                ->where('student_id', $duplicate->student_id)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('wellness_logs', function (Blueprint $table) {
            $table->unique(['schedule_id', 'student_id'], self::UNIQUE_INDEX);
        });
    }

    public function down(): void
    {
        Schema::table('wellness_logs', function (Blueprint $table) {
            $table->dropUnique(self::UNIQUE_INDEX);
        });
    }
};
