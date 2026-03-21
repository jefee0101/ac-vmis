<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('team_name');
            $table->string('team_avatar')->nullable();

            // Sport FK
            $table->unsignedBigInteger('sport_id')->nullable();
            $table->foreign('sport_id')->references('id')->on('sports')->onDelete('set null');

            $table->year('year')->nullable();

            // Coaches
            $table->unsignedBigInteger('coach_id'); // Head coach
            $table->foreign('coach_id')->references('id')->on('coaches')->onDelete('cascade');

            $table->unsignedBigInteger('assistant_coach_id')->nullable(); // Assistant coach
            $table->foreign('assistant_coach_id')->references('id')->on('coaches')->onDelete('set null');

            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
