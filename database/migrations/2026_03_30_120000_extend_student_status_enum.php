<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE students MODIFY student_status ENUM('Enrolled','Dropped','Graduated','Suspended','Unenrolled') NOT NULL DEFAULT 'Enrolled'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE students MODIFY student_status ENUM('Enrolled','Dropped','Graduated') NOT NULL DEFAULT 'Enrolled'");
    }
};
