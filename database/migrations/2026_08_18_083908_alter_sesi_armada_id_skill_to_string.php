<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('sqlsrv')->statement('ALTER TABLE sesi_armada ALTER COLUMN id_skill VARCHAR(50) NOT NULL');
    }

    public function down(): void
    {
        DB::connection('sqlsrv')->statement('ALTER TABLE sesi_armada ALTER COLUMN id_skill INT NOT NULL');
    }
};
