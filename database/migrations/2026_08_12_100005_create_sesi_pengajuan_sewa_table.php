<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sesi_pengajuan_sewa — Master pengajuan sewa (Sewa Truk & Kiriman Rutin).
 *
 * Riwayat: `id_armada` di-rename ke `id_kendaraan` (ikut rename tabel
 * sesi_armada→sesi_unit_kendaraan), `id_skill` diperlebar ke varchar(100)
 * untuk multi-skill comma-separated, dan `jenis_pengajuan` ditambahkan
 * untuk membedakan alur Sewa Truk vs Kiriman Rutin (3 Sept 2026).
 *
 * Kolom `id_perusahaan_ekspedisi` (dipakai flow Kiriman Rutin) sudah ada
 * di database sejak fitur Kiriman Rutin dibuat, tapi sempat tidak
 * tercatat lewat migration file (ter-ALTER manual). Dimasukkan di sini
 * supaya fresh install ke depannya konsisten dengan DB yang sudah jalan.
 *
 * Migration ini sudah di-squash sehingga langsung mencerminkan schema
 * final; riwayat perubahan bertahapnya ada di git history.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('sesi_pengajuan_sewa')) {
            Schema::connection('sqlsrv')->create('sesi_pengajuan_sewa', function (Blueprint $table) {
                $table->id('id_pengajuan_sewa');
                $table->unsignedBigInteger('id_kendaraan')->nullable()->comment('diisi kalau jenis_pengajuan = sewa_truk');
                $table->unsignedBigInteger('id_perusahaan_ekspedisi')->nullable()->comment('diisi kalau jenis_pengajuan = pengiriman_rutin');
                $table->enum('jenis_pengajuan', ['sewa_truk', 'pengiriman_rutin'])->default('sewa_truk');
                $table->string('id_cabang', 10)->comment('cabang_code, e.g. 01A');
                $table->date('tanggal_pengiriman');
                $table->decimal('value_muatan', 15, 2)->nullable()->comment('dari dokumen SJ/TO-ACB');
                $table->decimal('harga_sewa', 15, 2);
                $table->decimal('rasio_sewa', 5, 2)->nullable()->comment('computed: harga_sewa / value_muatan * 100');
                $table->enum('kategori_approval', ['normal', 'over_threshold'])->comment('locked saat submit');
                $table->enum('tujuan_penyewaan', ['Toko', 'PAC']);
                $table->string('id_skill', 100)->comment('kode skill area, e.g. ACKOT (bisa multi, dipisah koma)');
                $table->string('kategori_toko', 20)->nullable()->comment('DK, LKM, LKTM, EKS, dll');
                $table->enum('status_pengajuan', ['Pending', 'Approved', 'Rejected'])->default('Pending');
                $table->unsignedBigInteger('current_approval_rule_id')->nullable();
                $table->text('catatan_pengajuan')->nullable();
                $table->unsignedBigInteger('submitted_by')->comment('user id KaGud yang submit');
                $table->timestamp('submitted_at')->useCurrent();
                $table->boolean('flag')->default(true);
                $table->timestamps();

                $table->foreign('id_kendaraan')->references('id_kendaraan')->on('sesi_unit_kendaraan');
                $table->foreign('id_perusahaan_ekspedisi')->references('id_perusahaan')->on('sesi_perusahaan_ekspedisi');
                $table->index(['id_cabang', 'status_pengajuan']);
                $table->index(['submitted_by']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_pengajuan_sewa');
    }
};
