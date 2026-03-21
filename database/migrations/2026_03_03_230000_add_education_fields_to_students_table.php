<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->enum('education_level', ['Senior High', 'College'])
                ->nullable()
                ->after('course');
            $table->string('year_level', 10)
                ->nullable()
                ->after('current_grade_level');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['education_level', 'year_level']);
        });
    }
};
