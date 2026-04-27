<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wellness_logs', function (Blueprint $table) {
            $table->timestamp('injury_resolved_at')->nullable()->after('injury_notes');
            $table->foreignId('injury_resolved_by')->nullable()->after('injury_resolved_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wellness_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('injury_resolved_by');
            $table->dropColumn('injury_resolved_at');
        });
    }
};
