<?php

use App\Models\AturanAlur;
use App\Models\AturanEmail;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan DCI: alur approval pengajuan sewa per kondisi (sesi_aturan_alur) dan penerima
 * email per kejadian (sesi_aturan_email). Diisi aturan bawaan (sama dengan perilaku sebelumnya).
 * `updated_by` tanpa FK ke lntrn_users (tabel eksternal IT).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        $db = DB::connection('sqlsrv');

        if (! $schema->hasTable('sesi_aturan_alur')) {
            $schema->create('sesi_aturan_alur', function (Blueprint $table) {
                $table->id('id_aturan_alur');
                $table->string('jenis_pengajuan', 30);
                $table->string('tujuan_penyewaan', 10);
                $table->string('rasio', 10)->comment('bawah | atas | - (tanpa rasio)');
                $table->string('alur', 20)->comment('WM | WM,WC | WM,WH | WM,WC,WH');
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->unique(['jenis_pengajuan', 'tujuan_penyewaan', 'rasio']);
            });

            foreach (AturanAlur::ALUR_BAWAAN as $kunci => $alur) {
                [$jenis, $tujuan, $rasio] = explode('|', $kunci);
                $db->table('sesi_aturan_alur')->insert([
                    'jenis_pengajuan' => $jenis, 'tujuan_penyewaan' => $tujuan, 'rasio' => $rasio,
                    'alur' => $alur, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        if (! $schema->hasTable('sesi_aturan_email')) {
            $schema->create('sesi_aturan_email', function (Blueprint $table) {
                $table->id('id_aturan_email');
                $table->string('kejadian', 40);
                $table->string('role', 10);
                $table->string('jenis', 5)->comment('to | cc');
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->unique(['kejadian', 'role']);
            });

            foreach (AturanEmail::KEJADIAN as $kejadian => $k) {
                foreach (['to', 'cc'] as $jenis) {
                    foreach ($k[$jenis] as $role) {
                        $db->table('sesi_aturan_email')->insert([
                            'kejadian' => $kejadian, 'role' => $role, 'jenis' => $jenis,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        $schema->dropIfExists('sesi_aturan_email');
        $schema->dropIfExists('sesi_aturan_alur');
    }
};
