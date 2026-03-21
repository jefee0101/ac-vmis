<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('message');
            $table->enum('type', ['approval', 'general', 'academic', 'schedule', 'system'])->default('general');
            $table->boolean('is_read')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'is_read'], 'announcements_user_read_index');
            $table->index(['type', 'published_at'], 'announcements_type_published_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};

