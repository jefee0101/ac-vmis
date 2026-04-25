<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('academic_documents')
            ->where('review_status', 'auto_processed')
            ->whereNull('reviewed_by')
            ->update([
                'reviewed_at' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // This migration intentionally does not restore ambiguous legacy timestamps.
    }
};
