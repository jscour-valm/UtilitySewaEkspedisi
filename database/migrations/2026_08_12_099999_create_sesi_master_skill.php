<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sesi_master_skill — Master area pengiriman (skill), diturunkan dari
 * kolom `skills` di tabel IT `Q_CustomerLocusAtribute`.
 *
 * `id_skill` BIGINT identity, `nama_skill` unique (huruf besar). Kolom yang menyimpan banyak
 * skill sekaligus (`sesi_unit_kendaraan.id_skill`, `sesi_pengajuan_sewa.id_skill`) berupa
 * varchar berisi id dipisah koma, bukan FK. `sesi_cabang_skill.id_skill` (1 skill per baris)
 * FK bigint ke sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('sqlsrv')->hasTable('sesi_master_skill')) {
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
