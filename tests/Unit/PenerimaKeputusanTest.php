<?php

namespace Tests\Unit;

use App\Models\AturanEmail;
use App\Models\PengajuanSewa;
use App\Services\NotifikasiPengajuanService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PenerimaKeputusanTest extends TestCase
{
    public static function kasusPenerima(): array
    {
        return [
            'PAC + rasio, WM validasi lanjut WH' => ['WM,WH', 'PAC', 'Pending', 'WM', 'WH', 'menunggu_approval', ['WM', 'WH', 'WC', 'DCI']],
            'Toko rasio tinggi, WM validasi lanjut WH' => ['WM,WH', 'Toko', 'Pending', 'WM', 'WH', 'menunggu_approval', ['WM', 'WH', 'DCI']],
            'PAC, WM validasi lanjut WC' => ['WM,WC', 'PAC', 'Pending', 'WM', 'WC', 'menunggu_approval', ['WM', 'WC', 'DCI']],
            'final di WM' => ['WM', 'Toko', 'Approved', 'WM', null, 'approved', ['WM', 'WC', 'KA', 'DCI']],
            'final setelah WH' => ['WM,WH', 'PAC', 'Approved', 'WH', null, 'approved', ['WM', 'WC', 'WH', 'KA', 'DCI']],
            'ditolak, WC tidak di alur, tanpa KA' => ['WM,WH', 'Toko', 'Rejected', 'WH', null, 'rejected', ['WM', 'WH', 'DCI']],
            'ditolak di WM, alur hanya WM' => ['WM', 'Toko', 'Rejected', 'WM', null, 'rejected', ['WM', 'DCI']],
        ];
    }

    #[DataProvider('kasusPenerima')]
    public function test_penerima_keputusan(string $alur, string $tujuan, string $status, string $pemutus, ?string $berikutnya, string $tipe, array $cc): void
    {
        AturanEmail::lupakanCache();
        $p = new PengajuanSewa;
        $p->alur_approval = $alur;
        $p->tujuan_penyewaan = $tujuan;
        $p->status_pengajuan = $status;

        [$tipeHasil, $toHasil, $ccHasil] = NotifikasiPengajuanService::penerimaKeputusan($p, $pemutus, $berikutnya);

        $this->assertSame($tipe, $tipeHasil);
        $this->assertSame(['KG'], $toHasil);
        $this->assertEqualsCanonicalizing($cc, $ccHasil);
    }

    public function test_setiap_kejadian_punya_penerima_to(): void
    {
        AturanEmail::lupakanCache();
        foreach (array_keys(AturanEmail::KEJADIAN) as $kejadian) {
            $this->assertNotEmpty(AturanEmail::penerima($kejadian)['to'], $kejadian);
        }
    }
}
