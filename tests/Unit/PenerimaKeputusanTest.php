<?php

namespace Tests\Unit;

use App\Models\PengajuanSewa;
use App\Services\NotifikasiPengajuanService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PenerimaKeputusanTest extends TestCase
{
    public static function kasusPenerima(): array
    {
        return [
            'PAC + rasio, WM validasi lanjut WH' => ['WM,WH', 'PAC', 'Pending', 'WM', 'WH', 'menunggu_approval', ['WM', 'WH', 'WC']],
            'Toko rasio tinggi, WM validasi lanjut WH' => ['WM,WH', 'Toko', 'Pending', 'WM', 'WH', 'menunggu_approval', ['WM', 'WH']],
            'PAC, WM validasi lanjut WC' => ['WM,WC', 'PAC', 'Pending', 'WM', 'WC', 'menunggu_approval', ['WM', 'WC']],
            'final di WM' => ['WM', 'Toko', 'Approved', 'WM', null, 'approved', ['WM', 'WC', 'KA']],
            'final setelah WH' => ['WM,WH', 'PAC', 'Approved', 'WH', null, 'approved', ['WM', 'WC', 'WH', 'KA']],
            'ditolak, tanpa KA' => ['WM,WH', 'Toko', 'Rejected', 'WH', null, 'rejected', ['WM', 'WH']],
        ];
    }

    #[DataProvider('kasusPenerima')]
    public function test_penerima_keputusan(string $alur, string $tujuan, string $status, string $pemutus, ?string $berikutnya, string $tipe, array $cc): void
    {
        $p = new PengajuanSewa;
        $p->alur_approval = $alur;
        $p->tujuan_penyewaan = $tujuan;
        $p->status_pengajuan = $status;

        [$tipeHasil, $ccHasil] = NotifikasiPengajuanService::penerimaKeputusan($p, $pemutus, $berikutnya);

        $this->assertSame($tipe, $tipeHasil);
        $this->assertEqualsCanonicalizing($cc, $ccHasil);
    }
}
