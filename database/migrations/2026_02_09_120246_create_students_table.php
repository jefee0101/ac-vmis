<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();

            // Link to users (login/account)
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->unique('user_id');

            // Student identity
            $table->string('student_id_number')->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');

            // Personal info
            $table->date('date_of_birth');
            $table->enum('gender', ['Male', 'Female', 'Other']);
            $table->text('home_address');

            // Academic info
            $table->string('course');
            $table->string('current_grade_level');

            // Status
            $table->enum('student_status', ['Enrolled', 'Dropped', 'Graduated', 'Suspended', 'Unenrolled'])
                ->default('Enrolled');

            // Contact info
            $table->string('phone_number');

            // Emergency contact
            $table->string('emergency_contact_name');
            $table->string('emergency_contact_relationship');
            $table->string('emergency_contact_phone');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
