<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_schedules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('team_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('title');
            $table->enum('type', ['practice', 'game', 'meeting']);
            $table->string('venue');

            $table->dateTime('start_time')->index();
            $table->dateTime('end_time')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['venue', 'start_time', 'end_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_schedules');
    }
};
