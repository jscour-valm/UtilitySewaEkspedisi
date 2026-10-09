<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sesi_usulan_harga — usulan perubahan harga master (KG → validasi WM → approval WH),
 * terpisah dari pengajuan sewa. Target tarif disimpan sebagai kunci alami
 * (perusahaan + skill + cabang [+ jenis barang]) karena tarifnya bisa belum ada
 * di master; id_vendor_skill / id_tarif terisi kalau sudah ada atau setelah diterapkan.
 *
 * submitted_by tanpa FK ke lntrn_users (tabel eksternal IT).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasTable('sesi_usulan_harga')) {
            $schema->create('sesi_usulan_harga', function (Blueprint $table) {
                $table->id('id_usulan_harga');
                $table->string('jenis', 20)->comment('sewa_truk | pengiriman_rutin');
                $table->unsignedBigInteger('id_perusahaan');
                $table->unsignedBigInteger('id_skill');
                $table->string('cabang_code', 10);
                $table->unsignedBigInteger('id_jenis_barang')->nullable()->comment('Kiriman Rutin saja');
                $table->unsignedBigInteger('id_vendor_skill')->nullable();
                $table->unsignedBigInteger('id_tarif')->nullable()->comment('null = barang belum punya tarif');
                $table->decimal('harga_lama', 15, 2)->nullable();
                $table->decimal('harga_usulan', 15, 2);
                $table->string('sumber', 20)->comment('pengajuan | perusahaan');
                $table->string('catatan', 1000)->nullable();
                $table->string('status', 30)->default('menunggu_validasi')
                    ->comment('menunggu_validasi | menunggu_approval | approved | rejected');
                $table->string('id_cabang_pengaju', 10);
                $table->unsignedBigInteger('submitted_by');
                $table->dateTime('submitted_at');
                $table->string('alasan_penolakan', 500)->nullable();
                $table->boolean('flag')->default(true);
                $table->timestamps();

                $table->foreign('id_perusahaan')->references('id_perusahaan')->on('sesi_perusahaan_ekspedisi');
                $table->foreign('id_vendor_skill')->references('id_vendor_skill')->on('sesi_perusahaan_skill');
                $table->foreign('id_tarif')->references('id_tarif')->on('sesi_tarif_kiriman_rutin');
                $table->foreign('id_jenis_barang')->references('id_jenis_barang')->on('sesi_jenis_barang_kiriman');
                $table->index(['id_perusahaan', 'id_skill', 'cabang_code', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_usulan_harga');
    }
};
