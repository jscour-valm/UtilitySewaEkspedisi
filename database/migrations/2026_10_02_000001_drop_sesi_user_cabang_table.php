<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mapping user→cabang tidak butuh tabel terpisah. `sesi_user_cabang` dihapus:
 * - KG/KA: full-covered `lntrn_users.branch_id` (+ fallback derive dari
 *   prefix username kalau NULL) — lihat UserCabangResolver::liveBranchId().
 * - WM: override manual (sebelumnya nempel di tabel ini) DISATUKAN ke
 *   `sesi_approval` yang sudah ada (role_berwenang='WM', id_approver=user id)
 *   — lihat UserCabangResolver::wmOverrideCabangIds() & ApproverController.
 *
 * Isi tabel saat dihapus hanya override KG yang sudah tertutup fallback username (tanpa baris
 * WM aktif), jadi tidak ada data yang perlu dipindahkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_user_cabang');
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->create('sesi_user_cabang', function (Blueprint $table) {
            $table->id('id_user_cabang');
            $table->string('username', 255);
            $table->string('cabang_code', 50)->nullable();
            $table->string('area', 50)->nullable();
            $table->string('role', 10)->nullable();
            $table->boolean('flag')->default(true);
            $table->timestamps();

            $table->unique(['username', 'cabang_code']);
        });
    }
};
