<?php

namespace Tests\Unit;

use App\Helpers\RentangTanggalDashboard;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class RentangTanggalDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function rentang(array $query = []): RentangTanggalDashboard
    {
        return RentangTanggalDashboard::dariRequest(new Request($query));
    }

    public function test_default_tujuh_hari_sampai_hari_ini(): void
    {
        $r = $this->rentang();
        $this->assertSame('2026-10-01', $r->dari->toDateString());
        $this->assertSame('2026-10-07', $r->sampai->toDateString());
        $this->assertFalse($r->dipotong);
    }

    public function test_rentang_lebih_dari_tujuh_hari_dipotong(): void
    {
        $r = $this->rentang(['dari' => '2026-09-01', 'sampai' => '2026-09-30']);
        $this->assertSame('2026-09-24', $r->dari->toDateString());
        $this->assertSame('2026-09-30', $r->sampai->toDateString());
        $this->assertTrue($r->dipotong);
    }

    public function test_tanggal_terbalik_ditukar_dan_input_salah_pakai_default(): void
    {
        $r = $this->rentang(['dari' => '2026-10-05', 'sampai' => '2026-10-03']);
        $this->assertSame('2026-10-03', $r->dari->toDateString());
        $this->assertSame('2026-10-05', $r->sampai->toDateString());

        $r = $this->rentang(['dari' => 'kemarin', 'sampai' => '2026-13-45']);
        $this->assertSame('2026-10-01', $r->dari->toDateString());
    }

    public function test_mencakup_seluruh_hari_terakhir(): void
    {
        $r = $this->rentang();
        $this->assertTrue($r->mencakup('2026-10-07 23:59:00'));
        $this->assertTrue($r->mencakup('2026-10-01 00:00:00'));
        $this->assertFalse($r->mencakup('2026-09-30 23:59:59'));
        $this->assertFalse($r->mencakup(null));
    }

    public function test_antrian_pending_tidak_ikut_filter(): void
    {
        $r = $this->rentang();
        $lama = '2026-09-01 08:00:00';
        $this->assertTrue($r->tampil('WM', $lama, 'Pending', 'WM'));
        $this->assertFalse($r->tampil('WM', $lama, 'Pending', 'WH'));
        $this->assertTrue($r->tampil('WH', $lama, 'Pending', 'WH'));
        $this->assertTrue($r->tampil('DCI', $lama, 'Pending', 'WC'));
        $this->assertFalse($r->tampil('DCI', $lama, 'Approved', null));
        $this->assertFalse($r->tampil('KG', $lama, 'Pending', 'WM'));
    }
}
