<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'first_name')) {
                    $table->string('first_name')->nullable()->after('id');
                }
                if (!Schema::hasColumn('users', 'middle_name')) {
                    $table->string('middle_name')->nullable()->after('first_name');
                }
                if (!Schema::hasColumn('users', 'last_name')) {
                    $table->string('last_name')->nullable()->after('middle_name');
                }
            });
        }

        if (Schema::hasTable('students')) {
            DB::statement(
                "UPDATE users u
                 INNER JOIN students s ON s.user_id = u.id
                 SET u.first_name = COALESCE(u.first_name, s.first_name),
                     u.middle_name = COALESCE(u.middle_name, s.middle_name),
                     u.last_name = COALESCE(u.last_name, s.last_name)"
            );
        }

        if (Schema::hasTable('coaches')) {
            DB::statement(
                "UPDATE users u
                 INNER JOIN coaches c ON c.user_id = u.id
                 SET u.first_name = COALESCE(u.first_name, c.first_name),
                     u.middle_name = COALESCE(u.middle_name, c.middle_name),
                     u.last_name = COALESCE(u.last_name, c.last_name)"
            );
        }

        if (Schema::hasColumn('users', 'name')) {
            $users = DB::table('users')
                ->select('id', 'name')
                ->whereNull('first_name')
                ->whereNotNull('name')
                ->get();

            foreach ($users as $user) {
                $raw = trim((string) $user->name);
                if ($raw === '') {
                    continue;
                }

                $parts = preg_split('/\s+/', $raw) ?: [];
                $first = array_shift($parts);
                $last = count($parts) ? array_pop($parts) : null;
                $middle = count($parts) ? implode(' ', $parts) : null;

                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'first_name' => $first,
                        'middle_name' => $middle,
                        'last_name' => $last,
                    ]);
            }
        }

        if (Schema::hasTable('students') && Schema::hasColumn('students', 'first_name')) {
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn(['first_name', 'middle_name', 'last_name']);
            });
        }

        if (Schema::hasTable('coaches') && Schema::hasColumn('coaches', 'first_name')) {
            Schema::table('coaches', function (Blueprint $table) {
                $table->dropColumn(['first_name', 'middle_name', 'last_name']);
            });
        }

        if (Schema::hasColumn('users', 'name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('name')->nullable()->after('id');
            });
        }

        if (Schema::hasTable('students') && !Schema::hasColumn('students', 'first_name')) {
            Schema::table('students', function (Blueprint $table) {
                $table->string('first_name')->nullable()->after('student_id_number');
                $table->string('middle_name')->nullable()->after('first_name');
                $table->string('last_name')->nullable()->after('middle_name');
            });
        }

        if (Schema::hasTable('coaches') && !Schema::hasColumn('coaches', 'first_name')) {
            Schema::table('coaches', function (Blueprint $table) {
                $table->string('first_name', 50)->nullable()->after('user_id');
                $table->string('middle_name', 50)->nullable()->after('first_name');
                $table->string('last_name', 50)->nullable()->after('middle_name');
            });
        }

        if (Schema::hasTable('users')) {
            DB::table('users')->whereNull('name')->update([
                'name' => DB::raw("TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')))"),
            ]);
        }

        if (Schema::hasTable('students')) {
            DB::statement(
                "UPDATE students s
                 INNER JOIN users u ON s.user_id = u.id
                 SET s.first_name = u.first_name,
                     s.middle_name = u.middle_name,
                     s.last_name = u.last_name"
            );
        }

        if (Schema::hasTable('coaches')) {
            DB::statement(
                "UPDATE coaches c
                 INNER JOIN users u ON c.user_id = u.id
                 SET c.first_name = u.first_name,
                     c.middle_name = u.middle_name,
                     c.last_name = u.last_name"
            );
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'first_name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['first_name', 'middle_name', 'last_name']);
            });
        }
    }
};
