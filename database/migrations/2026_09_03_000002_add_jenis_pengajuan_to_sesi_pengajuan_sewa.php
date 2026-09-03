<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('sesi_pengajuan_sewa', function (Blueprint $table) {
            $table->enum('jenis_pengajuan', ['sewa_truk', 'pengiriman_rutin'])
                ->default('sewa_truk')
                ->after('id_armada');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->table('sesi_pengajuan_sewa', function (Blueprint $table) {
            $table->dropColumn('jenis_pengajuan');
        });
    }
};
