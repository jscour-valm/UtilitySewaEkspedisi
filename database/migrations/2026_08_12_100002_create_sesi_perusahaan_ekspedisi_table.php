<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sesi_perusahaan_ekspedisi — Perusahaan/vendor ekspedisi mitra sewa.
 *
 * Riwayat: enum `badan_usaha` awalnya punya nilai "Perorangan", di-rename
 * jadi "Perseorangan" 3 Sept 2026 untuk selaras dokumentasi. Kolom
 * `identitas_owner` ditambahkan 31 Aug 2026. Nilai `-` ditambah ke enum
 * `badan_usaha` 9 Sept 2026, KHUSUS placeholder impor CSV kalau data
 * sumbernya ga valid/ga ada (lihat command import:tarif-sewa-truk &
 * import:tarif-kiriman-rutin-wide) — form tambah vendor manual tetap wajib
 * pilih salah satu dari 4 nilai asli, "-" ga pernah jadi opsi di form.
 * Migration ini sudah di-squash sehingga langsung mencerminkan schema final.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('sesi_perusahaan_ekspedisi')) {
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
