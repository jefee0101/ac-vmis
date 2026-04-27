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

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("
                ALTER TABLE team_players
                MODIFY COLUMN player_status ENUM('active', 'injured', 'suspended', 'inactive')
                NOT NULL DEFAULT 'active'
            ");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE team_players DROP CONSTRAINT IF EXISTS team_players_player_status_check");
            DB::statement("ALTER TABLE team_players ALTER COLUMN player_status TYPE VARCHAR(20)");
            DB::statement("UPDATE team_players SET player_status = 'active' WHERE player_status IS NULL");
            DB::statement("ALTER TABLE team_players ALTER COLUMN player_status SET DEFAULT 'active'");
            DB::statement("ALTER TABLE team_players ALTER COLUMN player_status SET NOT NULL");
            DB::statement("
                ALTER TABLE team_players
                ADD CONSTRAINT team_players_player_status_check
                CHECK (player_status IN ('active', 'injured', 'suspended', 'inactive'))
            ");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("
                ALTER TABLE team_players
                MODIFY COLUMN player_status ENUM('active', 'injured', 'suspended')
                NOT NULL DEFAULT 'active'
            ");
        } elseif ($driver === 'pgsql') {
            DB::statement("UPDATE team_players SET player_status = 'active' WHERE player_status = 'inactive' OR player_status IS NULL");
            DB::statement("ALTER TABLE team_players DROP CONSTRAINT IF EXISTS team_players_player_status_check");
            DB::statement("ALTER TABLE team_players ALTER COLUMN player_status TYPE VARCHAR(20)");
            DB::statement("ALTER TABLE team_players ALTER COLUMN player_status SET DEFAULT 'active'");
            DB::statement("ALTER TABLE team_players ALTER COLUMN player_status SET NOT NULL");
            DB::statement("
                ALTER TABLE team_players
                ADD CONSTRAINT team_players_player_status_check
                CHECK (player_status IN ('active', 'injured', 'suspended'))
            ");
        }

        Schema::table('team_players', function (Blueprint $table) {
            $table->dropColumn('manual_inactive');
        });
    }
};
