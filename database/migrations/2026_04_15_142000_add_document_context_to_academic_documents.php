<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_documents', function (Blueprint $table) {
            $table->enum('document_context', ['registration', 'period_submission'])
                ->default('registration')
                ->after('document_type');
            $table->index(['document_context', 'document_type'], 'academic_documents_context_type_index');
        });

        DB::table('academic_documents')
            ->whereNotNull('academic_period_id')
            ->update(['document_context' => 'period_submission']);

        DB::table('academic_documents')
            ->whereNull('academic_period_id')
            ->update(['document_context' => 'registration']);
    }

    public function down(): void
    {
        Schema::table('academic_documents', function (Blueprint $table) {
            $table->dropIndex('academic_documents_context_type_index');
            $table->dropColumn('document_context');
        });
    }
};
