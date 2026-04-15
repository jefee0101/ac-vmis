<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_period_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_period_id')->constrained('academic_periods')->cascadeOnDelete();
            $table->text('message');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['academic_period_id', 'published_at'], 'academic_period_messages_period_published_idx');
        });

        if (Schema::hasColumn('academic_periods', 'announcement')) {
            $now = now();
            $periods = DB::table('academic_periods')
                ->select('id', 'announcement', 'created_at', 'updated_at')
                ->whereNotNull('announcement')
                ->where('announcement', '!=', '')
                ->get();

            foreach ($periods as $period) {
                DB::table('academic_period_messages')->insert([
                    'academic_period_id' => $period->id,
                    'message' => $period->announcement,
                    'published_at' => $period->updated_at ?? $period->created_at ?? $now,
                    'created_by' => null,
                    'created_at' => $period->created_at ?? $now,
                    'updated_at' => $period->updated_at ?? $now,
                ]);
            }

            Schema::table('academic_periods', function (Blueprint $table) {
                $table->dropColumn('announcement');
            });
        }
    }

    public function down(): void
    {
        Schema::table('academic_periods', function (Blueprint $table) {
            $table->text('announcement')->nullable()->after('is_submission_open');
        });

        $messages = DB::table('academic_period_messages')
            ->orderBy('published_at')
            ->get()
            ->groupBy('academic_period_id');

        foreach ($messages as $periodId => $rows) {
            $latest = $rows->last();
            DB::table('academic_periods')
                ->where('id', $periodId)
                ->update([
                    'announcement' => $latest->message ?? null,
                ]);
        }

        Schema::dropIfExists('academic_period_messages');
    }
};
