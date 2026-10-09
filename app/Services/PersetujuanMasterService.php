<?php

namespace App\Services;

use App\Exceptions\PersetujuanMasterException;
use App\Helpers\FormatHelper;
use App\Http\Controllers\Concerns\LogsRiwayatHarga;
use App\Mail\NotifikasiMasterMail;
use App\Models\ApprovalLog;
use App\Models\PengajuanSewa;
use App\Models\PerusahaanEkspedisi;
use App\Models\PerusahaanSkill;
use App\Models\TarifKirimanRutin;
use App\Models\User;
use App\Models\UsulanHarga;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Proses persetujuan master yang terpisah dari pengajuan sewa: vendor baru dan
 * usulan harga. Alur KG mengajukan → WM (cabang pengaju) memvalidasi → WH menyetujui;
 * penolakan oleh WM/WH langsung final.
 *
 * - Vendor ditolak → flag=0 dan pengajuan sewa Pending yang memakai vendor itu ikut ditolak.
 * - Usulan harga disetujui → harga master diperbarui (histori harga ikut tercatat).
 *   Usulan ditolak tidak mengubah pengajuan sewa apa pun.
 */
class PersetujuanMasterService
{
    use LogsRiwayatHarga;

    public function __construct(
        private PenerimaEmail $penerima,
        private PengirimEmail $pengirim,
    ) {}

    /** Vendor baru dari KG masuk antrian validasi WM. Dipanggil setelah baris vendor dibuat. */
    public function ajukanVendor(PerusahaanEkspedisi $vendor, User $kg): void
    {
        $vendor->forceFill([
            'status_approval' => PerusahaanEkspedisi::STATUS_MENUNGGU_VALIDASI,
            'id_cabang_pengaju' => $kg->getCabangId(),
            'submitted_by' => $kg->id,
            'submitted_at' => now(),
            'alasan_penolakan' => null,
            'flag' => true,
        ])->save();

        $this->kirimEmail($vendor, 'diajukan');
    }

    /** Vendor yang ditolak diperbaiki KG pengaju lalu masuk antrian validasi WM lagi. */
    public function ajukanUlangVendor(PerusahaanEkspedisi $vendor, User $kg): void
    {
        if ($vendor->statusPersetujuan() !== PerusahaanEkspedisi::STATUS_REJECTED) {
            throw new PersetujuanMasterException('Hanya vendor yang ditolak yang bisa diajukan ulang.');
        }
        if ((int) $vendor->submitted_by !== (int) $kg->id) {
            throw new PersetujuanMasterException('Vendor ini hanya bisa diajukan ulang oleh pengajunya.', 403);
        }

        $vendor->forceFill([
            'status_approval' => PerusahaanEkspedisi::STATUS_MENUNGGU_VALIDASI,
            'submitted_at' => now(),
            'alasan_penolakan' => null,
            'flag' => true,
        ])->save();

        $this->kirimEmail($vendor, 'diajukan_ulang');
    }

    /**
     * Usulan harga master untuk satu tarif. Kalau tarif itu sudah punya usulan yang sedang
     * berjalan: harga sama → usulan itu dipakai ulang; harga beda → ditolak.
     *
     * @param  array{jenis: string, id_perusahaan: int, id_skill: int, cabang_code: string, id_jenis_barang?: ?int}  $target
     * @return UsulanHarga|null null kalau harga sama dengan master sekarang (tidak perlu usulan)
     */
    public function ajukanUsulan(array $target, float $harga, string $sumber, ?string $catatan, User $kg): ?UsulanHarga
    {
        $jenis = $target['jenis'];
        $idJenisBarang = $jenis === UsulanHarga::JENIS_KIRIMAN_RUTIN ? ($target['id_jenis_barang'] ?? null) : null;
        if ($jenis === UsulanHarga::JENIS_KIRIMAN_RUTIN && ! $idJenisBarang) {
            throw new PersetujuanMasterException('Jenis barang usulan tarif Kiriman Rutin wajib diisi.');
        }

        return DB::connection('sqlsrv')->transaction(function () use ($target, $jenis, $idJenisBarang, $harga, $sumber, $catatan, $kg) {
            $berjalan = UsulanHarga::untukTarif($jenis, (int) $target['id_perusahaan'], (int) $target['id_skill'], $target['cabang_code'], $idJenisBarang)
                ->sedangBerjalan()
                ->lockForUpdate()
                ->first();

            $putusan = UsulanHarga::putusanBentrok($berjalan ? (float) $berjalan->harga_usulan : null, $harga);
            if ($putusan === 'ikut') {
                return $berjalan;
            }
            if ($putusan === 'tolak') {
                throw new PersetujuanMasterException(
                    'Masih ada usulan harga master '.FormatHelper::rupiah($berjalan->harga_usulan)
                    .' untuk tarif ini yang menunggu keputusan. Pakai harga itu atau tunggu sampai diputuskan.'
                );
            }

            [$idVendorSkill, $idTarif, $hargaLama] = $this->masterSekarang($target, $idJenisBarang);
            if ($hargaLama !== null && round($hargaLama, 2) === round($harga, 2)) {
                return null;
            }

            $usulan = UsulanHarga::create([
                'jenis' => $jenis,
                'id_perusahaan' => (int) $target['id_perusahaan'],
                'id_skill' => (int) $target['id_skill'],
                'cabang_code' => $target['cabang_code'],
                'id_jenis_barang' => $idJenisBarang,
                'id_vendor_skill' => $idVendorSkill,
                'id_tarif' => $idTarif,
                'harga_lama' => $hargaLama,
                'harga_usulan' => $harga,
                'sumber' => $sumber,
                'catatan' => $catatan,
                'status' => UsulanHarga::STATUS_MENUNGGU_VALIDASI,
                'id_cabang_pengaju' => $kg->getCabangId(),
                'submitted_by' => $kg->id,
                'submitted_at' => now(),
                'flag' => true,
            ]);

            $this->kirimEmail($usulan, 'diajukan');

            return $usulan;
        });
    }

