<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sesi_master_jenis_kendaraan — Master lookup jenis kendaraan (mis. "Truk Box",
 * "CDD", "Pickup"), tiap entri sekalian nge-lock muatan_maksimal-nya (ton).
 * Sebelumnya `sesi_unit_kendaraan.jenis_kendaraan` cuma teks bebas + muatan
 * diisi manual sendiri-sendiri per kendaraan — rawan typo/nggak konsisten &
 * gampang salah isi muatan.
 *
 * CRUD entri: DCI; WM/WC/WH hanya melihat (lihat routes/web.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('sqlsrv')->hasTable('sesi_master_jenis_kendaraan')) {
            Schema::connection('sqlsrv')->create('sesi_master_jenis_kendaraan', function (Blueprint $table) {
                $table->id('id_jenis_kendaraan');
                $table->string('nama_jenis', 100)->unique();
                $table->decimal('muatan_maksimal_ton', 8, 2);
                $table->boolean('flag')->default(true);
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_master_jenis_kendaraan');
    }
};
