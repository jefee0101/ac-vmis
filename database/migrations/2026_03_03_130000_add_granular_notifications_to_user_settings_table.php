<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->boolean('notify_approvals')->default(true)->after('notification_in_app_enabled');
            $table->boolean('notify_schedule_changes')->default(true)->after('notify_approvals');
            $table->boolean('notify_attendance_changes')->default(true)->after('notify_schedule_changes');
            $table->boolean('notify_wellness_alerts')->default(true)->after('notify_attendance_changes');
            $table->boolean('notify_academic_alerts')->default(true)->after('notify_wellness_alerts');

            $table->boolean('notify_attendance_exceptions')->default(true)->after('notify_academic_alerts');
            $table->boolean('notify_wellness_injury_threshold')->default(true)->after('notify_attendance_exceptions');
            $table->unsignedTinyInteger('wellness_injury_threshold_level')->default(3)->after('notify_wellness_injury_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->dropColumn([
                'notify_approvals',
                'notify_schedule_changes',
                'notify_attendance_changes',
                'notify_wellness_alerts',
                'notify_academic_alerts',
                'notify_attendance_exceptions',
                'notify_wellness_injury_threshold',
                'wellness_injury_threshold_level',
            ]);
        });
    }
};
