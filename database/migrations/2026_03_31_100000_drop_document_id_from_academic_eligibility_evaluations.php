<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_eligibility_evaluations', function (Blueprint $table) {
            if (Schema::hasColumn('academic_eligibility_evaluations', 'document_id')) {
                $table->dropConstrainedForeignId('document_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('academic_eligibility_evaluations', function (Blueprint $table) {
            if (!Schema::hasColumn('academic_eligibility_evaluations', 'document_id')) {
                $table->foreignId('document_id')
                    ->after('academic_period_id')
                    ->constrained('academic_documents')
                    ->cascadeOnDelete();
            }
        });
    }
};
