<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * sesi_approval_log juga mencatat keputusan WM/WH atas vendor baru (id_perusahaan) dan
 * usulan harga master (id_usulan_harga). Log seperti itu tidak punya pengajuan sewa,
 * jadi id_pengajuan_sewa dijadikan NULLABLE (FK tetap ada). Data lama tidak berubah.
 */
return new class extends Migration
{
    private const FK_PENGAJUAN = 'sesi_approval_log_id_pengajuan_sewa_foreign';

    private const INDEX_PENGAJUAN = 'sesi_approval_log_id_pengajuan_sewa_index';

    public function up(): void
    {
        $db = DB::connection('sqlsrv');
        $schema = Schema::connection('sqlsrv');

        $kolom = $db->selectOne("SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'sesi_approval_log' AND COLUMN_NAME = 'id_pengajuan_sewa'");
        if ($kolom && $kolom->IS_NULLABLE === 'NO') {
            // Nama FK bawaan bisa berbeda; cari dari sys.foreign_keys berdasarkan kolomnya.
            $fk = $db->selectOne("
                SELECT fk.name FROM sys.foreign_keys fk
                JOIN sys.foreign_key_columns fkc ON fkc.constraint_object_id = fk.object_id
                JOIN sys.columns c ON c.object_id = fkc.parent_object_id AND c.column_id = fkc.parent_column_id
                WHERE fk.parent_object_id = OBJECT_ID('dbo.sesi_approval_log') AND c.name = 'id_pengajuan_sewa'
            ");
            if ($fk) {
                $db->statement("ALTER TABLE sesi_approval_log DROP CONSTRAINT [{$fk->name}]");
            }
            // ALTER COLUMN gagal kalau kolomnya masih dipakai index → index dilepas lalu dibuat ulang.
            $adaIndex = $db->selectOne("SELECT 1 AS ada FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.sesi_approval_log') AND name = ?", [self::INDEX_PENGAJUAN]);
            if ($adaIndex) {
                $db->statement('DROP INDEX '.self::INDEX_PENGAJUAN.' ON sesi_approval_log');
            }
            $db->statement('ALTER TABLE sesi_approval_log ALTER COLUMN id_pengajuan_sewa BIGINT NULL');
            $db->statement('CREATE INDEX '.self::INDEX_PENGAJUAN.' ON sesi_approval_log (id_pengajuan_sewa)');
            $db->statement('ALTER TABLE sesi_approval_log ADD CONSTRAINT '.self::FK_PENGAJUAN
                .' FOREIGN KEY (id_pengajuan_sewa) REFERENCES sesi_pengajuan_sewa (id_pengajuan_sewa)');
        }

        $schema->table('sesi_approval_log', function (Blueprint $table) use ($schema) {
            if (! $schema->hasColumn('sesi_approval_log', 'id_perusahaan')) {
                $table->unsignedBigInteger('id_perusahaan')->nullable();
                $table->foreign('id_perusahaan')->references('id_perusahaan')->on('sesi_perusahaan_ekspedisi');
            }
            if (! $schema->hasColumn('sesi_approval_log', 'id_usulan_harga')) {
                $table->unsignedBigInteger('id_usulan_harga')->nullable();
                $table->foreign('id_usulan_harga')->references('id_usulan_harga')->on('sesi_usulan_harga');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('sesi_approval_log', function (Blueprint $table) {
            $table->dropForeign(['id_perusahaan']);
            $table->dropForeign(['id_usulan_harga']);
            $table->dropColumn(['id_perusahaan', 'id_usulan_harga']);
        });
        // id_pengajuan_sewa sengaja tidak dikembalikan ke NOT NULL: log vendor/usulan akan melanggar.
    }
};
