<?php

namespace Tests\Unit;

use App\Models\Kendaraan;
use App\Models\PengajuanSewa;
use App\Models\PerusahaanEkspedisi;
use App\Models\UsulanHarga;
use App\Services\PersetujuanMasterService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PersetujuanMasterTest extends TestCase
{
    public static function kasusGiliran(): array
    {
        return [
            'menunggu validasi → WM' => ['menunggu_validasi', 'WM', true],
            'menunggu approval → WH' => ['menunggu_approval', 'WH', true],
            'approved → final' => ['approved', null, false],
            'rejected → final' => ['rejected', null, false],
        ];
    }

    #[DataProvider('kasusGiliran')]
    public function test_giliran_vendor_dan_usulan(string $status, ?string $giliran, bool $berjalan): void
    {
        $vendor = new PerusahaanEkspedisi(['status_approval' => $status]);
        $usulan = new UsulanHarga(['status' => $status]);

        $this->assertSame($giliran, $vendor->giliranPersetujuan());
        $this->assertSame($giliran, $usulan->giliranPersetujuan());
        $this->assertSame($berjalan, $vendor->sedangBerjalan());
        $this->assertSame($berjalan, $usulan->sedangBerjalan());
    }

    public function test_putusan_bentrok_usulan(): void
    {
        $this->assertSame('baru', UsulanHarga::putusanBentrok(null, 1200000));
        $this->assertSame('ikut', UsulanHarga::putusanBentrok(1200000.00, 1200000));
        $this->assertSame('tolak', UsulanHarga::putusanBentrok(1200000.00, 1300000));
    }

    public static function kasusPenerima(): array
    {
        return [
            'diajukan' => ['diajukan', null, ['WM'], ['KG', 'DCI']],
            'diajukan ulang' => ['diajukan_ulang', null, ['WM'], ['KG', 'DCI']],
            'divalidasi WM' => ['divalidasi', 'WM', ['WH'], ['KG', 'WM', 'DCI']],
            'disetujui WH' => ['disetujui', 'WH', ['KG'], ['WM', 'WH', 'WC', 'DCI']],
            'ditolak WM' => ['ditolak', 'WM', ['KG'], ['WM', 'DCI']],
            'ditolak WH' => ['ditolak', 'WH', ['KG'], ['WM', 'WH', 'DCI']],
        ];
    }

    #[DataProvider('kasusPenerima')]
    public function test_penerima_email(string $aksi, ?string $pemutus, array $to, array $cc): void
    {
        [$toHasil, $ccHasil] = PersetujuanMasterService::penerima($aksi, $pemutus);

        $this->assertEqualsCanonicalizing($to, $toHasil);
        $this->assertEqualsCanonicalizing($cc, $ccHasil);
    }

    public function test_pengajuan_tertahan_hanya_oleh_vendor_yang_belum_approved(): void
    {
        $rutin = new PengajuanSewa(['jenis_pengajuan' => 'pengiriman_rutin']);
        $rutin->setRelation('perusahaanEkspedisi', new PerusahaanEkspedisi(['status_approval' => 'menunggu_approval']));
        $this->assertNotNull($rutin->vendorMenungguApproval());

        $rutin->setRelation('perusahaanEkspedisi', new PerusahaanEkspedisi(['status_approval' => 'approved']));
        $this->assertNull($rutin->vendorMenungguApproval());

        $kendaraan = new Kendaraan;
        $kendaraan->setRelation('perusahaan', new PerusahaanEkspedisi(['status_approval' => 'menunggu_validasi']));
        $sewa = new PengajuanSewa(['jenis_pengajuan' => 'sewa_truk']);
        $sewa->setRelation('kendaraan', $kendaraan);
        $this->assertNotNull($sewa->vendorMenungguApproval());
    }
}
