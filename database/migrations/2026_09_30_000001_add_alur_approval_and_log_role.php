<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alur approval per pengajuan (spesifikasi mentor, 30 Sept 2026): urutan approver
 * disimpan & dikunci per pengajuan (`WM`, `WM,WH`, `WM,WC`, `WM,WC,WH`) karena
 * sekarang ada WC dan alur 3 tingkat — `kategori_approval` (normal/over_threshold)
 * tidak cukup lagi untuk tahu siapa approver berikutnya.
 *
 * `sesi_approval_log.role_approver` + `tingkat`: peran approver dicatat langsung di
 * log, bukan diturunkan dari `sesi_approval.role_berwenang` (enum cuma WM/WH, jadi
 * WC tidak bisa punya rule di sana).
 */
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('sqlsrv');

        if (! $db->hasColumn('sesi_pengajuan_sewa', 'alur_approval')) {
            $db->table('sesi_pengajuan_sewa', function (Blueprint $table) {
                $table->string('alur_approval', 20)->nullable()->after('kategori_approval')
                    ->comment('urutan approver, dipisah koma: WM | WM,WH | WM,WC | WM,WC,WH');
            });

            DB::connection('sqlsrv')->table('sesi_pengajuan_sewa')
                ->whereNull('alur_approval')
                ->update(['alur_approval' => DB::raw("CASE WHEN kategori_approval = 'over_threshold' THEN 'WM,WH' ELSE 'WM' END")]);
        }

        if (! $db->hasColumn('sesi_approval_log', 'role_approver')) {
            $db->table('sesi_approval_log', function (Blueprint $table) {
                $table->string('role_approver', 10)->nullable()->after('id_approver');
                $table->unsignedTinyInteger('tingkat')->nullable()->after('role_approver');
            });

            DB::connection('sqlsrv')->statement('
                UPDATE al SET al.role_approver = ar.role_berwenang, al.tingkat = ar.tingkat
                FROM sesi_approval_log al
                INNER JOIN sesi_approval ar ON ar.id_approval_rule = al.id_approval_rule
                WHERE al.role_approver IS NULL
            ');
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('sesi_approval_log', function (Blueprint $table) {
            $table->dropColumn(['role_approver', 'tingkat']);
        });
        Schema::connection('sqlsrv')->table('sesi_pengajuan_sewa', function (Blueprint $table) {
            $table->dropColumn('alur_approval');
        });
    }
};
