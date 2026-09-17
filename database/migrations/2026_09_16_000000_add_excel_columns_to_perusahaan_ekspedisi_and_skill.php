<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom yang ada di CSV Sewa Truk asli tapi sebelumnya didiskon
 * pas import (KTP/NPWP, Revisi, Tanggal Revisi, Update Date) — Jo minta
 * halaman Kelola Tarif nampilin kolom yang persis sama kayak Excel sumbernya.
 *
 * Kolom "No", "Kode Area", "Nama Cabang" TIDAK ditambahkan ke sini:
 * - "No" cuma nomor urut baris file sumber, ga ada makna bisnis, disepakati
 *   tidak disimpan/ditampilkan.
 * - "Kode Area" & "Nama Cabang" ternyata BISA di-join dari cabang_code yang
 *   sudah tersimpan, ke tabel eksternal sesi_master_cabang (kolom Area &
 *   Name) — jadi ga perlu kolom baru buat 2 itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasColumn('sesi_perusahaan_ekspedisi', 'ktp_npwp')) {
            Schema::connection('sqlsrv')->table('sesi_perusahaan_ekspedisi', function (Blueprint $table) {
                $table->string('ktp_npwp', 100)->nullable()->after('badan_usaha')->comment('Dari CSV Sewa Truk — identitas vendor, cuma ada di sumber Sewa Truk');
            });
        }

        if (!Schema::connection('sqlsrv')->hasColumn('sesi_perusahaan_skill', 'revisi')) {
            Schema::connection('sqlsrv')->table('sesi_perusahaan_skill', function (Blueprint $table) {
                $table->string('revisi', 50)->nullable()->comment('Kolom "Revisi" dari CSV Sewa Truk');
                $table->date('tanggal_revisi')->nullable()->comment('Kolom "Tanggal Revisi" dari CSV Sewa Truk');
                $table->date('update_date_source')->nullable()->comment('Kolom "Update Date" asli CSV Sewa Truk — beda dari updated_at Laravel (yang isinya "End Date")');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('sesi_perusahaan_ekspedisi', function (Blueprint $table) {
            $table->dropColumn('ktp_npwp');
        });
        Schema::connection('sqlsrv')->table('sesi_perusahaan_skill', function (Blueprint $table) {
            $table->dropColumn(['revisi', 'tanggal_revisi', 'update_date_source']);
        });
    }
};
