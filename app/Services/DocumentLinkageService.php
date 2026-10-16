<?php

namespace App\Services;

use App\Models\AturanAlur;
use App\Models\PengajuanSewa;
use App\Models\PengajuanSewaSuratJalan;
use App\Models\PengajuanSewaToAcb;
use App\Models\RasioSewa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DocumentLinkageService
 *
 * Handles linking pengajuan sewa to external documents:
 * - Surat Jalan (from Quantum API)
 * - Transfer Antar Cabang (from ERP API)
 *
 * Uses reference-only approach (no FKs to external databases)
 * Validation done at application layer
 */
class DocumentLinkageService
{
    /**
     * Link Surat Jalan to Pengajuan Sewa
     *
     * @param  string  $idSuratJalan  (Reference to Quantum tbSJ.No_)
     */
    public function linkSuratJalan(int $idPengajuanSewa, string $idSuratJalan, bool $flag = true): PengajuanSewaSuratJalan
    {
        // Validate pengajuan exists
        $pengajuan = PengajuanSewa::findOrFail($idPengajuanSewa);
        $this->pastikanBelumTerpakai('SJ', $idSuratJalan, $idPengajuanSewa);

        // TODO: Validate idSuratJalan exists in Quantum API
        // $this->validateSuratJalanExists($idSuratJalan);

        // withInactive(): baris yang pernah di-unlink (flag=0) harus di-UPDATE
        // (reactivate), bukan di-INSERT ulang — kena unique (id_pengajuan_sewa, id_surat_jalan).
        $link = PengajuanSewaSuratJalan::withInactive()->updateOrCreate(
            [
                'id_pengajuan_sewa' => $idPengajuanSewa,
                'id_surat_jalan' => $idSuratJalan,
            ],
            [
                'flag' => $flag,
            ]
        );

        Log::info('Surat Jalan linked to pengajuan', [
            'pengajuan_id' => $idPengajuanSewa,
            'surat_jalan_id' => $idSuratJalan,
            'flag' => $flag,
        ]);

        return $link;
    }

    /**
     * Unlink Surat Jalan from Pengajuan Sewa (soft delete via flag)
     */
    public function unlinkSuratJalan(int $idPengajuanSewa, string $idSuratJalan): bool
    {
        $link = PengajuanSewaSuratJalan::withInactive()
            ->where('id_pengajuan_sewa', $idPengajuanSewa)
            ->where('id_surat_jalan', $idSuratJalan)
            ->first();

        if (! $link) {
            Log::warning('Surat Jalan link not found', [
                'pengajuan_id' => $idPengajuanSewa,
                'surat_jalan_id' => $idSuratJalan,
            ]);

            return false;
        }

        $link->update(['flag' => false]);

        Log::info('Surat Jalan unlinked from pengajuan', [
            'pengajuan_id' => $idPengajuanSewa,
            'surat_jalan_id' => $idSuratJalan,
        ]);

        return true;
    }

    /**
     * Get all active Surat Jalan for a pengajuan
     */
    public function getSuratJalansForPengajuan(int $idPengajuanSewa): array
    {
        return PengajuanSewaSuratJalan::where('id_pengajuan_sewa', $idPengajuanSewa)
            ->aktif()
            ->pluck('id_surat_jalan')
            ->toArray();
    }

    /**
     * Link Transfer Antar Cabang to Pengajuan Sewa
     *
     * @param  string  $idToAcb  (Reference to ERP tbTransferAntarCabang.No_)
     */
    public function linkTransferAntarCabang(int $idPengajuanSewa, string $idToAcb, bool $flag = true): PengajuanSewaToAcb
    {
        // Validate pengajuan exists
        $pengajuan = PengajuanSewa::findOrFail($idPengajuanSewa);
        $this->pastikanBelumTerpakai('TO-ACB', $idToAcb, $idPengajuanSewa);

        // TODO: Validate idToAcb exists in ERP API
        // $this->validateTransferAntarCabangExists($idToAcb);

        // withInactive(): lihat catatan di linkSuratJalan().
        $link = PengajuanSewaToAcb::withInactive()->updateOrCreate(
            [
                'id_pengajuan_sewa' => $idPengajuanSewa,
                'id_to_acb' => $idToAcb,
            ],
            [
                'flag' => $flag,
            ]
        );

        Log::info('Transfer Antar Cabang linked to pengajuan', [
            'pengajuan_id' => $idPengajuanSewa,
            'to_acb_id' => $idToAcb,
            'flag' => $flag,
        ]);

        return $link;
    }

