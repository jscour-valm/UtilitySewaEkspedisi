<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kode pengajuan untuk tampilan & pencarian: SE-{cabang}-{yyMM}-{urut 4 digit}, urut per cabang per
 * bulan (mis. SE-01A-2610-0007). id_pengajuan_sewa tetap jadi primary key/relasi/URL.
 * Baris yang sudah ada diberi kode berurutan menurut tanggal dibuat. Unique index difilter
 * (kode NULL boleh lebih dari satu) supaya aman sebelum/selama pengisian.
 */
return new class extends Migration
{
    private const TABEL = 'sesi_pengajuan_sewa';

    private const INDEX = 'sesi_pengajuan_sewa_kode_pengajuan_unique';

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        $db = DB::connection('sqlsrv');

        if (! $schema->hasColumn(self::TABEL, 'kode_pengajuan')) {
            $schema->table(self::TABEL, function (Blueprint $table) {
                $table->string('kode_pengajuan', 30)->nullable()->comment('SE-{cabang}-{yyMM}-{urut}, urut per cabang per bulan');
            });
        }

        $db->statement("
            WITH urut AS (
                SELECT kode_pengajuan, id_cabang,
                       FORMAT(COALESCE(created_at, submitted_at), 'yyMM') AS bulan,
                       ROW_NUMBER() OVER (
                           PARTITION BY id_cabang, FORMAT(COALESCE(created_at, submitted_at), 'yyMM')
                           ORDER BY COALESCE(created_at, submitted_at), id_pengajuan_sewa
                       ) AS nomor
                FROM ".self::TABEL."
                WHERE kode_pengajuan IS NULL AND id_cabang IS NOT NULL AND COALESCE(created_at, submitted_at) IS NOT NULL
            )
            UPDATE urut SET kode_pengajuan = CONCAT('SE-', id_cabang, '-', bulan, '-', FORMAT(nomor, '0000'))
        ");

        $adaIndex = $db->selectOne('SELECT 1 AS ada FROM sys.indexes WHERE name = ? AND object_id = OBJECT_ID(?)', [self::INDEX, self::TABEL]);
        if (! $adaIndex) {
            $db->statement('CREATE UNIQUE INDEX '.self::INDEX.' ON '.self::TABEL.' (kode_pengajuan) WHERE kode_pengajuan IS NOT NULL');
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        $db = DB::connection('sqlsrv');

        if ($db->selectOne('SELECT 1 AS ada FROM sys.indexes WHERE name = ? AND object_id = OBJECT_ID(?)', [self::INDEX, self::TABEL])) {
            $db->statement('DROP INDEX '.self::INDEX.' ON '.self::TABEL);
        }
        if ($schema->hasColumn(self::TABEL, 'kode_pengajuan')) {
            $schema->table(self::TABEL, fn (Blueprint $table) => $table->dropColumn('kode_pengajuan'));
        }
    }
};
