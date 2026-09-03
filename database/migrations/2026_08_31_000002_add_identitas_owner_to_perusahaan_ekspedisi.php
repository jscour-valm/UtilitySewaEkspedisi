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
        Schema::connection('sqlsrv')->table('sesi_perusahaan_ekspedisi', function (Blueprint $table) {
            // Tambah kolom identitas_owner untuk menyimpan KTP/NPWP/SIM owner (JSON array base64)
            $table->longText('identitas_owner')->nullable()->comment('JSON array of base64 files (KTP/NPWP/SIM owner)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->table('sesi_perusahaan_ekspedisi', function (Blueprint $table) {
            $table->dropColumn('identitas_owner');
        });
    }
};
