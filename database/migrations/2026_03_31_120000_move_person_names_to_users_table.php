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
            $students = DB::table('students')
                ->select('user_id', 'first_name', 'middle_name', 'last_name')
                ->get();

            foreach ($students as $student) {
                DB::table('users')
                    ->where('id', $student->user_id)
                    ->update([
                        'first_name' => DB::raw("COALESCE(first_name, " . DB::getPdo()->quote($student->first_name) . ")"),
                        'middle_name' => DB::raw("COALESCE(middle_name, " . ($student->middle_name !== null ? DB::getPdo()->quote($student->middle_name) : "NULL") . ")"),
                        'last_name' => DB::raw("COALESCE(last_name, " . DB::getPdo()->quote($student->last_name) . ")"),
                    ]);
            }
        }

        if (Schema::hasTable('coaches')) {
            $coaches = DB::table('coaches')
                ->select('user_id', 'first_name', 'middle_name', 'last_name')
                ->get();

            foreach ($coaches as $coach) {
                DB::table('users')
                    ->where('id', $coach->user_id)
                    ->update([
                        'first_name' => DB::raw("COALESCE(first_name, " . DB::getPdo()->quote($coach->first_name) . ")"),
                        'middle_name' => DB::raw("COALESCE(middle_name, " . ($coach->middle_name !== null ? DB::getPdo()->quote($coach->middle_name) : "NULL") . ")"),
                        'last_name' => DB::raw("COALESCE(last_name, " . DB::getPdo()->quote($coach->last_name) . ")"),
                    ]);
            }
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
            $users = DB::table('users')
                ->select('id', 'first_name', 'last_name', 'name')
                ->get();

            foreach ($users as $user) {
                if (!empty($user->name)) {
                    continue;
                }

                $full = trim(implode(' ', array_filter([(string) $user->first_name, (string) $user->last_name])));
                if ($full === '') {
                    continue;
                }

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['name' => $full]);
            }
        }

        if (Schema::hasTable('students')) {
            $users = DB::table('users')
                ->select('id', 'first_name', 'middle_name', 'last_name')
                ->get()
                ->keyBy('id');

            $students = DB::table('students')->select('id', 'user_id')->get();
            foreach ($students as $student) {
                $user = $users->get($student->user_id);
                if (!$user) {
                    continue;
                }
                DB::table('students')
                    ->where('id', $student->id)
                    ->update([
                        'first_name' => $user->first_name,
                        'middle_name' => $user->middle_name,
                        'last_name' => $user->last_name,
                    ]);
            }
        }

        if (Schema::hasTable('coaches')) {
            $users = DB::table('users')
                ->select('id', 'first_name', 'middle_name', 'last_name')
                ->get()
                ->keyBy('id');

            $coaches = DB::table('coaches')->select('id', 'user_id')->get();
            foreach ($coaches as $coach) {
                $user = $users->get($coach->user_id);
                if (!$user) {
                    continue;
                }
                DB::table('coaches')
                    ->where('id', $coach->id)
                    ->update([
                        'first_name' => $user->first_name,
                        'middle_name' => $user->middle_name,
                        'last_name' => $user->last_name,
                    ]);
            }
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'first_name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['first_name', 'middle_name', 'last_name']);
            });
        }
    }
};