    /**
     * Unlink Transfer Antar Cabang from Pengajuan Sewa (soft delete via flag)
     */
    public function unlinkTransferAntarCabang(int $idPengajuanSewa, string $idToAcb): bool
    {
        $link = PengajuanSewaToAcb::withInactive()
            ->where('id_pengajuan_sewa', $idPengajuanSewa)
            ->where('id_to_acb', $idToAcb)
            ->first();

        if (! $link) {
            Log::warning('Transfer Antar Cabang link not found', [
                'pengajuan_id' => $idPengajuanSewa,
                'to_acb_id' => $idToAcb,
            ]);

            return false;
        }

        $link->update(['flag' => false]);

        Log::info('Transfer Antar Cabang unlinked from pengajuan', [
            'pengajuan_id' => $idPengajuanSewa,
            'to_acb_id' => $idToAcb,
        ]);

        return true;
    }

    /**
     * Get all active Transfer Antar Cabang for a pengajuan
     */
    public function getTransferAntarCabangForPengajuan(int $idPengajuanSewa): array
    {
        return PengajuanSewaToAcb::where('id_pengajuan_sewa', $idPengajuanSewa)
            ->aktif()
            ->pluck('id_to_acb')
            ->toArray();
    }

    /**
     * Detail SJ / TO-ACB yang ter-link ke pengajuan (lookup ke view Quantum), plus agregat
     * untuk email notifikasi. Kolom sama dengan PengajuanController::getDokumenList.
     * Dokumen yang tidak ditemukan lagi di sumber (mis. SJ sudah keluar dari "Belum Kirim")
     * tetap muncul dengan detail null, biar nomornya nggak hilang.
     *
     * @return array{tipe:string, items:array, jumlah_dokumen:int, jumlah_toko:int, total_berat_kg:float, total_nilai:float}
     */
    /**
     * Nomor dokumen (SJ / TO-ACB) yang sedang dipakai pengajuan lain berstatus pending atau
     * approved. Dokumen dari pengajuan yang ditolak boleh dipakai lagi.
     *
     * @return string[]
     */
    public function dokumenTerpakai(string $tipe, ?int $kecualiPengajuan = null): array
    {
        [$tabel, $kolom] = $tipe === 'TO-ACB'
            ? ['sesi_pengajuan_sewa_to_acb', 'id_to_acb']
            : ['sesi_pengajuan_sewa_surat_jalan', 'id_surat_jalan'];

        return DB::connection('sqlsrv')->table($tabel.' as d')
            ->join('sesi_pengajuan_sewa as p', 'p.id_pengajuan_sewa', '=', 'd.id_pengajuan_sewa')
            ->where('d.flag', true)
            ->where('p.flag', true)
            ->whereRaw('LOWER(p.status_pengajuan) IN (?, ?)', ['pending', 'approved'])
            ->when($kecualiPengajuan, fn ($q) => $q->where('p.id_pengajuan_sewa', '!=', $kecualiPengajuan))
            ->distinct()
            ->pluck('d.'.$kolom)
            ->all();
    }

    private function pastikanBelumTerpakai(string $tipe, string $nomor, int $idPengajuanSewa): void
    {
        if (in_array($nomor, $this->dokumenTerpakai($tipe, $idPengajuanSewa), true)) {
            throw new \InvalidArgumentException("Dokumen $nomor sudah dipakai pengajuan lain yang masih aktif.");
        }
    }

