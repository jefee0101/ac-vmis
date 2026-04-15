<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('remarks', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'action'], 'account_action_logs_user_action_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_action_logs');
    }
};
