<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserCabangSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding User-Cabang Mappings...');
        $this->command->info('===============================');

        // Query dari lntrn_view_summary_mapping_areas
        $mappings = DB::connection('sqlsrv')->table('lntrn_view_summary_mapping_areas')
            ->where('type', 'wm')
            ->where('company', 'TT')
            ->where('effective_date', '2025-01-01')
            ->select('username', 'code', 'area', 'type')
            ->get();

        // Query users dengan role sesi dari lntrn_users
        $allUsers = DB::connection('sqlsrv')->table('lntrn_users as a')
            ->join('lntrn_user_utility as b', 'b.user_id', '=', 'a.id')
            ->join('lntrn_utilities as c', 'c.id', '=', 'b.utility_id')
            ->select('a.username', 'b.role')
            ->where('c.prefix', 'sesi')
            ->where('a.deleted_at', null)
            ->get()
            ->keyBy('username');

        $inserts = [];
        $skipped = [];

        // Process mappings dari view
        foreach ($mappings as $map) {
            $cabangCode = strtoupper($map->code);
            $area = $map->area;
            $username = $map->username;
            $role = isset($allUsers[$username]) ? $allUsers[$username]->role : strtoupper($map->type);

            $inserts[] = [
                'username' => $username,
                'cabang_code' => $cabangCode,
                'area' => $area,
                'role' => $role,
                'flag' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $this->command->info("✓ {$username} → {$cabangCode} (Area: {$area})");
        }

        // Process other sesi users (non-WM)
        foreach ($allUsers as $username => $user) {
            if (!isset($inserts) || !collect($inserts)->where('username', $username)->first()) {
                $role = $user->role;

                // WH dan DCI: global access (no cabang restriction)
                if (in_array($role, ['WH', 'DCI'])) {
                    $inserts[] = [
                        'username' => $username,
                        'cabang_code' => null,
                        'area' => null,
                        'role' => $role,
                        'flag' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $this->command->info("✓ {$username} → GLOBAL ACCESS ({$role})");
                } else {
                    // KG, KA: extract cabang dari username
                    $cabangCode = strtoupper(substr($username, 0, 3));
                    $cabang = DB::connection('sqlsrv')->table('sesi_master_cabang')
                        ->where('Code', $cabangCode)
                        ->first();

                    if ($cabang) {
                        $inserts[] = [
                            'username' => $username,
                            'cabang_code' => $cabangCode,
                            'area' => null,
                            'role' => $role,
                            'flag' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        $this->command->info("✓ {$username} → {$cabangCode} ({$role})");
                    } else {
                        $skipped[] = $username;
                        $this->command->warn("✗ {$username} (cabang tidak ditemukan)");
                    }
                }
            }
        }

        // Insert all mappings in chunks (SQL Server limit 2100 parameters)
        if (!empty($inserts)) {
            DB::connection('sqlsrv')->table('sesi_user_cabang')->truncate();

            $chunks = array_chunk($inserts, 100); // Insert 100 rows per query
            foreach ($chunks as $chunk) {
                DB::connection('sqlsrv')->table('sesi_user_cabang')->insert($chunk);
            }

            $this->command->info("\n✓ Berhasil insert " . count($inserts) . " user-cabang mappings dalam " . count($chunks) . " batches!");
        }

        if (!empty($skipped)) {
            $this->command->warn("\n⚠️  Skipped " . count($skipped) . " users");
        }
    }
}