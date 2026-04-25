<?php

use App\Models\AcademicDocumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DOCUMENT_CONTEXT_CHECK = 'academic_documents_period_context_check';

    public function up(): void
    {
        $registrationTypeIds = DB::table('academic_document_types')
            ->where('context', AcademicDocumentType::CONTEXT_REGISTRATION)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $periodTypeIds = DB::table('academic_document_types')
            ->where('context', AcademicDocumentType::CONTEXT_PERIOD_SUBMISSION)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $registrationSupportingDocumentId = DB::table('academic_document_types')
            ->where('context', AcademicDocumentType::CONTEXT_REGISTRATION)
            ->where('code', AcademicDocumentType::CODE_SUPPORTING_DOCUMENT)
            ->value('id');

        if ($registrationSupportingDocumentId) {
            $invalidPeriodlessDocuments = DB::table('academic_documents as documents')
                ->join('academic_document_types as document_types', 'document_types.id', '=', 'documents.document_type_id')
                ->where('document_types.context', AcademicDocumentType::CONTEXT_PERIOD_SUBMISSION)
                ->whereNull('documents.academic_period_id')
                ->select('documents.id')
                ->get();

            foreach ($invalidPeriodlessDocuments as $document) {
                DB::table('academic_documents')
                    ->where('id', $document->id)
                    ->update([
                        'document_type_id' => (int) $registrationSupportingDocumentId,
                        'updated_at' => now(),
                    ]);
            }
        }

        $invalidRegistrationDocuments = DB::table('academic_documents as documents')
            ->join('academic_document_types as document_types', 'document_types.id', '=', 'documents.document_type_id')
            ->where('document_types.context', AcademicDocumentType::CONTEXT_REGISTRATION)
            ->whereNotNull('documents.academic_period_id')
            ->select('documents.id')
            ->get();

        foreach ($invalidRegistrationDocuments as $document) {
            DB::table('academic_documents')
                ->where('id', $document->id)
                ->update([
                    'academic_period_id' => null,
                    'updated_at' => now(),
                ]);
        }

        DB::table('academic_eligibility_evaluations')
            ->whereNull('academic_period_id')
            ->delete();

        $driver = Schema::getConnection()->getDriverName();

        $this->dropAcademicEligibilityPeriodForeignKey();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE academic_eligibility_evaluations MODIFY academic_period_id BIGINT UNSIGNED NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE academic_eligibility_evaluations ALTER COLUMN academic_period_id SET NOT NULL');
        }

        $this->addAcademicEligibilityPeriodForeignKey(cascadeOnDelete: true);

        if ($registrationTypeIds->isNotEmpty() && $periodTypeIds->isNotEmpty()) {
            $registrationList = $registrationTypeIds->implode(', ');
            $periodList = $periodTypeIds->implode(', ');
            $expression = "((academic_period_id IS NULL AND document_type_id IN ({$registrationList})) OR (academic_period_id IS NOT NULL AND document_type_id IN ({$periodList})))";

            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $this->dropDocumentContextConstraintForMySqlFamily();
                DB::statement("ALTER TABLE academic_documents ADD CONSTRAINT " . self::DOCUMENT_CONTEXT_CHECK . " CHECK {$expression}");
            } elseif ($driver === 'pgsql') {
                DB::statement("ALTER TABLE academic_documents DROP CONSTRAINT IF EXISTS " . self::DOCUMENT_CONTEXT_CHECK);
                DB::statement("ALTER TABLE academic_documents ADD CONSTRAINT " . self::DOCUMENT_CONTEXT_CHECK . " CHECK {$expression}");
            }
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        $this->dropAcademicEligibilityPeriodForeignKey();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->dropDocumentContextConstraintForMySqlFamily();
            DB::statement('ALTER TABLE academic_eligibility_evaluations MODIFY academic_period_id BIGINT UNSIGNED NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE academic_documents DROP CONSTRAINT IF EXISTS " . self::DOCUMENT_CONTEXT_CHECK);
            DB::statement('ALTER TABLE academic_eligibility_evaluations ALTER COLUMN academic_period_id DROP NOT NULL');
        }

        $this->addAcademicEligibilityPeriodForeignKey(cascadeOnDelete: false);
    }

    private function dropDocumentContextConstraintForMySqlFamily(): void
    {
        try {
            DB::statement("ALTER TABLE academic_documents DROP CHECK " . self::DOCUMENT_CONTEXT_CHECK);
        } catch (\Throwable) {
            try {
                DB::statement("ALTER TABLE academic_documents DROP CONSTRAINT " . self::DOCUMENT_CONTEXT_CHECK);
            } catch (\Throwable) {
                // Constraint did not exist or the current engine uses a different drop syntax.
            }
        }
    }

    private function dropAcademicEligibilityPeriodForeignKey(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $database = Schema::getConnection()->getDatabaseName();
            $foreignKeys = DB::select(
                'SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = ?
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
                  AND REFERENCED_TABLE_NAME IS NOT NULL',
                [$database, 'academic_eligibility_evaluations', 'academic_period_id']
            );

            foreach ($foreignKeys as $foreignKey) {
                $name = $foreignKey->CONSTRAINT_NAME ?? null;
                if (!$name) {
                    continue;
                }

                DB::statement("ALTER TABLE academic_eligibility_evaluations DROP FOREIGN KEY `{$name}`");
            }

            return;
        }

        try {
            Schema::table('academic_eligibility_evaluations', function (Blueprint $table) {
                $table->dropForeign(['academic_period_id']);
            });
        } catch (\Throwable) {
            // Foreign key may already be absent in partially applied environments.
        }
    }

    private function addAcademicEligibilityPeriodForeignKey(bool $cascadeOnDelete): void
    {
        Schema::table('academic_eligibility_evaluations', function (Blueprint $table) use ($cascadeOnDelete) {
            $foreign = $table->foreign('academic_period_id')
                ->references('id')
                ->on('academic_periods');

            if ($cascadeOnDelete) {
                $foreign->cascadeOnDelete();
            } else {
                $foreign->nullOnDelete();
            }
        });
    }
};
