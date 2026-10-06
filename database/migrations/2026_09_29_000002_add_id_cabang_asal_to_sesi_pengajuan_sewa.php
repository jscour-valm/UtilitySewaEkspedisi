<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PAC (Pengiriman Antar Cabang) — mentor review item 5-7 (29 Sept 2026).
 *
 * `id_cabang` yang sudah ada dipertahankan maknanya sbg cabang PEMINTA/tujuan
 * (auto dari cabang login KG, readonly di form) — TIDAK di-rename krn dipakai
 * puluhan tempat di seluruh app. Kolom baru `id_cabang_asal` = cabang ASAL
 * barang, cuma wajib diisi kalau tujuan_penyewaan=PAC (divalidasi di
 * controller, bukan di level DB — kolom tetap nullable krn Toko tidak butuh).
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
