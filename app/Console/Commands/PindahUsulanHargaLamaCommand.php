<?php

namespace App\Console\Commands;

use App\Exceptions\PersetujuanMasterException;
use App\Models\DetailKirimanRutin;
use App\Models\PengajuanSewa;
use App\Models\User;
use App\Models\UsulanHarga;
use App\Services\HargaMasterPengajuanService;
use App\Services\PersetujuanMasterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pindahkan usulan harga master lama yang masih pending (kolom usulan_* di pengajuan /
 * detail kiriman, sebelum ada tabel sesi_usulan_harga) jadi baris UsulanHarga yang
 * menunggu validasi WM. Idempotent: baris yang sudah punya id_usulan_harga dilewati.
 * Tanpa email notifikasi.
 */
class PindahUsulanHargaLamaCommand extends Command
{
    protected $signature = 'usulan-harga:pindah-lama {--dry-run : Tampilkan saja, tanpa menyimpan}';

    protected $description = 'Pindahkan usulan harga master lama (pending) ke tabel sesi_usulan_harga';

    public function handle(PersetujuanMasterService $service, HargaMasterPengajuanService $hargaMaster): int
    {
        $dry = (bool) $this->option('dry-run');
        $hasil = ['dipindah' => 0, 'sama_master' => 0, 'gagal' => 0];

        $sewa = PengajuanSewa::where('jenis_pengajuan', 'sewa_truk')
            ->where('usulan_harga_sewa', true)->where('usulan_status', 'pending')->whereNull('id_usulan_harga')->get();
        $rutin = DetailKirimanRutin::with('pengajuanSewa')
            ->where('usulan_update_master', true)->where('usulan_status', 'pending')->whereNull('id_usulan_harga')->get();

        $this->info("Usulan lama pending: {$sewa->count()} Sewa Truk, {$rutin->count()} baris Kiriman Rutin.".($dry ? ' (dry-run)' : ''));

        $proses = function (string $label, array $target, float $harga, PengajuanSewa $p, callable $simpan) use ($service, $dry, &$hasil) {
            $this->line("- $label: Rp ".number_format($harga, 0, ',', '.'));
            if ($dry) {
                return;
            }
            $kg = User::find($p->submitted_by);
            if (! $kg) {
                $this->warn('  dilewati: pengaju tidak ditemukan');
                $hasil['gagal']++;

                return;
            }

            try {
                DB::connection('sqlsrv')->transaction(function () use ($service, $target, $harga, $p, $kg, $simpan, &$hasil) {
                    $usulan = $service->ajukanUsulan($target, $harga, UsulanHarga::SUMBER_PENGAJUAN, null, $kg, false);
                    if ($usulan) {
                        $usulan->forceFill(['submitted_at' => $p->submitted_at, 'id_cabang_pengaju' => $p->id_cabang])->save();
                        $hasil['dipindah']++;
                    } else {
                        $hasil['sama_master']++;
                    }
                    $simpan($usulan);
                });
            } catch (PersetujuanMasterException $e) {
                $this->warn('  gagal: '.$e->getMessage());
                $hasil['gagal']++;
            }
        };

        foreach ($sewa as $p) {
            $ctx = $hargaMaster->konteksSewaTruk($p);
            if (! $ctx) {
                $this->warn("- Pengajuan #{$p->id_pengajuan_sewa}: kendaraan/area tidak lengkap, dilewati");
                $hasil['gagal']++;

                continue;
            }
            $proses("Pengajuan #{$p->id_pengajuan_sewa} harga sewa", ['jenis' => UsulanHarga::JENIS_SEWA_TRUK] + $ctx, (float) $p->harga_sewa, $p,
                fn (?UsulanHarga $u) => $p->update($u
                    ? ['id_usulan_harga' => $u->id_usulan_harga]
                    : ['usulan_harga_sewa' => false, 'usulan_status' => null]));
        }

        foreach ($rutin as $d) {
            $p = $d->pengajuanSewa;
            $idSkill = (int) collect(explode(',', (string) $p?->id_skill))->map(fn ($s) => trim($s))->first(fn ($s) => ctype_digit($s));
            if (! $p || ! $p->id_perusahaan_ekspedisi || ! $idSkill) {
                $this->warn("- Detail #{$d->id_detail_kiriman}: data pengajuan tidak lengkap, dilewati");
                $hasil['gagal']++;

                continue;
            }
            $target = [
                'jenis' => UsulanHarga::JENIS_KIRIMAN_RUTIN,
                'id_perusahaan' => (int) $p->id_perusahaan_ekspedisi,
                'id_skill' => $idSkill,
                'cabang_code' => $p->id_cabang,
                'id_jenis_barang' => (int) $d->id_jenis_barang,
            ];
            $proses("Pengajuan #{$p->id_pengajuan_sewa} barang #{$d->id_jenis_barang}", $target, (float) $d->harga_satuan, $p,
                fn (?UsulanHarga $u) => $d->update($u
                    ? ['id_usulan_harga' => $u->id_usulan_harga]
                    : ['usulan_update_master' => false, 'usulan_status' => null]));
        }

        $this->info("Selesai: {$hasil['dipindah']} dipindah, {$hasil['sama_master']} sama dengan master (penanda dihapus), {$hasil['gagal']} gagal/dilewati.");

        return self::SUCCESS;
    }
}
