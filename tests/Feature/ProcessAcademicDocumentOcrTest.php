<?php

use App\Jobs\ProcessAcademicDocumentOcr;
use App\Models\AcademicDocument;
use App\Models\AcademicDocumentOcrRun;
use App\Models\AcademicDocumentType;
use App\Models\AcademicEligibilityEvaluation;
use App\Models\AcademicPeriod;
use App\Models\Student;
use App\Models\User;
use App\Services\OcrService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

function makeOcrPipelineFixture(string $email = 'ocr-pipeline@example.com'): AcademicDocument
{
    $user = User::query()->create([
        'first_name' => 'OCR',
        'last_name' => 'Pipeline',
        'email' => $email,
        'password' => Hash::make('password'),
        'role' => 'student-athlete',
        'account_state' => 'active',
    ]);

    $student = Student::query()->create([
        'user_id' => $user->id,
        'student_id_number' => '2026-0002',
        'first_name' => 'OCR',
        'last_name' => 'Pipeline',
        'date_of_birth' => '2005-11-20',
        'gender' => 'Female',
        'home_address' => 'Sample Address',
        'course_or_strand' => 'BSCS',
        'current_grade_level' => '1',
        'approval_status' => 'approved',
        'student_status' => 'Enrolled',
        'phone_number' => '09170000002',
        'emergency_contact_name' => 'Parent Pipeline',
        'emergency_contact_relationship' => 'Parent',
        'emergency_contact_phone' => '09179990002',
    ]);

    $period = AcademicPeriod::query()->create([
        'school_year' => '2026-2027',
        'term' => '2nd_sem',
        'starts_on' => '2026-04-01',
        'ends_on' => '2026-06-30',
    ]);

    $documentType = AcademicDocumentType::query()->firstOrCreate([
        'context' => AcademicDocumentType::CONTEXT_PERIOD_SUBMISSION,
        'code' => AcademicDocumentType::CODE_GRADE_REPORT,
    ], [
        'label' => 'Grade Report',
    ]);

    Storage::fake('public');
    Storage::disk('public')->put('academic_documents/pipeline.png', 'fake image payload');

    return AcademicDocument::query()->create([
        'student_id' => $student->id,
        'document_type_id' => $documentType->id,
        'academic_period_id' => $period->id,
        'file_path' => 'academic_documents/pipeline.png',
        'uploaded_by' => $user->id,
        'uploaded_at' => now(),
        'review_status' => 'pending',
    ]);
}

it('runs the OCR pipeline and immediately classifies clear eligible grades', function () {
    $document = makeOcrPipelineFixture('ocr-eligible@example.com');

    $mock = \Mockery::mock(OcrService::class);
    $mock->shouldReceive('extractText')->once()->andReturn([
        'engine' => 'tesseract',
        'engine_version' => 'tesseract 5.5.0',
        'raw_text' => implode("\n", [
            'MATH101 College Algebra 3.0 1.75 PASSED',
            'ENG102 Communication Skills 3.0 2.00 PASSED',
            'GWA: 1.88',
        ]),
        'mean_confidence' => 93.0,
    ]);
    app()->instance(OcrService::class, $mock);

    app(ProcessAcademicDocumentOcr::class, ['academicDocumentId' => $document->id])->handle(
        app(OcrService::class),
        app(\App\Services\GradeParsingService::class),
        app(\App\Services\AcademicDocumentValidationService::class),
        app(\App\Services\AcademicEligibilityRuleEngine::class),
    );

    $document->refresh();
    $ocrRun = AcademicDocumentOcrRun::query()->where('academic_document_id', $document->id)->latest('id')->firstOrFail();
    $evaluation = AcademicEligibilityEvaluation::query()
        ->where('student_id', $document->student_id)
        ->where('academic_period_id', $document->academic_period_id)
        ->firstOrFail();

    expect($ocrRun->run_status)->toBe('processed')
        ->and($ocrRun->validation_status)->toBe('valid')
        ->and($document->review_status)->toBe('auto_processed')
        ->and($document->reviewed_by)->toBeNull()
        ->and($document->reviewed_at)->toBeNull()
        ->and((float) $evaluation->gpa)->toBe(1.88)
        ->and($evaluation->status)->toBe('eligible')
        ->and($evaluation->review_required)->toBeFalse();
});

it('keeps unclear OCR outcomes in manual review instead of auto-approving them', function () {
    $document = makeOcrPipelineFixture('ocr-review@example.com');

    $mock = \Mockery::mock(OcrService::class);
    $mock->shouldReceive('extractText')->once()->andReturn([
        'engine' => 'tesseract',
        'engine_version' => 'tesseract 5.5.0',
        'raw_text' => implode("\n", [
            'REPORT OF GRADES',
            'Final Grade 4.00',
        ]),
        'mean_confidence' => 71.0,
    ]);
    app()->instance(OcrService::class, $mock);

    app(ProcessAcademicDocumentOcr::class, ['academicDocumentId' => $document->id])->handle(
        app(OcrService::class),
        app(\App\Services\GradeParsingService::class),
        app(\App\Services\AcademicDocumentValidationService::class),
        app(\App\Services\AcademicEligibilityRuleEngine::class),
    );

    $document->refresh();
    $evaluation = AcademicEligibilityEvaluation::query()
        ->where('student_id', $document->student_id)
        ->where('academic_period_id', $document->academic_period_id)
        ->first();

    expect($document->review_status)->toBe('needs_review')
        ->and($evaluation?->status)->toBe('pending_review')
        ->and($evaluation?->review_required)->toBeTrue();
});

it('flags low-confidence OCR runs for manual review even when a grade is extracted', function () {
    $document = makeOcrPipelineFixture('ocr-low-confidence@example.com');

    $mock = \Mockery::mock(OcrService::class);
    $mock->shouldReceive('extractText')->once()->andReturn([
        'engine' => 'tesseract',
        'engine_version' => 'tesseract 5.5.0',
        'raw_text' => 'General Weighted Average: 1.88',
        'mean_confidence' => 52.0,
    ]);
    app()->instance(OcrService::class, $mock);

    app(ProcessAcademicDocumentOcr::class, ['academicDocumentId' => $document->id])->handle(
        app(OcrService::class),
        app(\App\Services\GradeParsingService::class),
        app(\App\Services\AcademicDocumentValidationService::class),
        app(\App\Services\AcademicEligibilityRuleEngine::class),
    );

    $document->refresh();
    $ocrRun = AcademicDocumentOcrRun::query()->where('academic_document_id', $document->id)->latest('id')->firstOrFail();
    $evaluation = AcademicEligibilityEvaluation::query()
        ->where('student_id', $document->student_id)
        ->where('academic_period_id', $document->academic_period_id)
        ->firstOrFail();

    expect($ocrRun->validation_status)->toBe('manual_review')
        ->and($ocrRun->validation_flags)->toBeArray()
        ->and($ocrRun->validation_flags[0]['code'] ?? null)->toBe('ocr_low_confidence')
        ->and($document->review_status)->toBe('needs_review')
        ->and($evaluation->status)->toBe('eligible');
});
