<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->enum('type', ['approval', 'general', 'academic', 'schedule', 'system'])->default('general');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('announcement_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('announcement_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'user_id'], 'announcement_event_user_unique');
            $table->index(['user_id', 'read_at'], 'announcement_recipients_user_read_at_index');
        });

        Schema::create('academic_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('source_period_id')->nullable()->constrained('academic_periods')->nullOnDelete();
            $table->string('reason');
            $table->enum('status', ['suspended', 'unenrolled', 'resolved']);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status'], 'academic_holds_student_status_index');
        });

        if (Schema::hasTable('announcements')) {
            $legacyRows = DB::table('announcements')
                ->orderBy('id')
                ->get();

            $eventMap = [];
            foreach ($legacyRows as $row) {
                $key = implode('|', [
                    sha1((string) $row->title),
                    sha1((string) $row->message),
                    (string) $row->type,
                    (string) ($row->created_by ?? '0'),
                    (string) ($row->published_at ?? ''),
                ]);

                if (!isset($eventMap[$key])) {
                    $eventId = DB::table('announcement_events')->insertGetId([
                        'title' => $row->title,
                        'message' => $row->message,
                        'type' => $row->type,
                        'published_at' => $row->published_at,
                        'created_by' => $row->created_by,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ]);

                    $eventMap[$key] = $eventId;
                }

                DB::table('announcement_recipients')->insert([
                    'event_id' => $eventMap[$key],
                    'user_id' => $row->user_id,
                    'read_at' => $row->read_at,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);
            }
        }

        if (Schema::hasTable('students')) {
            $openPeriodId = DB::table('academic_periods')
                ->orderByDesc('starts_on')
                ->value('id');

            $students = DB::table('students')
                ->whereIn('student_status', ['Suspended', 'Unenrolled'])
                ->get(['id', 'student_status', 'created_at', 'updated_at']);

            foreach ($students as $student) {
                DB::table('academic_holds')->insert([
                    'student_id' => $student->id,
                    'source_period_id' => $openPeriodId,
                    'reason' => 'legacy_student_status',
                    'status' => strtolower((string) $student->student_status),
                    'started_at' => $student->updated_at ?? $student->created_at ?? now(),
                    'resolved_at' => null,
                    'created_at' => $student->created_at ?? now(),
                    'updated_at' => $student->updated_at ?? now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_holds');
        Schema::dropIfExists('announcement_recipients');
        Schema::dropIfExists('announcement_events');
    }
};
