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
}
