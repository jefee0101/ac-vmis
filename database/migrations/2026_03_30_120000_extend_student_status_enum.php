<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE students MODIFY student_status ENUM('Enrolled','Dropped','Graduated','Suspended','Unenrolled') NOT NULL DEFAULT 'Enrolled'");
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE students MODIFY student_status ENUM('Enrolled','Dropped','Graduated') NOT NULL DEFAULT 'Enrolled'");
    }
};
