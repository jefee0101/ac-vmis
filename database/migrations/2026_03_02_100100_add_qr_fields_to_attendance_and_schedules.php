<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_schedules', function (Blueprint $table) {
            $table->unsignedSmallInteger('qr_window_minutes')->default(20)->after('notes');
            $table->unsignedSmallInteger('qr_rotation_seconds')->default(25)->after('qr_window_minutes');
        });

        Schema::table('schedule_attendances', function (Blueprint $table) {
            $table->enum('verification_method', ['self_response', 'qr_verified', 'manual_override'])
                ->default('self_response')
                ->after('status');
            $table->foreignId('qr_token_id')
                ->nullable()
                ->after('verification_method')
                ->constrained('schedule_qr_tokens')
                ->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('recorded_at');
            $table->text('override_reason')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('qr_token_id');
            $table->dropColumn(['verification_method', 'verified_at', 'override_reason']);
        });

        Schema::table('team_schedules', function (Blueprint $table) {
            $table->dropColumn(['qr_window_minutes', 'qr_rotation_seconds']);
        });
    }
};
