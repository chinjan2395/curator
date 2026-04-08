<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE social_credentials MODIFY access_token TEXT NOT NULL');
        DB::statement('ALTER TABLE social_credentials MODIFY refresh_token TEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE social_credentials MODIFY access_token VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE social_credentials MODIFY refresh_token VARCHAR(255) NULL');
    }
};
