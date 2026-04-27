<?php

namespace App\Jobs;

use App\Models\AcademicDocument;
use App\Models\AcademicDocumentOcrRun;
use App\Models\AcademicDocumentType;
use App\Services\AcademicDocumentValidationService;
use App\Services\AcademicEligibilityRuleEngine;
use App\Services\GradeParsingService;
use App\Services\OcrService;
use App\Services\TeamPlayerStatusService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessAcademicDocumentOcr implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $academicDocumentId)
    {
        $this->onQueue((string) config('ocr.queue', 'default'));
    }

    public function handle(
        OcrService $ocrService,
        GradeParsingService $gradeParsingService,
        AcademicDocumentValidationService $validationService,
        AcademicEligibilityRuleEngine $ruleEngine,
        ?TeamPlayerStatusService $teamPlayerStatuses = null,
    ): void
    {
        $teamPlayerStatuses ??= app(TeamPlayerStatusService::class);

        $document = AcademicDocument::query()
            ->with('documentTypeDefinition')
            ->find($this->academicDocumentId);

        if (!$document || $document->document_type !== AcademicDocumentType::CODE_GRADE_REPORT) {
            return;
        }

        $ocrRun = AcademicDocumentOcrRun::create([
            'academic_document_id' => $document->id,
            'ocr_engine' => (string) config('ocr.default_engine', 'tesseract'),
            'run_status' => 'pending',
            'validation_status' => 'pending',
        ]);

        try {
            $absolutePath = storage_path('app/public/' . ltrim((string) $document->file_path, '/'));
            $ocrResult = $ocrService->extractText($absolutePath);

            $ocrRun->update([
                'ocr_engine' => (string) $ocrResult['engine'],
                'ocr_engine_version' => $ocrResult['engine_version'],
                'raw_text' => $ocrResult['raw_text'],
                'mean_confidence' => $ocrResult['mean_confidence'],
                'run_status' => 'processed',
                'processed_at' => now(),
                'error_message' => null,
            ]);

            $ocrRun->refresh();
            $parsed = $gradeParsingService->persistParsedData($ocrRun);
            $ocrRun->refresh();
            $ocrRun->update($validationService->validate($document->fresh(['student.user', 'academicPeriod']), $ocrRun));

            $evaluation = $ruleEngine->evaluateDocument(
                $document->fresh(),
                $ocrRun->fresh('parsedSummary')
            );

            $document->update([
                'review_status' => $parsed['parser_status'] === 'parsed'
                    && $ocrRun->fresh()->validation_status === 'valid'
                    && in_array($evaluation?->status, ['eligible', 'ineligible'], true)
                    ? AcademicDocument::REVIEW_STATUS_AUTO_PROCESSED
                    : AcademicDocument::REVIEW_STATUS_NEEDS_REVIEW,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);

            if ($parsed['parser_status'] !== 'parsed') {
                $ocrRun->update([
                    'run_status' => 'needs_review',
                ]);
            }

            $teamPlayerStatuses->syncStudent((int) $document->student_id);
        } catch (Throwable $e) {
            $failure = $this->failurePayload($e);

            $ocrRun->update([
                'run_status' => 'failed',
                'validation_status' => 'manual_review',
                'validation_summary' => $failure['summary'],
                'validation_flags' => [[
                    'code' => $failure['code'],
                    'message' => mb_substr($e->getMessage(), 0, 255),
                ]],
                'validation_checked_at' => now(),
                'processed_at' => now(),
                'error_message' => mb_substr($e->getMessage(), 0, 65535),
            ]);

            $document->update([
                'review_status' => AcademicDocument::REVIEW_STATUS_NEEDS_REVIEW,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);
        }
    }

    /**
     * @return array{code:string,summary:string}
     */
    private function failurePayload(Throwable $e): array
    {
        $message = mb_strtolower(trim($e->getMessage()));

        if (
            str_contains($message, 'ocr binary') ||
            str_contains($message, 'not available on this server') ||
            str_contains($message, 'command not found')
        ) {
            return [
                'code' => 'ocr_service_unavailable',
                'summary' => 'Automatic scan is currently unavailable on the server. Please try again later or contact an administrator.',
            ];
        }

        if (
            str_contains($message, 'no ocr text was extracted') ||
            str_contains($message, 'could not read any text') ||
            str_contains($message, 'empty page') ||
            str_contains($message, 'image too small')
        ) {
            return [
                'code' => 'ocr_unreadable_document',
                'summary' => 'The uploaded image could not be read clearly. Please upload a sharper photo or a clearer scanned copy.',
            ];
        }

        return [
            'code' => 'ocr_failed',
            'summary' => 'Automatic scan could not complete for this document. Please review the file and try another upload if needed.',
        ];
    }
}
