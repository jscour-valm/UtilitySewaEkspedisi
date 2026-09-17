<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sesi_tarif_kiriman_rutin — Tarif per vendor+skill/area+cabang
 * (sesi_perusahaan_skill) per jenis barang untuk fitur Kiriman Rutin.
 *
 * Riwayat (squash): FK-nya sempat 3x ganti bentuk — awalnya langsung ke
 * `id_perusahaan_ekspedisi` (Task 10, tarif cuma per vendor+barang, ternyata
 * kekurangan krn data asli bisa beda harga per Area Kirim), lalu ke
 * `id_kendaraan` (9 Sept, ke sesi_unit_kendaraan — row "rate card" yang bawa
 * vendor+cabang+area comma-CSV sekaligus), lalu ke `id_vendor_skill` (14
 * Sept, ke sesi_perusahaan_skill — 1 baris = 1 vendor+skill+cabang, bukan
 * comma-CSV lagi). Migration ini sudah di-squash sehingga langsung
 * mencerminkan schema final; riwayat bertahapnya ada di git history.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('sesi_tarif_kiriman_rutin')) {
            Schema::connection('sqlsrv')->create('sesi_tarif_kiriman_rutin', function (Blueprint $table) {
                $table->id('id_tarif');
                $table->unsignedBigInteger('id_vendor_skill')->comment('FK ke sesi_perusahaan_skill (vendor+skill/area+cabang)');
                $table->unsignedBigInteger('id_jenis_barang');
                $table->decimal('biaya_per_unit', 12, 2);
                $table->boolean('flag')->default(true)->comment('soft delete');
                $table->timestamps();

                $table->foreign('id_vendor_skill')
                    ->references('id_vendor_skill')
                    ->on('sesi_perusahaan_skill');

                $table->foreign('id_jenis_barang')
                    ->references('id_jenis_barang')
                    ->on('sesi_jenis_barang_kiriman');

                // 1 vendor+skill/area+cabang + 1 jenis barang = 1 tarif
                $table->unique(['id_vendor_skill', 'id_jenis_barang'], 'uk_tarif_fk');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_tarif_kiriman_rutin');
    }
};
