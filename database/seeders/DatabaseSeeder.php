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
        // Tabel dummy IT (lntrn_*, sesi_master_cabang, Q_CustomerLocusAtribute) untuk local dev
        // disiapkan manual lewat file .sql terpisah, bukan seeder Laravel.

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
