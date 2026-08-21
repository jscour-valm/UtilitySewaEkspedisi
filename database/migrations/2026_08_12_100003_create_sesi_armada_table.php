<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('sesi_armada')) {
            Schema::connection('sqlsrv')->create('sesi_armada', function (Blueprint $table) {
                $table->id('id_armada');
                $table->unsignedBigInteger('id_perusahaan');
                $table->unsignedBigInteger('id_cabang')->comment('TODO: BLOCKING — ambil dari mapping user');
                $table->longText('ktp_supir')->nullable()->comment('base64 atau file path');
                $table->longText('sim_supir')->nullable();
                $table->unsignedInteger('id_skill')->comment('FK to Q_CustomerLocusAtribute (delivery area)');
                $table->string('nama_kendaraan', 100);
                $table->string('plat_nomor', 20)->unique();
                $table->decimal('muatan_maksimal', 8, 2)->comment('ton');
                $table->boolean('flag')->default(true);
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('id_perusahaan')->references('id_perusahaan')->on('sesi_perusahaan_ekspedisi');
                // Note: id_cabang tidak ada FK ke sesi_master_cabang karena tabel itu bukan milik app
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_armada');
    }
};
