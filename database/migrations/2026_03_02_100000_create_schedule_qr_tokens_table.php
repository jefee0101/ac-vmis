<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('schedule_qr_tokens');
    }
};
