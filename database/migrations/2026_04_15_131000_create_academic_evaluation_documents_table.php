<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_evaluation_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('academic_eligibility_evaluations')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('academic_documents')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('evaluation_id', 'academic_evaluation_documents_evaluation_unique');
            $table->unique(['evaluation_id', 'document_id'], 'academic_evaluation_documents_eval_doc_unique');
        });

        $evaluations = DB::table('academic_eligibility_evaluations')
            ->get(['id', 'student_id', 'academic_period_id', 'created_at', 'updated_at']);

        foreach ($evaluations as $evaluation) {
            $documentId = DB::table('academic_documents')
                ->where('student_id', $evaluation->student_id)
                ->where('academic_period_id', $evaluation->academic_period_id)
                ->orderByDesc('uploaded_at')
                ->orderByDesc('id')
                ->value('id');

            if (!$documentId) {
                continue;
            }

            DB::table('academic_evaluation_documents')->insert([
                'evaluation_id' => $evaluation->id,
                'document_id' => $documentId,
                'created_at' => $evaluation->created_at ?? now(),
                'updated_at' => $evaluation->updated_at ?? now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_evaluation_documents');
    }
};
