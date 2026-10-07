<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pembatalan pengajuan oleh pengaju: status `Cancelled`, waktu & alasan pembatalan.
 */
return new class extends Migration
{
    private const CONSTRAINT = 'CK_sesi_pengajuan_sewa_status';

    public function up(): void
    {
        $this->gantiCheckStatus(['Pending', 'Approved', 'Rejected', 'Cancelled']);

        $schema = Schema::connection('sqlsrv');
        $schema->table('sesi_pengajuan_sewa', function (Blueprint $table) use ($schema) {
            if (! $schema->hasColumn('sesi_pengajuan_sewa', 'dibatalkan_at')) {
                $table->dateTime('dibatalkan_at')->nullable();
            }
            if (! $schema->hasColumn('sesi_pengajuan_sewa', 'alasan_pembatalan')) {
                $table->string('alasan_pembatalan', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (DB::connection('sqlsrv')->table('sesi_pengajuan_sewa')->where('status_pengajuan', 'Cancelled')->exists()) {
            throw new RuntimeException('Masih ada pengajuan berstatus Cancelled; ubah statusnya dulu sebelum rollback.');
        }

        $schema = Schema::connection('sqlsrv');
        $schema->table('sesi_pengajuan_sewa', function (Blueprint $table) {
            $table->dropColumn(['dibatalkan_at', 'alasan_pembatalan']);
        });

        $this->gantiCheckStatus(['Pending', 'Approved', 'Rejected']);
    }

    /** Hapus CHECK status yang ada (nama bawaan SQL Server berbeda tiap DB) lalu buat ulang. */
    private function gantiCheckStatus(array $nilai): void
    {
        $db = DB::connection('sqlsrv');

        $nama = $db->table('sys.check_constraints')
            ->where('parent_object_id', DB::raw("OBJECT_ID('dbo.sesi_pengajuan_sewa')"))
            ->where('definition', 'like', '%status_pengajuan%')
            ->pluck('name');

        foreach ($nama as $n) {
            $db->statement("ALTER TABLE dbo.sesi_pengajuan_sewa DROP CONSTRAINT [$n]");
        }

        $daftar = implode(', ', array_map(fn ($v) => "N'$v'", $nilai));
        $db->statement('ALTER TABLE dbo.sesi_pengajuan_sewa ADD CONSTRAINT '.self::CONSTRAINT." CHECK (status_pengajuan IN ($daftar))");
    }
};
