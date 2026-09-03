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

        // Drop filtered unique index dulu (nama kolom & index berubah)
        $connection->statement("
            IF EXISTS (SELECT name FROM sys.indexes WHERE name = 'UNIQUE_sesi_armada_plat_nomor_filtered')
            DROP INDEX UNIQUE_sesi_armada_plat_nomor_filtered ON sesi_armada
        ");

        // Rename table
        $connection->statement("EXEC sp_rename 'sesi_armada', 'sesi_unit_kendaraan'");

        // Rename 3 kolom di tabel yang sudah di-rename
        $connection->statement("EXEC sp_rename 'sesi_unit_kendaraan.id_armada', 'id_kendaraan', 'COLUMN'");
        $connection->statement("EXEC sp_rename 'sesi_unit_kendaraan.nama_kendaraan', 'jenis_kendaraan', 'COLUMN'");
        $connection->statement("EXEC sp_rename 'sesi_unit_kendaraan.plat_nomor', 'plat_nomor_truk', 'COLUMN'");

        // Rename kolom FK di tabel anak (sesi_pengajuan_sewa)
        $connection->statement("EXEC sp_rename 'sesi_pengajuan_sewa.id_armada', 'id_kendaraan', 'COLUMN'");

        // Recreate filtered unique index dengan nama kolom baru
        $connection->statement("
            CREATE UNIQUE INDEX UNIQUE_sesi_unit_kendaraan_plat_nomor_truk_filtered
            ON sesi_unit_kendaraan(plat_nomor_truk)
            WHERE plat_nomor_truk IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = DB::connection('sqlsrv');

        // Drop new index
        $connection->statement("
            IF EXISTS (SELECT name FROM sys.indexes WHERE name = 'UNIQUE_sesi_unit_kendaraan_plat_nomor_truk_filtered')
            DROP INDEX UNIQUE_sesi_unit_kendaraan_plat_nomor_truk_filtered ON sesi_unit_kendaraan
        ");

        // Rename kolom FK balik
        $connection->statement("EXEC sp_rename 'sesi_pengajuan_sewa.id_kendaraan', 'id_armada', 'COLUMN'");

        // Rename 3 kolom balik (urutan reverse dari up)
        $connection->statement("EXEC sp_rename 'sesi_unit_kendaraan.plat_nomor_truk', 'plat_nomor', 'COLUMN'");
        $connection->statement("EXEC sp_rename 'sesi_unit_kendaraan.jenis_kendaraan', 'nama_kendaraan', 'COLUMN'");
        $connection->statement("EXEC sp_rename 'sesi_unit_kendaraan.id_kendaraan', 'id_armada', 'COLUMN'");

        // Rename table balik
        $connection->statement("EXEC sp_rename 'sesi_unit_kendaraan', 'sesi_armada'");

        // Recreate old index
        $connection->statement("
            CREATE UNIQUE INDEX UNIQUE_sesi_armada_plat_nomor_filtered
            ON sesi_armada(plat_nomor)
            WHERE plat_nomor IS NOT NULL
        ");
    }
};
