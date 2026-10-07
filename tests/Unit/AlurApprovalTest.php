<?php

namespace Tests\Unit;

use App\Models\PengajuanSewa;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AlurApprovalTest extends TestCase
{
    public static function kasusAlur(): array
    {
        return [
            'rasio di bawah batas' => [false, false, 'WM'],
            'PAC' => [true, false, 'WM,WC'],
            'rasio di atas batas' => [false, true, 'WM,WH'],
            'PAC + rasio di atas batas' => [true, true, 'WM,WH'],
        ];
    }

    #[DataProvider('kasusAlur')]
    public function test_hitung_alur(bool $isPac, bool $butuhWh, string $harapan): void
    {
        $this->assertSame($harapan, PengajuanSewa::hitungAlur($isPac, $butuhWh));
    }

    public static function kasusPakaiRasio(): array
    {
        return [
            'sewa truk toko' => ['sewa_truk', 'Toko', true],
            'sewa truk PAC' => ['sewa_truk', 'PAC', true],
            'kiriman rutin PAC' => ['pengiriman_rutin', 'PAC', true],
            'kiriman rutin toko' => ['pengiriman_rutin', 'Toko', false],
        ];
    }

    #[DataProvider('kasusPakaiRasio')]
    public function test_pakai_rasio(string $jenis, string $tujuan, bool $harapan): void
    {
        $this->assertSame($harapan, PengajuanSewa::pakaiRasioUntuk($jenis, $tujuan));
    }
}
