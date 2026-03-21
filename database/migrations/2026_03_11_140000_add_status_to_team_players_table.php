<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_players', function (Blueprint $table) {
            $table->enum('player_status', ['active', 'injured', 'suspended'])
                ->default('active')
                ->after('athlete_position');
        });
    }

    public function down(): void
    {
        Schema::table('team_players', function (Blueprint $table) {
            $table->dropColumn('player_status');
        });
    }
};
