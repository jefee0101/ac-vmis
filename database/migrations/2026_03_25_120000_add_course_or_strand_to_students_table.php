<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('course_or_strand')->nullable()->after('course');
        });

        DB::table('students')->whereNull('course_or_strand')->update([
            'course_or_strand' => DB::raw('course'),
        ]);
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('course_or_strand');
        });
    }
};
