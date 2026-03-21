<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\AccountPendingApprovalMail;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Student;
use App\Models\Coach;
use App\Models\AthleteHealthClearance;
use App\Models\AcademicDocument;
use App\Services\SecureUploadService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RegisterController extends Controller
{
    public function __construct(private SecureUploadService $secureUpload)
    {
    }

    public function registerStudentAthlete(Request $request)
    {
        // --- Validation ---
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
            'student_id_number' => 'required|unique:students,student_id_number',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'date_of_birth' => 'required|date',
            'gender' => 'required|in:Male,Female,Other',
            'phone_number' => 'required|string|max:30',
            'education_level' => 'required|in:Senior High,College',
            'year_level' => 'required|in:11,12,1,2,3,4',
            'height' => 'required|numeric|min:50|max:260',
            'weight' => 'required|numeric|min:20|max:300',
            'avatar' => 'nullable|image|max:2048',
            'clearance_date' => 'required|date',
            'valid_until' => 'nullable|date|after_or_equal:clearance_date',
            'physician_name' => 'required|string|max:255',
            'conditions' => 'nullable|string',
            'allergies' => 'nullable|string',
            'restrictions' => 'nullable|string',
            'clearance_status' => 'required|in:fit,fit_with_restrictions,not_fit,expired',
            'medical_certificate' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'academic_document_type' => 'required|in:tor,grade_report,other',
            'academic_document_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'academic_document_notes' => 'nullable|string',
        ]);

        try {
            $user = DB::transaction(function () use ($request) {
                // --- Create User ---
                $user = $this->createUser($request);

                // --- Create Student ---
                $student = $this->createStudent($request, $user);

                // --- Create initial health clearance record ---
                $this->createInitialHealthClearance($request, $student);

                // --- Create initial academic document ---
                $this->createInitialAcademicDocument($request, $student, $user);

                return $user;
            });

            $this->sendPendingApprovalMail($user);

            // --- Redirect on success ---
            return inertia('Status/PendingApproval', [
                'successMessage' => 'Student-Athlete registered successfully.'
            ]);
        } catch (\Exception $e) {
            // --- Catch errors and display ---
            return back()->withErrors(['error' => 'Registration failed: ' . $e->getMessage()])
                ->withInput();
        }
    }

    private function createInitialHealthClearance(Request $request, Student $student): AthleteHealthClearance
    {
        $certificatePath = null;
        if ($request->hasFile('medical_certificate')) {
            $certificatePath = $this->secureUpload->storePublic(
                $request->file('medical_certificate'),
                'medical_certificates',
                'medical_certificate'
            );
        }

        return AthleteHealthClearance::create([
            'student_id' => $student->id,
            'clearance_date' => $request->clearance_date,
            'valid_until' => $request->valid_until ?: null,
            'physician_name' => $request->physician_name,
            'conditions' => $request->conditions,
            'allergies' => $request->allergies,
            'restrictions' => $request->restrictions,
            'clearance_status' => $request->clearance_status,
            'certificate_path' => $certificatePath,
        ]);
    }

    private function createInitialAcademicDocument(Request $request, Student $student, User $user): AcademicDocument
    {
        $filePath = null;
        if ($request->hasFile('academic_document_file')) {
            $filePath = $this->secureUpload->storePublic(
                $request->file('academic_document_file'),
                'academic_documents',
                'academic_document'
            );
        }

        return AcademicDocument::create([
            'student_id' => $student->id,
            'document_type' => $request->academic_document_type,
            'academic_period_id' => null,
            'file_path' => $filePath,
            'uploaded_by' => $user->id,
            'uploaded_at' => now(),
            'notes' => $request->academic_document_notes,
        ]);
    }
    // --- Private Helper Methods ---
    private function createUser(Request $request): User
    {
        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $this->secureUpload->storePublic(
                $request->file('avatar'),
                'avatars',
                'avatar'
            );
        }

        return User::create([
            'name' => $request->first_name . ' ' . $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'student-athlete',
            'status' => 'pending',
            'avatar' => $avatarPath,
        ]);
    }

    private function createStudent(Request $request, User $user): Student
    {
        try {
            return Student::create([
                'user_id' => $user->id,
                'student_id_number' => $request->student_id_number,
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name,
                'last_name' => $request->last_name,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
                'home_address' => $request->home_address,
                'course' => $request->education_level,
                'education_level' => $request->education_level,
                'current_grade_level' => $request->year_level,
                'year_level' => $request->year_level,
                'student_status' => 'Enrolled',
                'phone_number' => $request->phone_number,
                'height' => $request->height,
                'weight' => $request->weight,
                'emergency_contact_name' => $request->emergency_contact_name,
                'emergency_contact_relationship' => $request->emergency_contact_relationship,
                'emergency_contact_phone' => $request->emergency_contact_phone,
            ]);
        } catch (\Exception $e) {
            throw new \Exception('Failed to create Student record: ' . $e->getMessage());
        }
    }

    public function checkStudentIdAvailability(Request $request)
    {
        $validated = $request->validate([
            'student_id_number' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{6,20}$/'],
        ]);

        $exists = Student::query()
            ->where('student_id_number', $validated['student_id_number'])
            ->exists();

        return response()->json([
            'available' => !$exists,
        ]);
    }

    public function registerCoach(Request $request)
    {
        // --- Validation ---
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'phone_number' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|string',
            'home_address' => 'nullable|string',
        ]);

        try {
            $user = DB::transaction(function () use ($request) {
                $avatarPath = null;
                if ($request->hasFile('avatar')) {
                    $avatarPath = $this->secureUpload->storePublic(
                        $request->file('avatar'),
                        'avatars',
                        'avatar'
                    );
                }

                // --- Create User ---
                $user = User::create([
                    'name' => $request->first_name . ' ' . $request->last_name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'role' => 'coach',
                    'status' => 'pending',
                    'avatar' => $avatarPath,
                ]);

                // --- Create Coach ---
                $coach = Coach::create([
                    'user_id' => $user->id, // link to the user
                    'first_name' => $request->first_name,
                    'middle_name' => $request->middle_name,
                    'last_name' => $request->last_name,
                    'phone_number' => $request->phone_number,
                    'date_of_birth' => $request->date_of_birth,
                    'gender' => $request->gender,
                    'home_address' => $request->home_address,
                    'coach_status' => 'Active',
                ]);

                return $user;
            });

            $this->sendPendingApprovalMail($user);


            // --- Redirect with success ---
            return inertia('Status/PendingApproval', [
                'successMessage' => 'Coach registered successfully.'
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Registration failed: ' . $e->getMessage()])->withInput();
        }
    }

    private function sendPendingApprovalMail(User $user): void
    {
        try {
            Mail::to($user->email)->send(new AccountPendingApprovalMail($user));
        } catch (\Throwable $e) {
            // Keep registration successful even when mail transport is unavailable.
            // Log only concise diagnostics to avoid noisy SMTP traces.
            Log::notice('Pending approval email not sent. Check mail credentials/settings.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'reason' => $e->getCode() ?: 'smtp_auth_or_transport',
            ]);
        }
    }
}
