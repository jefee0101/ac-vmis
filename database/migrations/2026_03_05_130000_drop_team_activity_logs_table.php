<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('team_activity_logs');
    }

    public function down(): void
    {
        // Intentionally left empty; team activity logs were removed from the product.
    }
};
