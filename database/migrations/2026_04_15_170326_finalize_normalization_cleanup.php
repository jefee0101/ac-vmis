<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('academic_eligibility_evaluations') && !Schema::hasColumn('academic_eligibility_evaluations', 'document_id')) {
            Schema::table('academic_eligibility_evaluations', function (Blueprint $table) {
                $table->foreignId('document_id')
                    ->nullable()
                    ->after('academic_period_id')
                    ->constrained('academic_documents')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('academic_evaluation_documents') && Schema::hasColumn('academic_eligibility_evaluations', 'document_id')) {
            $links = DB::table('academic_evaluation_documents')
                ->select('evaluation_id', 'document_id')
                ->get();

            foreach ($links as $link) {
                DB::table('academic_eligibility_evaluations')
                    ->where('id', $link->evaluation_id)
                    ->update(['document_id' => $link->document_id]);
            }

            Schema::dropIfExists('academic_evaluation_documents');
        }

        if (Schema::hasTable('announcements')) {
            Schema::dropIfExists('announcements');
        }

        DB::table('students')
            ->whereIn('student_status', ['Suspended', 'Unenrolled'])
            ->update(['student_status' => 'Enrolled']);

        DB::statement("ALTER TABLE students MODIFY student_status ENUM('Enrolled','Dropped','Graduated') NOT NULL DEFAULT 'Enrolled'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE students MODIFY student_status ENUM('Enrolled','Dropped','Graduated','Suspended','Unenrolled') NOT NULL DEFAULT 'Enrolled'");

        if (!Schema::hasTable('announcements')) {
            Schema::create('announcements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('title');
                $table->text('message');
                $table->enum('type', ['approval', 'general', 'academic', 'schedule', 'system'])->default('general');
                $table->timestamp('published_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['user_id', 'read_at'], 'announcements_user_read_at_index');
                $table->index(['user_id', 'read_at', 'published_at'], 'announcements_user_readat_published_index');
                $table->index(['type', 'published_at'], 'announcements_type_published_index');
            });
        }

        if (!Schema::hasTable('academic_evaluation_documents')) {
            Schema::create('academic_evaluation_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('evaluation_id')->constrained('academic_eligibility_evaluations')->cascadeOnDelete();
                $table->foreignId('document_id')->constrained('academic_documents')->cascadeOnDelete();
                $table->timestamps();
                $table->unique('evaluation_id', 'academic_evaluation_documents_evaluation_unique');
                $table->unique(['evaluation_id', 'document_id'], 'academic_evaluation_documents_eval_doc_unique');
            });
        }

        if (Schema::hasColumn('academic_eligibility_evaluations', 'document_id')) {
            $evaluations = DB::table('academic_eligibility_evaluations')
                ->whereNotNull('document_id')
                ->select('id', 'document_id')
                ->get();

            foreach ($evaluations as $evaluation) {
                DB::table('academic_evaluation_documents')->updateOrInsert(
                    ['evaluation_id' => $evaluation->id],
                    [
                        'document_id' => $evaluation->document_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            Schema::table('academic_eligibility_evaluations', function (Blueprint $table) {
                $table->dropConstrainedForeignId('document_id');
            });
        }
    }
};
