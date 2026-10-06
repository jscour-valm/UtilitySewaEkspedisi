<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah FK id_jenis_kendaraan (nullable) ke sesi_unit_kendaraan — kolom teks
 * bebas `jenis_kendaraan` TETAP dipertahankan (tidak dihapus): data lama tidak
 * di-backfill otomatis (0 baris aktif punya isi teks bebas per pengecekan 29
 * Sept, jadi tidak ada yang perlu dipetakan), dan kolom lama masih dipakai
 * sebagai fallback tampilan kalau id_jenis_kendaraan kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('sqlsrv')->hasColumn('sesi_unit_kendaraan', 'id_jenis_kendaraan')) {
            Schema::connection('sqlsrv')->table('sesi_unit_kendaraan', function (Blueprint $table) {
                $table->unsignedBigInteger('id_jenis_kendaraan')->nullable()->after('jenis_kendaraan');
                $table->foreign('id_jenis_kendaraan')->references('id_jenis_kendaraan')->on('sesi_master_jenis_kendaraan');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('sesi_unit_kendaraan', function (Blueprint $table) {
            $table->dropForeign(['id_jenis_kendaraan']);
            $table->dropColumn('id_jenis_kendaraan');
        });
    }
};
