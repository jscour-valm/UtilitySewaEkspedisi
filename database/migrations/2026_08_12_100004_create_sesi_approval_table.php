<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sesi_approval — Konfigurasi rule approval per cabang/tingkat.
 *
 * Riwayat: `id_cabang` awalnya unsignedBigInteger, diubah ke string(50)
 * (cabang_code) 26 Aug 2026 di migration yang sama dengan penambahan
 * `id_approver` dan penghapusan kolom `kategori_approval` (WH approval
 * otomatis over_threshold, jadi kolom itu tidak diperlukan lagi).
 * Migration ini sudah di-squash sehingga langsung mencerminkan schema
 * final.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('sesi_approval')) {
            Schema::connection('sqlsrv')->create('sesi_approval', function (Blueprint $table) {
                $table->id('id_approval_rule');
                $table->string('id_cabang', 50)->nullable()->comment('cabang_code; NULL = global rule');
                $table->unsignedTinyInteger('tingkat')->comment('1=level 1 (WM), 2=level 2 (WH), etc');
                $table->enum('role_berwenang', ['WM', 'WH']);
                $table->unsignedBigInteger('id_approver')->nullable()->comment('approver utama, tidak ada FK ke external lntrn_users');
                $table->unsignedBigInteger('id_approver_cadangan')->nullable()->comment('backup approver user id');
                $table->boolean('flag')->default(true);
                $table->timestamps();

                // Note: id_cabang & id_approver/id_approver_cadangan sengaja tidak ada FK
                // karena reference-nya ke sesi_master_cabang / lntrn_users (external/IT-owned)
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_approval');
    }
};
