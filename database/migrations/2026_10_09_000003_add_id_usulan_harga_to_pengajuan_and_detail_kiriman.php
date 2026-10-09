<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hubungan pengajuan sewa → usulan harga master yang ikut diajukan:
 * Sewa Truk di sesi_pengajuan_sewa, Kiriman Rutin per barang di sesi_detail_kiriman_rutin.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        foreach (['sesi_pengajuan_sewa', 'sesi_detail_kiriman_rutin'] as $tabel) {
            if (! $schema->hasColumn($tabel, 'id_usulan_harga')) {
                $schema->table($tabel, function (Blueprint $table) {
                    $table->unsignedBigInteger('id_usulan_harga')->nullable();
                    $table->foreign('id_usulan_harga')->references('id_usulan_harga')->on('sesi_usulan_harga');
                });
            }
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        foreach (['sesi_pengajuan_sewa', 'sesi_detail_kiriman_rutin'] as $tabel) {
            $schema->table($tabel, function (Blueprint $table) {
                $table->dropForeign(['id_usulan_harga']);
                $table->dropColumn('id_usulan_harga');
            });
        }
    }
};
