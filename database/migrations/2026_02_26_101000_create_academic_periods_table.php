<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_periods', function (Blueprint $table) {
            $table->id();
            $table->string('school_year', 9); // e.g. 2026-2027
            $table->enum('term', ['1st_sem', '2nd_sem', 'summer']);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();

            $table->unique(['school_year', 'term'], 'academic_periods_year_term_unique');
            $table->index(['starts_on', 'ends_on'], 'academic_periods_range_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_periods');
    }
};

