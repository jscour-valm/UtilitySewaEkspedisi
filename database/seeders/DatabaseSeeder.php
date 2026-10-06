<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Dummy IT tables (lntrn_*, sesi_master_cabang, Q_CustomerLocusAtribute)
        // untuk local dev SEKARANG di-setup manual via file .sql terpisah (Task
        // 19, 2 Okt 2026) — bukan lagi lewat seeder Laravel.

        // Seed master data
        $this->call([
            RasioSewaSeeder::class,
            JenisBiayaSeeder::class,
            JenisBarangKirimanSeeder::class,
            MasterJenisKendaraanSeeder::class,
            CabangSkillSeeder::class,
            ApprovalSeeder::class,
        ]);
    }
}
