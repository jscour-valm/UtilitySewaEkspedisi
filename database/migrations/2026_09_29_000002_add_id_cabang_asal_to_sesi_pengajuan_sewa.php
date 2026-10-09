<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PAC (Pengiriman Antar Cabang): kolom cabang kedua di pengajuan, wajib kalau
 * tujuan_penyewaan=PAC (divalidasi di controller; nullable karena Toko tidak butuh).
 * `id_cabang` tetap cabang KG pengaju. Kolom ini di-rename jadi `id_cabang_tujuan` oleh
 * migration 2026_10_07_000001.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('sqlsrv')->hasColumn('sesi_pengajuan_sewa', 'id_cabang_asal')) {
            Schema::connection('sqlsrv')->table('sesi_pengajuan_sewa', function (Blueprint $table) {
                $table->string('id_cabang_asal', 10)->nullable()->after('id_cabang')
                    ->comment('cabang_code asal barang, cuma diisi kalau tujuan_penyewaan=PAC');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('sesi_pengajuan_sewa', function (Blueprint $table) {
            $table->dropColumn('id_cabang_asal');
        });
    }
};
