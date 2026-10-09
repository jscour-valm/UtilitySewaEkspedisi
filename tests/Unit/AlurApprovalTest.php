<?php

namespace Tests\Unit;

use App\Models\AturanAlur;
use App\Models\PengajuanSewa;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AlurApprovalTest extends TestCase
{
    public static function kasusAlur(): array
    {
        return [
            'sewa truk toko, rasio di bawah batas' => ['sewa_truk', 'Toko', false, 'WM'],
            'sewa truk toko, rasio di atas batas' => ['sewa_truk', 'Toko', true, 'WM,WH'],
            'sewa truk PAC' => ['sewa_truk', 'PAC', false, 'WM,WC'],
            'sewa truk PAC + rasio di atas batas' => ['sewa_truk', 'PAC', true, 'WM,WH'],
            'kiriman rutin toko (tanpa rasio)' => ['pengiriman_rutin', 'Toko', true, 'WM'],
            'kiriman rutin PAC' => ['pengiriman_rutin', 'PAC', false, 'WM,WC'],
            'kiriman rutin PAC + rasio di atas batas' => ['pengiriman_rutin', 'PAC', true, 'WM,WH'],
        ];
    }

    #[DataProvider('kasusAlur')]
    public function test_alur_bawaan(string $jenis, string $tujuan, bool $rasioDiAtas, string $harapan): void
    {
        AturanAlur::lupakanCache();
        $this->assertSame($harapan, AturanAlur::alurUntuk($jenis, $tujuan, $rasioDiAtas));
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
