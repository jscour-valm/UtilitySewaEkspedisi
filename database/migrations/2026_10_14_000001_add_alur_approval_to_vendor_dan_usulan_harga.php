<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alur persetujuan vendor baru & usulan harga diatur DCI: alur dikunci per baris saat
 * diajukan (`alur_approval`, mis. "WM,WH") dan peran yang sedang ditunggu dicatat di
 * `giliran_approval`. Baris lama dibiarkan NULL = alur WM → WH.
 */
return new class extends Migration
{
    private const TABEL = ['sesi_perusahaan_ekspedisi', 'sesi_usulan_harga'];

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        foreach (self::TABEL as $tabel) {
            if (! $schema->hasColumn($tabel, 'alur_approval')) {
                $schema->table($tabel, function (Blueprint $table) {
                    $table->string('alur_approval', 20)->nullable()->comment('Urutan peran, mis. WM,WH. NULL = WM,WH');
                });
            }
            if (! $schema->hasColumn($tabel, 'giliran_approval')) {
                $schema->table($tabel, function (Blueprint $table) {
                    $table->string('giliran_approval', 5)->nullable()->comment('Peran yang sedang ditunggu; NULL kalau sudah final / baris lama');
                });
            }
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        foreach (self::TABEL as $tabel) {
            foreach (['giliran_approval', 'alur_approval'] as $kolom) {
                if ($schema->hasColumn($tabel, $kolom)) {
                    $schema->table($tabel, fn (Blueprint $table) => $table->dropColumn($kolom));
                }
            }
        }
    }
};