    /**
     * Keputusan WM (validasi) atau WH (approval) atas vendor baru / usulan harga.
     *
     * @param  bool  $setuju  false = tolak (alasan wajib)
     */
    public function putuskan(PerusahaanEkspedisi|UsulanHarga $objek, User $user, bool $setuju, ?string $alasan = null): void
    {
        $peran = $user->userUtility?->role;
        if (! $setuju && trim((string) $alasan) === '') {
            throw new PersetujuanMasterException('Alasan penolakan wajib diisi.');
        }

        $db = DB::connection('sqlsrv');
        $pengajuanDitolak = collect();
        $alasanPengajuan = null;

        $db->beginTransaction();
        try {
            // Kunci baris supaya dua klik bersamaan tidak memutuskan dua kali.
            $objek = $objek::withInactive()->lockForUpdate()->findOrFail($objek->getKey());

            $giliran = $objek->giliranPersetujuan();
            if ($giliran === null) {
                throw new PersetujuanMasterException('Proses ini sudah diputuskan.', 400);
            }
            if ($giliran !== $peran) {
                throw new PersetujuanMasterException('Belum giliran Anda — proses ini '.lcfirst($objek->labelStatusPersetujuan()).'.', 400);
            }
            if ($peran === 'WM' && ! $user->canAccessCabang($objek->id_cabang_pengaju)) {
                throw new PersetujuanMasterException('Anda hanya bisa memproses pengajuan dari cabang Anda.', 403);
            }

            $kolom = $objek->kolomStatusPersetujuan();
            if (! $setuju) {
                $objek->forceFill([$kolom => $objek::STATUS_REJECTED, 'alasan_penolakan' => $alasan]);
                if ($objek instanceof PerusahaanEkspedisi) {
                    $objek->flag = false;
                    $alasanPengajuan = "Vendor baru {$objek->nama_perusahaan} ditolak $peran: $alasan";
                    $pengajuanDitolak = $this->tolakPengajuanVendor($objek, $user, $peran, $alasanPengajuan);
                }
                $aksi = 'ditolak';
            } elseif ($peran === 'WM') {
                $objek->forceFill([$kolom => $objek::STATUS_MENUNGGU_APPROVAL]);
                $aksi = 'divalidasi';
            } else {
                $objek->forceFill([$kolom => $objek::STATUS_APPROVED]);
                if ($objek instanceof UsulanHarga) {
                    $this->terapkanUsulan($objek);
                }
                $aksi = 'disetujui';
            }
            $objek->save();

            $this->catatLog($objek, $user, $peran, $setuju, $alasan);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        $this->kirimEmail($objek, $aksi, $peran, $setuju ? null : $alasan);

        foreach ($pengajuanDitolak as $p) {
            try {
                app(NotifikasiPengajuanService::class)->keputusan($p, $peran, null, $alasanPengajuan);
            } catch (\Throwable $e) {
                Log::warning("Notifikasi penolakan otomatis pengajuan #{$p->id_pengajuan_sewa} gagal: ".$e->getMessage());
            }
        }
    }

    /**
     * Peran penerima email per aksi (KG = pengaju saja, WM = cabang pengaju). DCI di-CC lewat PengirimEmail.
     *
     * @return array{0: string[], 1: string[]} [peran To, peran CC]
     */
    public static function penerima(string $aksi, ?string $peranPemutus = null): array
    {
        return match ($aksi) {
            'diajukan', 'diajukan_ulang' => [['WM'], ['KG']],
            'divalidasi' => [['WH'], ['KG', 'WM']],
            'disetujui' => [['KG'], ['WM', 'WH', 'WC']],
            'ditolak' => [['KG'], $peranPemutus === 'WH' ? ['WM', 'WH'] : ['WM']],
        };
    }

    /** Sewa Truk: harga_sewa di sesi_perusahaan_skill (dibuat kalau belum ada). @return int id_vendor_skill */
    public function terapkanHargaSewaTruk(array $konteks, float $harga): int
    {
        $vendorSkill = PerusahaanSkill::withInactive()->firstOrCreate($konteks, ['flag' => true]);
        $hargaLama = $vendorSkill->harga_sewa;
        $vendorSkill->update([
            'harga_sewa_sebelumnya' => $hargaLama,
            'harga_sewa' => $harga,
            'flag' => true,
        ]);
        $this->catatRiwayatHargaSewaTruk($vendorSkill->id_vendor_skill, $hargaLama, $harga);

        return $vendorSkill->id_vendor_skill;
    }

    /**
     * Kiriman Rutin: biaya_per_unit di sesi_tarif_kiriman_rutin. Pakai $idTarif kalau masih ada;
     * selain itu vendor-skill & tarif dicari atau dibuat dari konteks. @return int id_tarif
     */
    public function terapkanTarifKirimanRutin(array $konteks, int $idJenisBarang, float $harga, ?int $idTarif = null): int
    {
        $tarif = $idTarif ? TarifKirimanRutin::withInactive()->find($idTarif) : null;

        if (! $tarif) {
            $vendorSkill = PerusahaanSkill::withInactive()->firstOrCreate($konteks, ['flag' => true]);
            if (! $vendorSkill->flag) {
                $vendorSkill->update(['flag' => true]);
            }

            $tarif = TarifKirimanRutin::withInactive()->firstOrCreate(
                ['id_vendor_skill' => $vendorSkill->id_vendor_skill, 'id_jenis_barang' => $idJenisBarang],
                ['biaya_per_unit' => $harga, 'flag' => true]
            );
            if ($tarif->wasRecentlyCreated) {
                return $tarif->id_tarif;
            }
        }

        $biayaLama = $tarif->biaya_per_unit;
        $tarif->update([
            'harga_sebelumnya' => $biayaLama,
            'biaya_per_unit' => $harga,
            'flag' => true,
        ]);
        $this->catatRiwayatTarifKirimanRutin($tarif->id_tarif, $biayaLama, $harga);

        return $tarif->id_tarif;
    }

    private function terapkanUsulan(UsulanHarga $usulan): void
    {
        $konteks = [
            'id_perusahaan' => (int) $usulan->id_perusahaan,
            'id_skill' => (int) $usulan->id_skill,
            'cabang_code' => $usulan->cabang_code,
        ];

        if ($usulan->jenis === UsulanHarga::JENIS_SEWA_TRUK) {
            $usulan->id_vendor_skill = $this->terapkanHargaSewaTruk($konteks, (float) $usulan->harga_usulan);

            return;
        }

        $usulan->id_tarif = $this->terapkanTarifKirimanRutin($konteks, (int) $usulan->id_jenis_barang, (float) $usulan->harga_usulan, $usulan->id_tarif);
        $usulan->id_vendor_skill = TarifKirimanRutin::withInactive()->whereKey($usulan->id_tarif)->value('id_vendor_skill');

        // Baris pengajuan yang dulu belum punya tarif sekarang menunjuk tarif master.
        DB::connection('sqlsrv')->table('sesi_detail_kiriman_rutin')
            ->where('id_usulan_harga', $usulan->id_usulan_harga)
            ->whereNull('id_tarif_kiriman_rutin')
            ->update(['id_tarif_kiriman_rutin' => $usulan->id_tarif]);
    }

    /** @return array{0: ?int, 1: ?int, 2: ?float} [id_vendor_skill, id_tarif, harga master sekarang] */
    private function masterSekarang(array $target, ?int $idJenisBarang): array
    {
        $vendorSkill = PerusahaanSkill::where([
            'id_perusahaan' => (int) $target['id_perusahaan'],
            'id_skill' => (int) $target['id_skill'],
            'cabang_code' => $target['cabang_code'],
        ])->first();

        if (! $vendorSkill) {
            return [null, null, null];
        }

        if ($target['jenis'] === UsulanHarga::JENIS_SEWA_TRUK) {
            return [$vendorSkill->id_vendor_skill, null, $vendorSkill->harga_sewa !== null ? (float) $vendorSkill->harga_sewa : null];
        }

        $tarif = TarifKirimanRutin::where('id_vendor_skill', $vendorSkill->id_vendor_skill)
            ->where('id_jenis_barang', $idJenisBarang)
            ->first();

        return [$vendorSkill->id_vendor_skill, $tarif?->id_tarif, $tarif ? (float) $tarif->biaya_per_unit : null];
    }

    /**
     * Pengajuan sewa Pending yang memakai vendor ditolak ikut ditolak (log atas nama pemutus).
     *
     * @return Collection<int, PengajuanSewa>
     */
    private function tolakPengajuanVendor(PerusahaanEkspedisi $vendor, User $user, string $peran, string $alasan)
    {
        $idVendor = $vendor->id_perusahaan;

        $daftar = PengajuanSewa::whereRaw('LOWER(status_pengajuan) = ?', ['pending'])
            ->where(function ($q) use ($idVendor) {
                $q->where('id_perusahaan_ekspedisi', $idVendor)
                    ->orWhereHas('kendaraan', fn ($k) => $k->withInactive()->where('id_perusahaan', $idVendor));
            })
            ->lockForUpdate()
            ->get();

        foreach ($daftar as $p) {
            $alur = $p->alurApproval();
            $posisi = array_search($peran, $alur, true);

            ApprovalLog::create([
                'id_pengajuan_sewa' => $p->id_pengajuan_sewa,
                'id_approval_rule' => null,
                'id_approver' => $user->id,
                'role_approver' => $peran,
                'tingkat' => $posisi === false ? null : $posisi + 1,
                'status' => 'rejected',
                'decided_at' => now(),
                'alasan_penolakan' => $alasan,
            ]);
            $p->status_pengajuan = 'Rejected';
            $p->save();
        }

        return $daftar;
    }

    /** Log keputusan di sesi_approval_log; tingkat 1 = validasi WM, 2 = approval WH. */
    private function catatLog(PerusahaanEkspedisi|UsulanHarga $objek, User $user, string $peran, bool $setuju, ?string $alasan): void
    {
        ApprovalLog::create([
            $objek instanceof PerusahaanEkspedisi ? 'id_perusahaan' : 'id_usulan_harga' => $objek->getKey(),
            'id_approver' => $user->id,
            'role_approver' => $peran,
            'tingkat' => $peran === 'WM' ? 1 : 2,
            'status' => $setuju ? 'approved' : 'rejected',
            'decided_at' => now(),
            'alasan_penolakan' => $setuju ? null : $alasan,
        ]);
    }

    private function kirimEmail(PerusahaanEkspedisi|UsulanHarga $objek, string $aksi, ?string $peranPemutus = null, ?string $alasan = null): void
    {
        try {
            [$toPeran, $ccPeran] = self::penerima($aksi, $peranPemutus);
            $to = $this->alamat($toPeran, $objek);
            $cc = $this->alamat($ccPeran, $objek);

            $jenis = $objek instanceof PerusahaanEkspedisi ? 'vendor' : 'harga';
            $url = route('persetujuan.buka', [$jenis, $objek->getKey()]);
            $label = "'$aksi' ".($jenis === 'vendor' ? 'vendor' : 'usulan harga')." #{$objek->getKey()}";

            $this->pengirim->kirim($label, $to, $cc, fn () => new NotifikasiMasterMail($objek->fresh(), $aksi, $url, $alasan));
        } catch (\Throwable $e) {
            Log::warning("Email persetujuan master '$aksi' gagal disiapkan: ".$e->getMessage());
        }
    }

    /** @param  string[]  $peran */
    private function alamat(array $peran, PerusahaanEkspedisi|UsulanHarga $objek): array
    {
        $emails = in_array('KG', $peran, true) ? $this->penerima->user($objek->submitted_by) : [];

        return array_values(array_unique(array_merge(
            $emails,
            $this->penerima->peran(array_values(array_diff($peran, ['KG'])), $objek->id_cabang_pengaju),
        )));
    }
}
