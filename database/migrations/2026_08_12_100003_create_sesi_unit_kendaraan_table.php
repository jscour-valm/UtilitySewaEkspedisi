<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * sesi_unit_kendaraan — Armada/kendaraan milik perusahaan ekspedisi.
 *
 * Riwayat: tabel ini awalnya bernama `sesi_armada` dengan PK `id_armada`,
 * kolom `nama_kendaraan` & `plat_nomor`. Di-rename total (tabel + 3 kolom)
 * tanggal 3 Sept 2026 untuk selaras dengan dokumentasi. Migration ini sudah
 * di-squash sehingga langsung mencerminkan schema final — riwayat rename
 * bertahapnya ada di git history, bukan di file migration lagi.
 *
 * 8 Sept 2026: kolom `ktp_supir`/`sim_supir` DIHAPUS dari sini. Sudah ga
 * dipakai UI sejak 1 Sept (waktu `identitas_owner` ditambah ke
 * sesi_perusahaan_ekspedisi), dan datanya sudah dipindah ke situ lewat
 * migration `2026_09_08_000002_...`. Dokumen identitas sekarang disimpan
 * per-perusahaan (identitas_owner), bukan per-kendaraan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('sesi_unit_kendaraan')) {
            Schema::connection('sqlsrv')->create('sesi_unit_kendaraan', function (Blueprint $table) {
                $table->id('id_kendaraan');
                $table->unsignedBigInteger('id_perusahaan');
                $table->string('id_cabang', 50)->comment('TODO: BLOCKING — ambil dari mapping user');
                $table->string('id_skill', 50)->comment('FK to Q_CustomerLocusAtribute (delivery area)');
                $table->string('jenis_kendaraan', 100)->nullable();
                $table->string('plat_nomor_truk', 20)->nullable();
                $table->decimal('muatan_maksimal', 8, 2)->nullable()->comment('ton; NULL kalau row ini rate card tanpa kendaraan fisik');
                $table->decimal('harga_sewa', 15, 2)->nullable()->comment('Rate sewa dari histori CSV; NULL kalau row ini kendaraan fisik biasa (ga ada rate card)');
                $table->boolean('flag')->default(true);
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('id_perusahaan')->references('id_perusahaan')->on('sesi_perusahaan_ekspedisi');
                // Note: id_cabang tidak ada FK ke sesi_master_cabang karena tabel itu bukan milik app
            });

            // plat_nomor_truk nullable tapi tetap unique untuk nilai yang terisi
            // (kendaraan internal/sementara boleh belum punya plat nomor)
            DB::connection('sqlsrv')->statement('
                CREATE UNIQUE INDEX UNIQUE_sesi_unit_kendaraan_plat_nomor_truk_filtered
                ON sesi_unit_kendaraan(plat_nomor_truk)
                WHERE plat_nomor_truk IS NOT NULL
            ');
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_unit_kendaraan');
    }
};
