<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_players', function (Blueprint $table) {
            $table->boolean('manual_inactive')
                ->default(false)
                ->after('player_status');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE team_players
                MODIFY COLUMN player_status ENUM('active', 'injured', 'suspended', 'inactive')
                NOT NULL DEFAULT 'active'
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE team_players
                MODIFY COLUMN player_status ENUM('active', 'injured', 'suspended')
                NOT NULL DEFAULT 'active'
            ");
        }

        Schema::table('team_players', function (Blueprint $table) {
            $table->dropColumn('manual_inactive');
        });
    }
};
