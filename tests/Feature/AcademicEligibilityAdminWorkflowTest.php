<?php

use App\Models\AcademicDocument;
use App\Models\AcademicDocumentType;
use App\Models\AcademicEligibilityEvaluation;
use App\Models\AcademicPeriod;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function makeAdminAcademicWorkflowFixture(string $suffix = 'admin-workflow'): array
{
    $admin = User::query()->create([
        'first_name' => 'Admin',
        'last_name' => 'Reviewer',
        'email' => "{$suffix}-admin@example.com",
        'password' => Hash::make('password'),
        'role' => 'admin',
        'account_state' => 'active',
    ]);

    $studentUser = User::query()->create([
        'first_name' => 'Student',
        'last_name' => 'Athlete',
        'email' => "{$suffix}-student@example.com",
        'password' => Hash::make('password'),
        'role' => 'student-athlete',
        'account_state' => 'active',
    ]);

    $student = Student::query()->create([
        'user_id' => $studentUser->id,
        'student_id_number' => '2026-0101',
        'first_name' => 'Student',
        'last_name' => 'Athlete',
        'date_of_birth' => '2005-11-20',
        'gender' => 'Female',
        'home_address' => 'Sample Address',
        'course_or_strand' => 'BSCS',
        'current_grade_level' => '1',
        'approval_status' => 'approved',
        'student_status' => 'Enrolled',
        'phone_number' => '09170000010',
        'emergency_contact_name' => 'Parent Athlete',
        'emergency_contact_relationship' => 'Parent',
        'emergency_contact_phone' => '09179990010',
    ]);

    $period = AcademicPeriod::query()->create([
        'school_year' => '2026-2027',
        'term' => '1st_sem',
        'starts_on' => '2026-04-01',
        'ends_on' => '2026-06-30',
    ]);

    $documentType = AcademicDocumentType::query()->firstOrCreate([
        'context' => AcademicDocumentType::CONTEXT_PERIOD_SUBMISSION,
        'code' => AcademicDocumentType::CODE_GRADE_REPORT,
    ], [
        'label' => 'Grade Report',
    ]);

    $document = AcademicDocument::query()->create([
        'student_id' => $student->id,
        'document_type_id' => $documentType->id,
        'academic_period_id' => $period->id,
        'file_path' => 'academic_documents/admin-workflow.png',
        'uploaded_by' => $studentUser->id,
        'uploaded_at' => now(),
        'review_status' => 'pending',
    ]);

    return compact('admin', 'student', 'period', 'document');
}

it('returns admin submission records with evaluation metadata without crashing', function () {
    ['admin' => $admin, 'student' => $student, 'period' => $period, 'document' => $document] = makeAdminAcademicWorkflowFixture('records');

    AcademicEligibilityEvaluation::query()->create([
        'student_id' => $student->id,
        'academic_period_id' => $period->id,
        'document_id' => $document->id,
        'gpa' => 1.88,
        'evaluation_source' => 'manual',
        'final_status' => 'eligible',
        'review_required' => false,
        'evaluated_by' => $admin->id,
        'evaluated_at' => now(),
        'remarks' => 'Cleared manually.',
    ]);

    $response = $this
        ->actingAs($admin)
        ->getJson("/academics/submissions/records?period_id={$period->id}");

    $response->assertOk()
        ->assertJsonPath('data.0.student_id', $student->id)
        ->assertJsonPath('data.0.evaluation.status', 'eligible')
        ->assertJsonPath('data.0.evaluation.evaluation_source', 'manual')
        ->assertJsonPath('data.0.evaluation.review_required', false);
});

it('syncs manual override decisions back to the academic document review state', function () {
    ['admin' => $admin, 'student' => $student, 'period' => $period, 'document' => $document] = makeAdminAcademicWorkflowFixture('override');

    $response = $this
        ->actingAs($admin)
        ->putJson("/academics/evaluations/{$student->id}/{$period->id}", [
            'document_id' => $document->id,
            'gpa' => 1.75,
            'remarks' => 'Verified against registrar copy.',
            'audit_note' => 'Manual override after OCR mismatch.',
        ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Evaluation updated.');

    $document->refresh();
    $evaluation = AcademicEligibilityEvaluation::query()
        ->where('student_id', $student->id)
        ->where('academic_period_id', $period->id)
        ->firstOrFail();

    expect($evaluation->status)->toBe('eligible')
        ->and($evaluation->evaluation_source)->toBe('manual')
        ->and($document->review_status)->toBe('reviewed')
        ->and($document->reviewed_by)->toBe($admin->id)
        ->and($document->reviewed_at)->not->toBeNull();
});
