<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection('sqlsrv');

        // 1. Drop existing PRIMARY KEY constraint pada id_master_skill (SQL Server — nama constraint auto-generated, jadi cari dinamis)
        $connection->statement("
            DECLARE @constraintName NVARCHAR(MAX);
            DECLARE @sql NVARCHAR(MAX) = '';

            SELECT @constraintName = name
            FROM sys.key_constraints
            WHERE parent_object_id = OBJECT_ID(N'sesi_master_skill')
            AND type = 'PK';

            IF @constraintName IS NOT NULL
            BEGIN
                SET @sql = 'ALTER TABLE sesi_master_skill DROP CONSTRAINT ' + QUOTENAME(@constraintName);
                EXEC(@sql);
            END
        ");

        // 2. Drop UNIQUE constraint pada nama_skill (jika ada — dibuat auto dari ->unique() di migration create)
        $connection->statement("
            DECLARE @constraintName NVARCHAR(MAX);
            DECLARE @sql NVARCHAR(MAX) = '';

            SELECT @constraintName = name
            FROM sys.key_constraints
            WHERE parent_object_id = OBJECT_ID(N'sesi_master_skill')
            AND type = 'UQ';

            IF @constraintName IS NOT NULL
            BEGIN
                SET @sql = 'ALTER TABLE sesi_master_skill DROP CONSTRAINT ' + QUOTENAME(@constraintName);
                EXEC(@sql);
            END
        ");

        Schema::connection('sqlsrv')->table('sesi_master_skill', function (Blueprint $table) {
            // 3. Drop kolom id_master_skill (sekarang sudah tidak ada PK constraint yang reference-nya)
            $table->dropColumn('id_master_skill');
        });

        // 4. Add PRIMARY KEY constraint baru di nama_skill
        $connection->statement('ALTER TABLE sesi_master_skill ADD CONSTRAINT PK_sesi_master_skill PRIMARY KEY (nama_skill)');
    }

    public function down(): void
    {
        $connection = DB::connection('sqlsrv');

        // Reverse: drop PK di nama_skill
        $connection->statement('ALTER TABLE sesi_master_skill DROP CONSTRAINT PK_sesi_master_skill');

        Schema::connection('sqlsrv')->table('sesi_master_skill', function (Blueprint $table) {
            // Re-add id_master_skill sebagai identity PK (autoincrement)
            $table->id('id_master_skill');
        });

        // Re-add UNIQUE constraint di nama_skill
        $connection->statement('ALTER TABLE sesi_master_skill ADD CONSTRAINT UQ_sesi_master_skill_nama_skill UNIQUE (nama_skill)');
    }
};
