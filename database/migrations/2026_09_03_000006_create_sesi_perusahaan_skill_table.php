<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sesi_perusahaan_skill — patokan harga_sewa truk per vendor + skill/area +
 * cabang. Menggantikan pola lama "rate-card" (baris sesi_unit_kendaraan
 * dengan plat_nomor_truk NULL yang comma-CSV banyak skill jadi 1 baris) —
 * di sini 1 baris = 1 kombinasi vendor+skill+cabang, jadi query-nya nggak
 * perlu whereRaw LIKE lagi buat cari skill yang cocok.
 *
 * Selaras sama "master tabel_updated.pdf" (Sesi_Perusahaan_Skill), TAPI
 * kolom `cabang_code` ditambahkan meski nggak ada di dokumen itu — soalnya
 * ImportTarifSewaTrukCommand baca "Kode Cabang" dari CSV dan 1 vendor bisa
 * punya harga beda per cabang meski skill/area-nya sama. Tanpa cabang_code,
 * baris vendor+skill sama dari cabang berbeda bakal saling timpa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('sesi_perusahaan_skill')) {
            Schema::connection('sqlsrv')->create('sesi_perusahaan_skill', function (Blueprint $table) {
                $table->id('id_vendor_skill');
                $table->unsignedBigInteger('id_perusahaan');
                $table->unsignedBigInteger('id_skill');
                $table->string('cabang_code', 50)->nullable()->comment('cabang yang berlaku utk harga ini; beda cabang bisa beda harga meski skill sama');
                $table->decimal('harga_sewa', 15, 2)->nullable()->comment('Patokan harga sewa truk vendor ini utk skill+cabang ini');
                $table->boolean('flag')->default(true);
                $table->timestamps();

                $table->foreign('id_perusahaan')->references('id_perusahaan')->on('sesi_perusahaan_ekspedisi');
                $table->foreign('id_skill')->references('id_skill')->on('sesi_master_skill');

                // 1 vendor + 1 skill/area + 1 cabang = 1 patokan harga
                $table->unique(['id_perusahaan', 'id_skill', 'cabang_code'], 'uk_perusahaan_skill_cabang');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_perusahaan_skill');
    }
};
