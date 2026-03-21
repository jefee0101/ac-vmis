<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('emergency_contact_name')->nullable()->change();
            $table->string('emergency_contact_relationship')->nullable()->change();
            $table->string('emergency_contact_phone')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Rollback-safe: keep nullable to avoid failing on legacy rows with null values.
            $table->string('emergency_contact_name')->nullable()->change();
            $table->string('emergency_contact_relationship')->nullable()->change();
            $table->string('emergency_contact_phone')->nullable()->change();
        });
    }
};
