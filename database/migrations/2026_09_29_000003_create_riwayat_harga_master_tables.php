<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histori harga master — 2 tabel log terpisah krn sumbernya beda tabel master (Sewa Truk vs
 * Kiriman Rutin). Append-only, diisi setiap harga master berubah: usulan harga yang disetujui WH
 * (PersetujuanMasterService) dan edit tarif langsung oleh DCI (TarifKirimanRutinController).
 * Kolom flat `harga_sewa_sebelumnya` / `harga_sebelumnya` hanya menyimpan nilai terakhir.
 *
 * `diubah_oleh` TANPA FK constraint ke lntrn_users — pola sama kayak
 * `submitted_by`/`usulan_decided_by` di tabel lain (users itu external/IT-owned).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('sqlsrv')->hasTable('sesi_riwayat_harga_sewa_truk')) {
            Schema::connection('sqlsrv')->create('sesi_riwayat_harga_sewa_truk', function (Blueprint $table) {
                $table->id('id_riwayat');
                $table->unsignedBigInteger('id_vendor_skill');
                $table->decimal('harga_lama', 15, 2)->nullable();
                $table->decimal('harga_baru', 15, 2);
                $table->timestamp('tanggal_perubahan')->useCurrent();
                $table->unsignedBigInteger('diubah_oleh')->nullable()->comment('user id, tanpa FK ke external lntrn_users');
                $table->boolean('flag')->default(true);

                $table->foreign('id_vendor_skill')->references('id_vendor_skill')->on('sesi_perusahaan_skill');
                $table->index(['id_vendor_skill', 'tanggal_perubahan']);
            });
        }

        if (! Schema::connection('sqlsrv')->hasTable('sesi_riwayat_tarif_kiriman_rutin')) {
            Schema::connection('sqlsrv')->create('sesi_riwayat_tarif_kiriman_rutin', function (Blueprint $table) {
                $table->id('id_riwayat');
                $table->unsignedBigInteger('id_tarif');
                $table->decimal('biaya_lama', 12, 2)->nullable();
                $table->decimal('biaya_baru', 12, 2);
                $table->timestamp('tanggal_perubahan')->useCurrent();
                $table->unsignedBigInteger('diubah_oleh')->nullable()->comment('user id, tanpa FK ke external lntrn_users');
                $table->boolean('flag')->default(true);

                $table->foreign('id_tarif')->references('id_tarif')->on('sesi_tarif_kiriman_rutin');
                $table->index(['id_tarif', 'tanggal_perubahan']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_riwayat_tarif_kiriman_rutin');
        Schema::connection('sqlsrv')->dropIfExists('sesi_riwayat_harga_sewa_truk');
    }
};
