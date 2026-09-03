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
        // Seed master data
        $this->call([
            RasioSewaSeeder::class,
            JenisBiayaSeeder::class,
            CabangSkillSeeder::class,
            ApprovalSeeder::class,
        ]);
    }
}
