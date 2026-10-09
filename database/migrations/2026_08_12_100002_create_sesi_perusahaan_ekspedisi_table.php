<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sesi_perusahaan_ekspedisi — Perusahaan/vendor ekspedisi mitra sewa.
 *
 * Enum `badan_usaha`: PT / CV / UD / Perseorangan, plus `-` khusus placeholder impor CSV kalau
 * data sumbernya tidak valid (import:tarif-sewa-truk & import:tarif-kiriman-rutin-wide) — form
 * tambah vendor tetap wajib memilih salah satu dari 4 nilai asli. `identitas_owner` = JSON array
 * path foto identitas owner. Migration ini sudah di-squash (schema final).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('sqlsrv')->hasTable('sesi_perusahaan_ekspedisi')) {
            Schema::connection('sqlsrv')->create('sesi_perusahaan_ekspedisi', function (Blueprint $table) {
                $table->id('id_perusahaan');
                $table->string('nama_perusahaan', 255);
                $table->enum('badan_usaha', ['PT', 'CV', 'UD', 'Perseorangan', '-']);
                $table->string('no_telepon', 20);
                $table->text('alamat_kantor');
                $table->longText('identitas_owner')->nullable()->comment('JSON array of base64 files (KTP/NPWP/SIM owner)');
                $table->boolean('flag')->default(true);
                $table->timestamps();

                $table->unique('nama_perusahaan');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_perusahaan_ekspedisi');
    }
};
