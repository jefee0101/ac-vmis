<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('schedule_attendances')) {
            Schema::table('schedule_attendances', function (Blueprint $table) {
                if (Schema::hasColumn('schedule_attendances', 'qr_token_id')) {
                    $table->dropConstrainedForeignId('qr_token_id');
                }
            });
        }

        if (Schema::hasTable('team_schedules')) {
            Schema::table('team_schedules', function (Blueprint $table) {
                $dropColumns = [];

                if (Schema::hasColumn('team_schedules', 'qr_window_minutes')) {
                    $dropColumns[] = 'qr_window_minutes';
                }

                if (Schema::hasColumn('team_schedules', 'qr_rotation_seconds')) {
                    $dropColumns[] = 'qr_rotation_seconds';
                }

                if ($dropColumns !== []) {
                    $table->dropColumn($dropColumns);
                }
            });
        }

        Schema::dropIfExists('schedule_qr_tokens');
    }

    public function down(): void
    {
        if (!Schema::hasTable('schedule_qr_tokens')) {
            Schema::create('schedule_qr_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('schedule_id')->constrained('team_schedules')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->string('token_hash', 64)->index();
                $table->dateTime('issued_at');
                $table->dateTime('expires_at')->index();
                $table->dateTime('used_at')->nullable()->index();
                $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['schedule_id', 'student_id', 'expires_at'], 'schedule_qr_tokens_lookup_idx');
            });
        }

        if (Schema::hasTable('team_schedules')) {
            Schema::table('team_schedules', function (Blueprint $table) {
                if (!Schema::hasColumn('team_schedules', 'qr_window_minutes')) {
                    $table->unsignedSmallInteger('qr_window_minutes')->default(20)->after('notes');
                }

                if (!Schema::hasColumn('team_schedules', 'qr_rotation_seconds')) {
                    $table->unsignedSmallInteger('qr_rotation_seconds')->default(25)->after('qr_window_minutes');
                }
            });
        }

        if (Schema::hasTable('schedule_attendances') && !Schema::hasColumn('schedule_attendances', 'qr_token_id')) {
            Schema::table('schedule_attendances', function (Blueprint $table) {
                $table->foreignId('qr_token_id')
                    ->nullable()
                    ->after('verification_method')
                    ->constrained('schedule_qr_tokens')
                    ->nullOnDelete();
            });
        }
    }
};
