<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (!Schema::hasTable('academic_document_types')) {
            Schema::create('academic_document_types', function (Blueprint $table) {
                $table->id();
                $table->enum('context', ['registration', 'period_submission']);
                $table->string('code', 50);
                $table->string('label', 120);
                $table->unique(['context', 'code'], 'academic_document_types_context_code_unique');
            });
        }

        if (!DB::table('academic_document_types')->exists()) {
            DB::table('academic_document_types')->insert([
                ['context' => 'registration', 'code' => 'tor', 'label' => 'Transcript of Records'],
                ['context' => 'registration', 'code' => 'supporting_document', 'label' => 'Supporting Document'],
                ['context' => 'period_submission', 'code' => 'grade_report', 'label' => 'Grade Report'],
                ['context' => 'period_submission', 'code' => 'supporting_document', 'label' => 'Supporting Document'],
            ]);
        }

        if (!Schema::hasColumn('academic_documents', 'document_type_id')) {
            Schema::table('academic_documents', function (Blueprint $table) {
                $table->foreignId('document_type_id')->nullable()->after('student_id')->constrained('academic_document_types');
            });
        }

        $types = DB::table('academic_document_types')
            ->select('id', 'context', 'code')
            ->get()
            ->keyBy(fn ($row) => $row->context . ':' . $row->code);

        if (Schema::hasColumn('academic_documents', 'document_type') && Schema::hasColumn('academic_documents', 'document_context')) {
            $documents = DB::table('academic_documents')
                ->select('id', 'document_context', 'document_type')
                ->get();

            foreach ($documents as $document) {
                $legacyContext = $document->document_context ?: ($document->document_type === 'tor' ? 'registration' : 'period_submission');
                $legacyCode = $document->document_type === 'other' ? 'supporting_document' : $document->document_type;
                $typeId = $types[$legacyContext . ':' . $legacyCode]->id ?? null;

                if ($typeId) {
                    DB::table('academic_documents')
                        ->where('id', $document->id)
                        ->update(['document_type_id' => $typeId]);
                }
            }
        }

        $this->ensureIndex('academic_documents', 'academic_documents_student_fk_idx', 'CREATE INDEX academic_documents_student_fk_idx ON academic_documents (student_id)');
        $this->ensureIndex('academic_documents', 'academic_documents_period_fk_idx', 'CREATE INDEX academic_documents_period_fk_idx ON academic_documents (academic_period_id)');

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE academic_documents MODIFY document_type_id BIGINT UNSIGNED NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE academic_documents ALTER COLUMN document_type_id SET NOT NULL');
        }

        $this->dropIndexIfExists('academic_documents', 'academic_documents_student_type_index');
        $this->dropIndexIfExists('academic_documents', 'academic_documents_period_type_index');
        $this->dropIndexIfExists('academic_documents', 'academic_documents_context_type_index');

        if (Schema::hasColumn('academic_documents', 'document_type') || Schema::hasColumn('academic_documents', 'document_context')) {
            Schema::table('academic_documents', function (Blueprint $table) {
                $table->dropColumn(['document_type', 'document_context']);
            });
        }

        $this->ensureIndex('academic_documents', 'academic_documents_student_type_id_index', 'CREATE INDEX academic_documents_student_type_id_index ON academic_documents (student_id, document_type_id)');
        $this->ensureIndex('academic_documents', 'academic_documents_period_type_id_index', 'CREATE INDEX academic_documents_period_type_id_index ON academic_documents (academic_period_id, document_type_id)');
    }

    public function down(): void
    {
        Schema::table('academic_documents', function (Blueprint $table) {
            $table->enum('document_type', ['tor', 'grade_report', 'other'])->after('document_type_id');
            $table->enum('document_context', ['registration', 'period_submission'])->default('registration')->after('document_type');
        });

        $types = DB::table('academic_document_types')->select('id', 'context', 'code')->get()->keyBy('id');
        $documents = DB::table('academic_documents')->select('id', 'document_type_id')->get();
        foreach ($documents as $document) {
            $type = $types[$document->document_type_id] ?? null;
            if (!$type) {
                continue;
            }

            DB::table('academic_documents')
                ->where('id', $document->id)
                ->update([
                    'document_context' => $type->context,
                    'document_type' => $type->code === 'supporting_document' ? 'other' : $type->code,
                ]);
        }

        Schema::table('academic_documents', function (Blueprint $table) {
            $table->dropIndex('academic_documents_student_type_id_index');
            $table->dropIndex('academic_documents_period_type_id_index');
            $table->dropConstrainedForeignId('document_type_id');
            $table->index(['student_id', 'document_type'], 'academic_documents_student_type_index');
            $table->index(['academic_period_id', 'document_type'], 'academic_documents_period_type_index');
            $table->index(['document_context', 'document_type'], 'academic_documents_context_type_index');
        });

        Schema::dropIfExists('academic_document_types');
    }

    private function ensureIndex(string $table, string $indexName, string $statement): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $exists = false;

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $exists = collect(DB::select("SHOW INDEX FROM `{$table}`"))
                ->contains(fn ($row) => ($row->Key_name ?? null) === $indexName);
        } elseif ($driver === 'pgsql') {
            $exists = !empty(DB::select(
                "SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexname = ? LIMIT 1",
                [$table, $indexName]
            ));
        } elseif ($driver === 'sqlite') {
            $exists = collect(DB::select("PRAGMA index_list('{$table}')"))
                ->contains(fn ($row) => ($row->name ?? null) === $indexName);
        }

        if (!$exists) {
            DB::statement($statement);
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $exists = false;

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $exists = collect(DB::select("SHOW INDEX FROM `{$table}`"))
                ->contains(fn ($row) => ($row->Key_name ?? null) === $indexName);
        } elseif ($driver === 'pgsql') {
            $exists = !empty(DB::select(
                "SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexname = ? LIMIT 1",
                [$table, $indexName]
            ));
        } elseif ($driver === 'sqlite') {
            $exists = collect(DB::select("PRAGMA index_list('{$table}')"))
                ->contains(fn ($row) => ($row->name ?? null) === $indexName);
        }

        if ($exists) {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement("DROP INDEX `{$indexName}` ON `{$table}`");
            } elseif ($driver === 'pgsql') {
                DB::statement("DROP INDEX IF EXISTS {$indexName}");
            } elseif ($driver === 'sqlite') {
                DB::statement("DROP INDEX IF EXISTS {$indexName}");
            }
        }
    }
};
