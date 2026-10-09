<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status persetujuan vendor baru (KG → validasi WM → approval WH).
 * Default `approved` supaya vendor yang sudah ada tetap dianggap disetujui.
 * `submitted_by` tanpa FK ke lntrn_users (tabel eksternal IT).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        $schema->table('sesi_perusahaan_ekspedisi', function (Blueprint $table) use ($schema) {
            if (! $schema->hasColumn('sesi_perusahaan_ekspedisi', 'status_approval')) {
                $table->string('status_approval', 30)->default('approved')
                    ->comment('menunggu_validasi | menunggu_approval | approved | rejected');
            }
            if (! $schema->hasColumn('sesi_perusahaan_ekspedisi', 'id_cabang_pengaju')) {
                $table->string('id_cabang_pengaju', 10)->nullable();
            }
            if (! $schema->hasColumn('sesi_perusahaan_ekspedisi', 'submitted_by')) {
                $table->unsignedBigInteger('submitted_by')->nullable();
            }
            if (! $schema->hasColumn('sesi_perusahaan_ekspedisi', 'submitted_at')) {
                $table->dateTime('submitted_at')->nullable();
            }
            if (! $schema->hasColumn('sesi_perusahaan_ekspedisi', 'alasan_penolakan')) {
                $table->string('alasan_penolakan', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('sesi_perusahaan_ekspedisi', function (Blueprint $table) {
            $table->dropColumn(['status_approval', 'id_cabang_pengaju', 'submitted_by', 'submitted_at', 'alasan_penolakan']);
        });
    }
};
