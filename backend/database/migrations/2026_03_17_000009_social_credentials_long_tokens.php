<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE social_credentials ALTER COLUMN access_token TYPE TEXT');
            DB::statement('ALTER TABLE social_credentials ALTER COLUMN access_token SET NOT NULL');
            DB::statement('ALTER TABLE social_credentials ALTER COLUMN refresh_token TYPE TEXT');
            DB::statement('ALTER TABLE social_credentials ALTER COLUMN refresh_token DROP NOT NULL');
            return;
        }

        DB::statement('ALTER TABLE social_credentials MODIFY access_token TEXT NOT NULL');
        DB::statement('ALTER TABLE social_credentials MODIFY refresh_token TEXT NULL');
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE social_credentials ALTER COLUMN access_token TYPE VARCHAR(255)');
            DB::statement('ALTER TABLE social_credentials ALTER COLUMN access_token SET NOT NULL');
            DB::statement('ALTER TABLE social_credentials ALTER COLUMN refresh_token TYPE VARCHAR(255)');
            DB::statement('ALTER TABLE social_credentials ALTER COLUMN refresh_token DROP NOT NULL');
            return;
        }

        DB::statement('ALTER TABLE social_credentials MODIFY access_token VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE social_credentials MODIFY refresh_token VARCHAR(255) NULL');
    }
};
