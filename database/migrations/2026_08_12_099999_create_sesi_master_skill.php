<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sesi_master_skill — Master area pengiriman (skill), diturunkan dari
 * kolom `skills` di tabel IT `Q_CustomerLocusAtribute`.
 *
 * Riwayat: awalnya PK-nya integer `id_master_skill` dengan kolom
 * `nama_skill` unique. Direfactor 26 Aug jadi natural key (nama_skill
 * sebagai PK, di-rename ke `id_skill` 3 Sept). 9 Sept: DIBALIK lagi ke
 * numeric auto-increment (keputusan Jo) — `id_skill` BIGINT identity,
 * `nama_skill` jadi kolom nama biasa (unique, bukan PK). Tabel lain yang
 * *mereferensikan banyak skill sekaligus* (`sesi_unit_kendaraan.id_skill`,
 * `sesi_pengajuan_sewa.id_skill`) TETAP varchar comma-separated seperti
 * sebelumnya — isinya cuma diganti dari nama jadi id numeric ini, bukan
 * ikut jadi FK scalar (lihat Task 14 di plan). `sesi_cabang_skill.id_skill`
 * (1 skill per baris) JADI FK bigint langsung ke sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('sesi_master_skill')) {
            Schema::connection('sqlsrv')->create('sesi_master_skill', function (Blueprint $table) {
                $table->id('id_skill');
                $table->string('nama_skill', 100)->unique();
                $table->boolean('flag')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_master_skill');
    }
};
