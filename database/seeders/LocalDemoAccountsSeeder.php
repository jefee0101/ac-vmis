<?php

namespace Database\Seeders;

use App\Models\Coach;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;

class LocalDemoAccountsSeeder extends Seeder
{
    /**
     * Seed local demo accounts for each supported role.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@ac-vmis.local'],
            [
                'first_name' => 'Local',
                'middle_name' => null,
                'last_name' => 'Admin',
                'password' => 'password',
                'role' => 'admin',
                'status' => 'approved',
            ],
        );

        $coachUser = User::updateOrCreate(
            ['email' => 'coach@ac-vmis.local'],
            [
                'first_name' => 'Local',
                'middle_name' => null,
                'last_name' => 'Coach',
                'password' => 'password',
                'role' => 'coach',
                'status' => 'approved',
            ],
        );

        Coach::updateOrCreate(
            ['user_id' => $coachUser->id],
            [
                'phone_number' => '09170000001',
                'date_of_birth' => '1990-01-01',
                'gender' => 'Male',
                'home_address' => 'AC Campus',
                'coach_status' => 'Active',
            ],
        );

        $studentUser = User::updateOrCreate(
            ['email' => 'student@ac-vmis.local'],
            [
                'first_name' => 'Local',
                'middle_name' => null,
                'last_name' => 'Student',
                'password' => 'password',
                'role' => 'student-athlete',
                'status' => 'approved',
            ],
        );

        Student::updateOrCreate(
            ['user_id' => $studentUser->id],
            [
                'student_id_number' => '2026-0001',
                'date_of_birth' => '2006-01-01',
                'gender' => 'Male',
                'home_address' => 'AC Campus',
                'course_or_strand' => 'STEM',
                'current_grade_level' => 'Grade 12',
                'student_status' => 'Enrolled',
                'phone_number' => '09170000002',
                'emergency_contact_name' => 'Local Guardian',
                'emergency_contact_relationship' => 'Parent',
                'emergency_contact_phone' => '09170000003',
                'height' => 175,
                'weight' => 68,
            ],
        );

        $this->command?->info('Local demo accounts are ready: admin@ac-vmis.local, coach@ac-vmis.local, student@ac-vmis.local');
    }
}
