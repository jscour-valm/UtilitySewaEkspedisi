<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('sesi_armada', function (Blueprint $table) {
            // Make nama_kendaraan nullable
            $table->string('nama_kendaraan', 100)->nullable()->change();
        });

        // For plat_nomor, we need to handle the unique constraint on SQL Server
        // Use raw SQL to drop the unique constraint and recreate with nullable support
        DB::connection('sqlsrv')->statement('
            IF EXISTS (SELECT name FROM sys.indexes WHERE name = \'sesi_armada_plat_nomor_unique\')
            DROP INDEX sesi_armada_plat_nomor_unique ON sesi_armada
        ');

        // Now make plat_nomor nullable
        DB::connection('sqlsrv')->statement('
            ALTER TABLE sesi_armada ALTER COLUMN plat_nomor VARCHAR(20) NULL
        ');

        // Re-add unique constraint but filtered to non-NULL values
        // In SQL Server, this is done by creating a filtered unique index
        DB::connection('sqlsrv')->statement('
            CREATE UNIQUE INDEX UNIQUE_sesi_armada_plat_nomor_filtered
            ON sesi_armada(plat_nomor)
            WHERE plat_nomor IS NOT NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the filtered unique index
        DB::connection('sqlsrv')->statement('
            IF EXISTS (SELECT name FROM sys.indexes WHERE name = \'UNIQUE_sesi_armada_plat_nomor_filtered\')
            DROP INDEX UNIQUE_sesi_armada_plat_nomor_filtered ON sesi_armada
        ');

        Schema::connection('sqlsrv')->table('sesi_armada', function (Blueprint $table) {
            // Revert nama_kendaraan to NOT NULL
            $table->string('nama_kendaraan', 100)->change();
        });

        // Revert plat_nomor to NOT NULL
        DB::connection('sqlsrv')->statement('
            ALTER TABLE sesi_armada ALTER COLUMN plat_nomor VARCHAR(20) NOT NULL
        ');

        // Re-add the original unique constraint
        DB::connection('sqlsrv')->statement('
            CREATE UNIQUE INDEX sesi_armada_plat_nomor_unique ON sesi_armada(plat_nomor)
        ');
    }
};