    /**
     * Cek dokumen pilihan KG terhadap sumbernya (view "Surat Jalan Belum Kirim" / "Transfer Antar
     * Cabang"): ada, jenisnya sesuai tujuan (Toko = SJ, PAC = TO-ACB), SJ milik cabang pengaju dan
     * tidak sedang dikoreksi, belum dipakai pengajuan lain yang pending/approved. Nilai dokumen
     * diambil dari sumber, bukan dari browser.
     *
     * @param  string[]  $nomor
     * @return array{items: array<string, array{tipe:string, nilai:float}>, masalah: string[]}
     */
    public function periksaDokumen(array $nomor, string $cabang, string $tujuan, ?int $kecualiPengajuan = null): array
    {
        $nomor = array_values(array_unique(array_map(fn ($n) => trim((string) $n), $nomor)));
        $nomor = array_values(array_filter($nomor, fn ($n) => $n !== ''));
        if (! $nomor) {
            return ['items' => [], 'masalah' => []];
        }

        $db = DB::connection('sqlsrv');
        $sj = $db->table('Surat Jalan Belum Kirim')->whereIn('No_', $nomor)
            ->get([DB::raw('No_ as nomor'), DB::raw('[Sell-to County] as county'), DB::raw('Flag_Correction as koreksi'), DB::raw('ISNULL(Amount, 0) as nilai')])
            ->keyBy('nomor');
        $acb = $db->table('Transfer Antar Cabang')->whereIn('No_', $nomor)
            ->get([DB::raw('No_ as nomor'), DB::raw('ISNULL(Amount, 0) as nilai')])
            ->keyBy('nomor');
        $pemakai = $this->pemakaiDokumen($nomor, $kecualiPengajuan);

        $items = [];
        $masalah = [];
        foreach ($nomor as $no) {
            if ($r = $sj->get($no)) {
                if ($tujuan === 'PAC') {
                    $masalah[] = "$no adalah Surat Jalan, sedangkan tujuan PAC memakai TO-ACB.";
                } elseif (! str_starts_with(strtoupper((string) $r->county), strtoupper($cabang))) {
                    $masalah[] = "$no bukan Surat Jalan cabang $cabang.";
                } elseif (trim((string) $r->koreksi) !== '') {
                    $masalah[] = "$no sedang dikoreksi, belum bisa dipakai.";
                } elseif (isset($pemakai[$no])) {
                    $masalah[] = "$no sudah dipakai pengajuan {$pemakai[$no]} yang masih aktif.";
                } else {
                    $items[$no] = ['tipe' => 'SJ', 'nilai' => (float) $r->nilai];
                }
            } elseif ($r = $acb->get($no)) {
                if ($tujuan !== 'PAC') {
                    $masalah[] = "$no adalah TO-ACB, sedangkan tujuan Toko memakai Surat Jalan.";
                } elseif (isset($pemakai[$no])) {
                    $masalah[] = "$no sudah dipakai pengajuan {$pemakai[$no]} yang masih aktif.";
                } else {
                    $items[$no] = ['tipe' => 'TO-ACB', 'nilai' => (float) $r->nilai];
                }
            } else {
                $masalah[] = "$no tidak ditemukan di daftar dokumen yang belum dikirim (mungkin sudah dikirim atau nomornya salah).";
            }
        }

        return ['items' => $items, 'masalah' => $masalah];
    }

    /**
     * Pengajuan pending/approved yang sedang memakai dokumen tertentu (SJ & TO-ACB).
     *
     * @param  string[]  $nomor
     * @return array<string, string> nomor dokumen => kode pengajuan (lihat PengajuanSewa::kode)
     */
    private function pemakaiDokumen(array $nomor, ?int $kecualiPengajuan): array
    {
        $hasil = [];
        foreach ([['sesi_pengajuan_sewa_surat_jalan', 'id_surat_jalan'], ['sesi_pengajuan_sewa_to_acb', 'id_to_acb']] as [$tabel, $kolom]) {
            $baris = DB::connection('sqlsrv')->table($tabel.' as d')
                ->join('sesi_pengajuan_sewa as p', 'p.id_pengajuan_sewa', '=', 'd.id_pengajuan_sewa')
                ->where('d.flag', true)
                ->where('p.flag', true)
                ->whereRaw('LOWER(p.status_pengajuan) IN (?, ?)', ['pending', 'approved'])
                ->whereIn('d.'.$kolom, $nomor)
                ->when($kecualiPengajuan, fn ($q) => $q->where('p.id_pengajuan_sewa', '!=', $kecualiPengajuan))
                ->get(['d.'.$kolom.' as nomor', 'p.id_pengajuan_sewa as id', ...(PengajuanSewa::kolomKodeAda() ? ['p.kode_pengajuan'] : [])]);
            foreach ($baris as $b) {
                $hasil[$b->nomor] = PengajuanSewa::kodeDari($b->kode_pengajuan ?? null, $b->id);
            }
        }

        return $hasil;
    }

    /**
     * Pasang tautan dokumen hasil periksaDokumen() ke pengajuan; tautan lain milik pengajuan ini
     * dilepas (flag = 0). Dipanggil di dalam transaksi submit/update.
     *
     * @param  array<string, array{tipe:string, nilai:float}>  $items
     */
    public function tautkanDokumen(PengajuanSewa $pengajuan, array $items): void
    {
        $id = $pengajuan->id_pengajuan_sewa;
        $nomorSj = array_keys(array_filter($items, fn ($i) => $i['tipe'] === 'SJ'));
        $nomorAcb = array_keys(array_filter($items, fn ($i) => $i['tipe'] === 'TO-ACB'));

        PengajuanSewaSuratJalan::where('id_pengajuan_sewa', $id)->whereNotIn('id_surat_jalan', $nomorSj)->update(['flag' => false]);
        PengajuanSewaToAcb::where('id_pengajuan_sewa', $id)->whereNotIn('id_to_acb', $nomorAcb)->update(['flag' => false]);

        // withInactive(): tautan lama (flag = 0) diaktifkan lagi, bukan di-insert ulang (unique per pengajuan + nomor)
        foreach ($nomorSj as $no) {
            PengajuanSewaSuratJalan::withInactive()->updateOrCreate(['id_pengajuan_sewa' => $id, 'id_surat_jalan' => $no], ['flag' => true]);
        }
        foreach ($nomorAcb as $no) {
            PengajuanSewaToAcb::withInactive()->updateOrCreate(['id_pengajuan_sewa' => $id, 'id_to_acb' => $no], ['flag' => true]);
        }
    }

