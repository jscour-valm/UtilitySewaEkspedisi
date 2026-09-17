<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sesi_detail_kiriman_rutin — Line item per pengajuan Kiriman Rutin.
 * `harga_satuan` & `subtotal` adalah snapshot yang dikunci saat submit,
 * tidak mengikuti perubahan `sesi_tarif_kiriman_rutin` setelahnya.
 *
 * `id_tarif_kiriman_rutin` nullable: diisi kalau baris ini pakai tarif resmi
 * yang sudah terdaftar (buat audit trail); NULL kalau harganya ad-hoc/manual
 * (jenis barang belum punya tarif resmi utk vendor ini). Submit pengajuan
 * TIDAK PERNAH membuat row baru di sesi_tarif_kiriman_rutin — tarif resmi
 * cuma didaftarkan manual lewat halaman Kelola Tarif Kiriman Rutin.
 * `id_jenis_barang` selalu diisi supaya baris tetap tau barang apa yang
 * diajukan terlepas dari ada/tidaknya tarif resmi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('sesi_detail_kiriman_rutin')) {
            Schema::connection('sqlsrv')->create('sesi_detail_kiriman_rutin', function (Blueprint $table) {
                $table->id('id_detail_kiriman');
                $table->unsignedBigInteger('id_pengajuan_sewa');
                $table->unsignedBigInteger('id_jenis_barang')->comment('Barang yang diajukan, selalu terisi');
                $table->unsignedBigInteger('id_tarif_kiriman_rutin')->nullable()->comment('FK ke tarif resmi kalau ada; NULL kalau harga ad-hoc');
                $table->decimal('quantity', 12, 2)->comment('Qty dalam unit (koli, ikat, dll)');
                $table->decimal('harga_satuan', 12, 2)->comment('Snapshot harga saat submit — locked');
                $table->decimal('subtotal', 12, 2)->comment('qty × harga_satuan — locked');
                $table->boolean('flag')->default(true)->comment('soft delete');
                $table->timestamp('created_at')->nullable();

                $table->foreign('id_pengajuan_sewa')
                    ->references('id_pengajuan_sewa')
                    ->on('sesi_pengajuan_sewa');

                $table->foreign('id_jenis_barang')
                    ->references('id_jenis_barang')
                    ->on('sesi_jenis_barang_kiriman');

                $table->foreign('id_tarif_kiriman_rutin')
                    ->references('id_tarif')
                    ->on('sesi_tarif_kiriman_rutin');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_detail_kiriman_rutin');
    }
};
