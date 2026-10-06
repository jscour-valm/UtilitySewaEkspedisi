<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `sesi_approval_log.id_approval_rule` jadi NULLABLE (FK tetap ada). Sebelumnya
 * NOT NULL → approve/reject gagal 500 kalau cabang belum punya rule di
 * `sesi_approval`, dan WC tidak mungkin punya rule (enum role_berwenang cuma WM/WH).
 * Peran approver sekarang dicatat di `role_approver` (migration sebelumnya).
 */
return new class extends Migration
{
    private const FK = 'sesi_approval_log_id_approval_rule_foreign';

    public function up(): void
    {
        $db = DB::connection('sqlsrv');

        $nullable = $db->selectOne("SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'sesi_approval_log' AND COLUMN_NAME = 'id_approval_rule'");
        if ($nullable && $nullable->IS_NULLABLE === 'YES') {
            return;
        }

        if ($db->selectOne('SELECT 1 AS ada FROM sys.foreign_keys WHERE name = ?', [self::FK])) {
            $db->statement('ALTER TABLE sesi_approval_log DROP CONSTRAINT '.self::FK);
        }
        $db->statement('ALTER TABLE sesi_approval_log ALTER COLUMN id_approval_rule BIGINT NULL');
        $db->statement('ALTER TABLE sesi_approval_log ADD CONSTRAINT '.self::FK
            .' FOREIGN KEY (id_approval_rule) REFERENCES sesi_approval (id_approval_rule)');
    }

    public function down(): void
    {
        // Sengaja tidak dikembalikan ke NOT NULL: log WC / cabang tanpa rule akan melanggar.
    }
};
