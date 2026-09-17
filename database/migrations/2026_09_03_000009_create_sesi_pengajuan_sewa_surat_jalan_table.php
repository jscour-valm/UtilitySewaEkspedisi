<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sesi_pengajuan_sewa_surat_jalan — Link reference-only ke dokumen Surat
 * Jalan di database Quantum. Bukan FK karena Q_SuratJalan ada di
 * database terpisah — validasi referensi dilakukan di application layer.
 * Zero-migration kalau nanti switch ke integrasi Quantum yang real.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('sesi_pengajuan_sewa_surat_jalan')) {
            Schema::connection('sqlsrv')->create('sesi_pengajuan_sewa_surat_jalan', function (Blueprint $table) {
                $table->id('id_pengajuan_sewa_sj');
                $table->unsignedBigInteger('id_pengajuan_sewa');
                $table->string('id_surat_jalan')->comment('Reference ke Q_SuratJalan.No_ — bukan FK');
                $table->boolean('flag')->default(true)->comment('soft delete');
                $table->timestamps();

                $table->foreign('id_pengajuan_sewa')
                    ->references('id_pengajuan_sewa')
                    ->on('sesi_pengajuan_sewa');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_pengajuan_sewa_surat_jalan');
    }
};
