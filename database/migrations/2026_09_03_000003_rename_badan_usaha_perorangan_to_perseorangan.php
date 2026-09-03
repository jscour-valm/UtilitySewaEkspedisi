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

        // Drop existing CHECK constraint
        $connection->statement('ALTER TABLE sesi_perusahaan_ekspedisi DROP CONSTRAINT [CK__sesi_peru__badan__647F1302]');

        // Backfill existing data
        $connection->statement("UPDATE sesi_perusahaan_ekspedisi SET badan_usaha = 'Perseorangan' WHERE badan_usaha = 'Perorangan'");

        // Add new CHECK constraint
        $connection->statement("
            ALTER TABLE sesi_perusahaan_ekspedisi
            ADD CONSTRAINT [CK_sesi_perusahaan_ekspedisi_badan_usaha]
            CHECK (badan_usaha IN ('PT','CV','UD','Perseorangan'))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = DB::connection('sqlsrv');

        // Drop new constraint
        $connection->statement('ALTER TABLE sesi_perusahaan_ekspedisi DROP CONSTRAINT [CK_sesi_perusahaan_ekspedisi_badan_usaha]');

        // Backfill data back
        $connection->statement("UPDATE sesi_perusahaan_ekspedisi SET badan_usaha = 'Perorangan' WHERE badan_usaha = 'Perseorangan'");

        // Restore old constraint
        $connection->statement("
            ALTER TABLE sesi_perusahaan_ekspedisi
            ADD CONSTRAINT [CK__sesi_peru__badan__647F1302]
            CHECK (badan_usaha IN ('PT','CV','UD','Perorangan'))
        ");
    }
};
