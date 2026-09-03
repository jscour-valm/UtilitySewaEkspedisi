<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = DB::connection('sqlsrv');

        // Drop the existing primary key constraint
        $connection->statement('ALTER TABLE sesi_master_skill DROP CONSTRAINT PK_sesi_master_skill');

        // Rename the column from nama_skill to id_skill
        $connection->statement("EXEC sp_rename 'sesi_master_skill.nama_skill', 'id_skill', 'COLUMN'");

        // Re-add the primary key constraint on the new column name
        $connection->statement('ALTER TABLE sesi_master_skill ADD CONSTRAINT PK_sesi_master_skill PRIMARY KEY (id_skill)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = DB::connection('sqlsrv');

        // Drop the primary key constraint
        $connection->statement('ALTER TABLE sesi_master_skill DROP CONSTRAINT PK_sesi_master_skill');

        // Rename the column back from id_skill to nama_skill
        $connection->statement("EXEC sp_rename 'sesi_master_skill.id_skill', 'nama_skill', 'COLUMN'");

        // Re-add the primary key constraint on the old column name
        $connection->statement('ALTER TABLE sesi_master_skill ADD CONSTRAINT PK_sesi_master_skill PRIMARY KEY (nama_skill)');
    }
};
