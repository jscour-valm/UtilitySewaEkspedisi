<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ApprovalSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding Approval Rules...');
        $this->command->info('==========================');

        $inserts = [];

        try {
            // 1. WM Rows — 1 per cabang
            $wmMappings = DB::connection('sqlsrv')->table('sesi_user_cabang as uc')
                ->join('lntrn_users as u', 'u.username', '=', 'uc.username')
                ->where('uc.role', 'WM')
                ->whereNotNull('uc.cabang_code')
                ->where('uc.flag', true)
                ->select('uc.cabang_code', 'u.id', 'u.username')
                ->distinct()
                ->orderBy('uc.cabang_code')
                ->get();
        } catch (\Exception $e) {
            $this->command->warn("⚠ lntrn_users tidak ditemukan (external table dari IT system)");
            $this->command->info("ℹ Approval rules harus di-seed manual di production dengan user data yang valid");
            return;
        }

        $wmCabangs = [];
        foreach ($wmMappings as $map) {
            $cabang = $map->cabang_code;
            if (!isset($wmCabangs[$cabang])) {
                $wmCabangs[$cabang] = $map; // Take first WM per cabang
            }
        }

        foreach ($wmCabangs as $cabang => $map) {
            $inserts[] = [
                'id_cabang'              => $cabang,
                'tingkat'                => 1,
                'role_berwenang'         => 'WM',
                'id_approver'            => $map->id,
                'id_approver_cadangan'   => null,
                'flag'                   => true,
                'created_at'             => now(),
                'updated_at'             => now(),
            ];
            $this->command->info("✓ WM Cabang {$cabang} → {$map->username} (id={$map->id})");
        }

        // 2. WH Row — global (cabang_code = null), hardcoded username per Jo
        $whUsername = '000-wh-co3';
        $whUser = DB::connection('sqlsrv')->table('lntrn_users')
            ->where('username', $whUsername)
            ->first();

        if ($whUser) {
            $inserts[] = [
                'id_cabang'              => null,
                'tingkat'                => 2,
                'role_berwenang'         => 'WH',
                'id_approver'            => $whUser->id,
                'id_approver_cadangan'   => null,
                'flag'                   => true,
                'created_at'             => now(),
                'updated_at'             => now(),
            ];
            $this->command->info("✓ WH Global → {$whUsername} (id={$whUser->id})");
        } else {
            $this->command->warn("✗ WH user '{$whUsername}' tidak ditemukan — skip WH row");
        }

        // Insert in chunks (SQL Server 2100 parameter limit)
        if (!empty($inserts)) {
            // Cannot truncate due to FK constraint — use DELETE + disable/enable FK checks
            DB::connection('sqlsrv')->statement('EXEC sp_MSForEachTable "ALTER TABLE ? NOCHECK CONSTRAINT all"');
            DB::connection('sqlsrv')->table('sesi_approval')->delete();
            DB::connection('sqlsrv')->statement('EXEC sp_MSForEachTable "ALTER TABLE ? CHECK CONSTRAINT all"');

            $chunks = array_chunk($inserts, 100);
            foreach ($chunks as $chunk) {
                DB::connection('sqlsrv')->table('sesi_approval')->insert($chunk);
            }

            $this->command->info("\n✓ Berhasil insert " . count($inserts) . " approval rules dalam " . count($chunks) . " batches!");
        } else {
            $this->command->warn("⚠️  Tidak ada approval rules yang di-insert");
        }
    }
}
