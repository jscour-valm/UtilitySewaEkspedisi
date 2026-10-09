<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom usulan harga master versi awal (flag & status usulan di pengajuan / detail barang, harga
 * sebelumnya di master). Proses usulan sekarang memakai tabel `sesi_usulan_harga`
 * (migration 2026_10_09_000002); kolom `usulan_status` di sini hanya disinkronkan untuk tampilan lama.
 *
 * `usulan_decided_by` TANPA FK constraint ke lntrn_users — pola sama kayak
 * `submitted_by`/`id_approver` di tabel lain (tabel users itu external/IT-owned).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('sqlsrv')->hasColumn('sesi_pengajuan_sewa', 'usulan_harga_sewa')) {
            Schema::connection('sqlsrv')->table('sesi_pengajuan_sewa', function (Blueprint $table) {
                $table->boolean('usulan_harga_sewa')->default(false)->comment('KG usul harga_sewa pengajuan ini jadi harga master baru (cuma relevan jenis_pengajuan=sewa_truk)');
                $table->string('usulan_status', 20)->nullable()->comment('null = gak ada usulan; pending/approved/rejected');
                $table->unsignedBigInteger('usulan_decided_by')->nullable()->comment('user id WM/WH yang decide, tanpa FK ke external lntrn_users');
                $table->timestamp('usulan_decided_at')->nullable();
            });
        }

        if (! Schema::connection('sqlsrv')->hasColumn('sesi_detail_kiriman_rutin', 'usulan_update_master')) {
            Schema::connection('sqlsrv')->table('sesi_detail_kiriman_rutin', function (Blueprint $table) {
                $table->boolean('usulan_update_master')->default(false)->comment('KG usul biaya_per_unit baris ini jadi harga master baru — independen per baris/jenis barang');
                $table->string('usulan_status', 20)->nullable();
                $table->unsignedBigInteger('usulan_decided_by')->nullable();
                $table->timestamp('usulan_decided_at')->nullable();
            });
        }

        if (! Schema::connection('sqlsrv')->hasColumn('sesi_perusahaan_skill', 'harga_sewa_sebelumnya')) {
            Schema::connection('sqlsrv')->table('sesi_perusahaan_skill', function (Blueprint $table) {
                $table->decimal('harga_sewa_sebelumnya', 15, 2)->nullable()->comment('Snapshot harga_sewa SEBELUM di-apply usulan — buat nampilin before/after di halaman approval');
            });
        }

        if (! Schema::connection('sqlsrv')->hasColumn('sesi_tarif_kiriman_rutin', 'harga_sebelumnya')) {
            Schema::connection('sqlsrv')->table('sesi_tarif_kiriman_rutin', function (Blueprint $table) {
                $table->decimal('harga_sebelumnya', 12, 2)->nullable()->comment('Snapshot biaya_per_unit SEBELUM di-apply usulan');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('sesi_pengajuan_sewa', function (Blueprint $table) {
            $table->dropColumn(['usulan_harga_sewa', 'usulan_status', 'usulan_decided_by', 'usulan_decided_at']);
        });
        Schema::connection('sqlsrv')->table('sesi_detail_kiriman_rutin', function (Blueprint $table) {
            $table->dropColumn(['usulan_update_master', 'usulan_status', 'usulan_decided_by', 'usulan_decided_at']);
        });
        Schema::connection('sqlsrv')->table('sesi_perusahaan_skill', function (Blueprint $table) {
            $table->dropColumn('harga_sewa_sebelumnya');
        });
        Schema::connection('sqlsrv')->table('sesi_tarif_kiriman_rutin', function (Blueprint $table) {
            $table->dropColumn('harga_sebelumnya');
        });
    }
};
