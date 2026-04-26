<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('wellness_attachments');
    }

    public function down(): void
    {
        Schema::create('wellness_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wellness_log_id')
                ->constrained('wellness_logs')
                ->cascadeOnDelete();
            $table->string('file_path');
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index('wellness_log_id');
            $table->index('uploaded_by');
        });
    }
};
