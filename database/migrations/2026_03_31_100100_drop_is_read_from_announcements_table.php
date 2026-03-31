<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $name): bool
    {
        $dbName = Schema::getConnection()->getDatabaseName();
        $rows = DB::select(
            "SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = 'announcements' AND index_name = ? LIMIT 1",
            [$dbName, $name]
        );

        return !empty($rows);
    }

    private function ensureIndex(string $name, string $sql): void
    {
        if (!$this->indexExists($name)) {
            DB::statement($sql);
        }
    }

    private function dropIndexIfExists(string $name): void
    {
        if ($this->indexExists($name)) {
            DB::statement("DROP INDEX {$name} ON announcements");
        }
    }

    public function up(): void
    {
        if (!Schema::hasTable('announcements')) {
            return;
        }

        $this->ensureIndex(
            'announcements_user_read_at_index',
            'CREATE INDEX announcements_user_read_at_index ON announcements (user_id, read_at)'
        );
        $this->ensureIndex(
            'announcements_user_readat_published_index',
            'CREATE INDEX announcements_user_readat_published_index ON announcements (user_id, read_at, published_at)'
        );

        $this->dropIndexIfExists('announcements_user_read_index');
        $this->dropIndexIfExists('announcements_user_read_published_index');

        if (Schema::hasColumn('announcements', 'is_read')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->dropColumn('is_read');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('announcements')) {
            return;
        }

        $this->dropIndexIfExists('announcements_user_read_at_index');
        $this->dropIndexIfExists('announcements_user_readat_published_index');

        if (!Schema::hasColumn('announcements', 'is_read')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->boolean('is_read')->default(false)->after('type');
            });
        }

        $this->ensureIndex(
            'announcements_user_read_index',
            'CREATE INDEX announcements_user_read_index ON announcements (user_id, is_read)'
        );
        $this->ensureIndex(
            'announcements_user_read_published_index',
            'CREATE INDEX announcements_user_read_published_index ON announcements (user_id, is_read, published_at)'
        );
    }
};
