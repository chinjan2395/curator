<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE posts MODIFY external_id TEXT NULL');
            DB::statement('ALTER TABLE posts MODIFY video_url TEXT NULL');
            DB::statement('ALTER TABLE posts MODIFY thumbnail_url TEXT NULL');

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE posts ALTER COLUMN external_id TYPE TEXT USING (external_id::TEXT)');
            DB::statement('ALTER TABLE posts ALTER COLUMN video_url TYPE TEXT USING (video_url::TEXT)');
            DB::statement('ALTER TABLE posts ALTER COLUMN thumbnail_url TYPE TEXT USING (thumbnail_url::TEXT)');

            return;
        }

        // SQLite (e.g. local testing): rebuild-style change not applied here; use MySQL in production.
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE posts MODIFY external_id VARCHAR(255) NULL');
            DB::statement('ALTER TABLE posts MODIFY video_url VARCHAR(255) NULL');
            DB::statement('ALTER TABLE posts MODIFY thumbnail_url VARCHAR(255) NULL');

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE posts ALTER COLUMN external_id TYPE VARCHAR(255) USING (LEFT(external_id::text, 255))');
            DB::statement('ALTER TABLE posts ALTER COLUMN video_url TYPE VARCHAR(255) USING (LEFT(video_url::text, 255))');
            DB::statement('ALTER TABLE posts ALTER COLUMN thumbnail_url TYPE VARCHAR(255) USING (LEFT(thumbnail_url::text, 255))');
        }
    }
};