    /**
     * Hitung ulang value muatan, rasio, dan alur dari dokumen yang sedang tertaut (dipakai
     * setelah tautan diubah lewat endpoint link/unlink, selama pengajuan masih bisa diedit).
     */
    public function hitungUlangNilai(PengajuanSewa $pengajuan): void
    {
        $value = $this->getDetailDokumen($pengajuan)['total_nilai'];
        $biaya = (float) $pengajuan->biayaTambahan()->sum('jumlah');
        $rasio = $value > 0 ? (((float) $pengajuan->harga_sewa + $biaya) / $value) * 100 : null;
        $rasioMaks = RasioSewa::aktif()?->persentase_maksimal ?? RasioSewa::BATAS_DEFAULT;
        $alur = AturanAlur::alurUntuk($pengajuan->jenis_pengajuan, $pengajuan->tujuan_penyewaan, ($rasio ?? 0.0) > $rasioMaks);

        $pengajuan->update([
            'value_muatan' => $value > 0 ? $value : null,
            'rasio_sewa' => $rasio !== null ? round($rasio, 2) : null,
            'alur_approval' => $alur,
            'kategori_approval' => str_contains($alur, ',') ? 'over_threshold' : 'normal',
        ]);
    }

    public function getDetailDokumen(PengajuanSewa $pengajuan): array
    {
        $nomorSj = $this->getSuratJalansForPengajuan($pengajuan->id_pengajuan_sewa);
        $nomorAcb = $this->getTransferAntarCabangForPengajuan($pengajuan->id_pengajuan_sewa);

        $items = [];

        if ($nomorSj) {
            $rows = DB::connection('sqlsrv')->table('Surat Jalan Belum Kirim')
                ->whereIn('No_', $nomorSj)
                ->select(
                    DB::raw('No_ as nomor'),
                    DB::raw('[Sell-to Customer No_] as customer_no'),
                    DB::raw('[Sell-to Customer Name] as customer'),
                    DB::raw('[Ship-to Address] as alamat'),
                    DB::raw('[Sell-to City] as kota'),
                    DB::raw('ISNULL(GW, 0) as berat'),
                    DB::raw('ISNULL(Amount, 0) as nilai')
                )
                ->get()->keyBy('nomor');

            foreach ($nomorSj as $no) {
                $r = $rows->get($no);
                $items[] = [
                    'nomor' => $no,
                    'customer_no' => $r->customer_no ?? null,
                    'customer' => $r->customer ?? null,
                    'alamat' => $r->alamat ?? null,
                    'kota' => $r->kota ?? null,
                    'berat' => (float) ($r->berat ?? 0),
                    'nilai' => (float) ($r->nilai ?? 0),
                ];
            }
        }

        if ($nomorAcb) {
            $rows = DB::connection('sqlsrv')->table('Transfer Antar Cabang')
                ->whereIn('No_', $nomorAcb)
                ->select(
                    DB::raw('No_ as nomor'),
                    DB::raw('[Last Shipment No_] as last_shipment_no'),
                    DB::raw('ISNULL([Gross Weight], 0) as berat'),
                    DB::raw('ISNULL(Amount, 0) as nilai')
                )
                ->get()->keyBy('nomor');

            foreach ($nomorAcb as $no) {
                $r = $rows->get($no);
                $items[] = [
                    'nomor' => $no,
                    'customer_no' => null,
                    'customer' => null,
                    'alamat' => null,
                    'kota' => null,
                    'last_shipment_no' => $r->last_shipment_no ?? null,
                    'berat' => (float) ($r->berat ?? 0),
                    'nilai' => (float) ($r->nilai ?? 0),
                ];
            }
        }

        $collection = collect($items);

        return [
            'tipe' => $nomorAcb && ! $nomorSj ? 'TO-ACB' : 'SJ',
            'items' => $items,
            'jumlah_dokumen' => count($items),
            'jumlah_toko' => $collection->pluck('customer_no')->filter()->unique()->count(),
            'total_berat_kg' => (float) $collection->sum('berat'),
            'total_nilai' => (float) $collection->sum('nilai'),
        ];
    }

    /**
     * TODO: Validate Surat Jalan exists in Quantum API
     * This method will call the Quantum API to verify the SJ exists
     */
    protected function validateSuratJalanExists(string $idSuratJalan): bool
    {
        // Implementation pending: integrate with Quantum API
        return true;
    }

    /**
     * TODO: Validate Transfer Antar Cabang exists in ERP API
     * This method will call the ERP API to verify the TO-ACB exists
     */
    protected function validateTransferAntarCabangExists(string $idToAcb): bool
    {
        // Implementation pending: integrate with ERP API
        return true;
    }
}
