<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sesi_cabang_skill — Mapping cabang ↔ skill/area yang dilayani (1 skill
 * per baris, beda dari sesi_unit_kendaraan/sesi_pengajuan_sewa yang
 * comma-separated). `id_skill` FK bigint biasa ke sesi_master_skill —
 * SENGAJA bukan `$table->id()` (itu akan bikin kolom ini auto-increment
 * sendiri, bentrok sama composite PK + insert manual nilai FK dari
 * master, error "Cannot insert explicit value for identity column").
 * Reproducible dari seeder (CabangSkillSeeder ← Q_CustomerLocusAtribute
 * milik IT), aman di-drop+reseed ulang.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('sesi_cabang_skill')) {
            Schema::connection('sqlsrv')->create('sesi_cabang_skill', function (Blueprint $table) {
                $table->string('cabang_code', 10);
                $table->unsignedBigInteger('id_skill')->comment('FK ke sesi_master_skill.id_skill');
                $table->boolean('flag')->default(true);
                $table->timestamps();

                $table->primary(['cabang_code', 'id_skill']);
                $table->foreign('id_skill')->references('id_skill')->on('sesi_master_skill');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_cabang_skill');
    }
};