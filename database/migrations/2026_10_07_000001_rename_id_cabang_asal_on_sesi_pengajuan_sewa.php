<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom cabang PAC menyimpan cabang tujuan barang, bukan cabang asal.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rename('id_cabang_asal', 'id_cabang_tujuan');
    }

    public function down(): void
    {
        $this->rename('id_cabang_tujuan', 'id_cabang_asal');
    }

    private function rename(string $dari, string $ke): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasColumn('sesi_pengajuan_sewa', $dari) && ! $schema->hasColumn('sesi_pengajuan_sewa', $ke)) {
            DB::connection('sqlsrv')->statement(
                "EXEC sp_rename 'dbo.sesi_pengajuan_sewa.{$dari}', '{$ke}', 'COLUMN'"
            );
        }
    }
};
