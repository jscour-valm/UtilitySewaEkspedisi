<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('sesi_biaya_tambahan')) {
            Schema::connection('sqlsrv')->create('sesi_biaya_tambahan', function (Blueprint $table) {
                $table->id('id_biaya_tambahan');
                $table->unsignedBigInteger('id_pengajuan_sewa');
                $table->unsignedBigInteger('id_jenis_biaya');
                $table->decimal('jumlah', 15, 2);
                $table->boolean('flag')->default(true);
                $table->timestamps();

                $table->foreign('id_pengajuan_sewa')->references('id_pengajuan_sewa')->on('sesi_pengajuan_sewa');
                $table->foreign('id_jenis_biaya')->references('id_jenis_biaya')->on('sesi_jenis_biaya');
                $table->index(['id_pengajuan_sewa']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_biaya_tambahan');
    }
};
