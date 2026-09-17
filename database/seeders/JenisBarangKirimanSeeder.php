<?php

namespace Database\Seeders;

use App\Models\JenisBarangKiriman;
use Illuminate\Database\Seeder;

class JenisBarangKirimanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Seed 9 jenis barang default yang dapat dikirim via tarif kiriman rutin.
     */
    public function run(): void
    {
        $items = [
            'Per Koli (Cat Pail)',
            'Per Koli (Cat Dus)',
            'Per Ikat (Pipa)',
            'Per Batang (Pipa)',
            'Per Dus (Fitting)',
            'Mebel Kecil (Per kg)',
            'Mebel Besar (Per kg)',
            'Gimo',
            'Biaya Buruh (Per Kapal)',
        ];

        foreach ($items as $namaBarang) {
            // withInactive(): kalau baris pernah di-soft-delete, reactivate (jangan bikin duplikat)
            JenisBarangKiriman::withInactive()->firstOrCreate(
                ['nama_barang' => $namaBarang],
                ['flag' => true],
            );
        }
    }
}
