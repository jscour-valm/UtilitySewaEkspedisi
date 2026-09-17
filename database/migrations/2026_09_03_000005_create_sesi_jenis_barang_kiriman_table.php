<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sesi_jenis_barang_kiriman — Master jenis barang untuk fitur Kiriman Rutin
 * (pola sama seperti sesi_jenis_biaya). Dipindah ke posisi paling awal
 * di grup migration Kiriman Rutin karena sesi_tarif_kiriman_rutin
 * butuh tabel ini lebih dulu untuk foreign key-nya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('sesi_jenis_barang_kiriman')) {
            Schema::connection('sqlsrv')->create('sesi_jenis_barang_kiriman', function (Blueprint $table) {
                $table->id('id_jenis_barang');
                $table->string('nama_barang', 150)->unique();
                $table->boolean('flag')->default(true)->comment('soft delete');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_jenis_barang_kiriman');
    }
};
