<?php

use App\Models\Sport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $supportedDescriptions = [
            Sport::NAME_BASKETBALL => 'Varsity basketball program.',
            Sport::NAME_SOCCER => 'Varsity soccer program.',
            Sport::NAME_VOLLEYBALL => 'Varsity volleyball program.',
        ];

        $football = DB::table('sports')->where('name', 'Football')->first();
        $soccer = DB::table('sports')->where('name', Sport::NAME_SOCCER)->first();

        if ($football && $soccer) {
            DB::table('teams')
                ->where('sport_id', $football->id)
                ->update([
                    'sport_id' => $soccer->id,
                    'updated_at' => $now,
                ]);

            DB::table('sports')->where('id', $football->id)->delete();
        } elseif ($football) {
            DB::table('sports')
                ->where('id', $football->id)
                ->update([
                    'name' => Sport::NAME_SOCCER,
                    'description' => $supportedDescriptions[Sport::NAME_SOCCER],
                    'updated_at' => $now,
                ]);
        }

        foreach ($supportedDescriptions as $name => $description) {
            $existing = DB::table('sports')->where('name', $name)->first();

            if ($existing) {
                DB::table('sports')
                    ->where('id', $existing->id)
                    ->update([
                        'description' => $description,
                        'updated_at' => $now,
                    ]);

                continue;
            }

            DB::table('sports')->insert([
                'name' => $name,
                'description' => $description,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $unsupportedSportIds = DB::table('sports')
            ->whereNotIn('name', Sport::supportedNames())
            ->pluck('id');

        if ($unsupportedSportIds->isNotEmpty()) {
            DB::table('teams')
                ->whereIn('sport_id', $unsupportedSportIds->all())
                ->update([
                    'sport_id' => null,
                    'archived_at' => $now,
                    'updated_at' => $now,
                ]);

            DB::table('sports')
                ->whereIn('id', $unsupportedSportIds->all())
                ->delete();
        }
    }

    public function down(): void
    {
        // This migration intentionally keeps the supported-sports restriction in place.
    }
};
