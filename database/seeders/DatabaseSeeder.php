<?php

namespace Database\Seeders;

use App\Models\User;
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
        // Local dev only - seed dummy IT tables
        if (app()->environment('local')) {
            $this->call([
                LocalDummyCabangSeeder::class,
                LocalDummyUserSeeder::class,
                LocalDummyUserCabangSeeder::class,
                LocalDummyUtilitySeeder::class,
            ]);
        }

        // Seed master data
        $this->call([
            RasioSewaSeeder::class,
            JenisBiayaSeeder::class,
            JenisBarangKirimanSeeder::class,
            CabangSkillSeeder::class,
            ApprovalSeeder::class,
        ]);
    }
}
