<?php

namespace App\Http\Controllers;

use App\Helpers\DbHelper;
use App\Helpers\FormatHelper;
use App\Http\Controllers\Concerns\BuildsPerusahaanSummary;
use App\Http\Controllers\Concerns\ManagesVendorMasterData;
use App\Models\BiayaTambahan;
use App\Models\DetailKirimanRutin;
use App\Models\JenisBiaya;
use App\Models\Kendaraan;
use App\Models\MasterJenisKendaraan;
use App\Models\PengajuanSewa;
use App\Models\PengajuanSewaSuratJalan;
use App\Models\PengajuanSewaToAcb;
use App\Models\PerusahaanEkspedisi;
use App\Models\PerusahaanSkill;
use App\Models\RasioSewa;
use App\Models\TarifKirimanRutin;
use App\Services\DocumentLinkageService;
use App\Services\NotifikasiPengajuanService;
use App\Services\SnapshotDokumenService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PengajuanController extends Controller
{
    use BuildsPerusahaanSummary;
    use ManagesVendorMasterData;

    public function storeKendaraan(Request $request)
    {
        $request->validate([
            'perusahaan_id' => 'required|exists:sqlsrv.dbo.sesi_perusahaan_ekspedisi,id_perusahaan',
            'id_skill' => 'nullable|array',
            'id_skill.*' => 'integer|exists:sqlsrv.dbo.sesi_master_skill,id_skill',
            'skill_baru' => 'nullable|array',
            'skill_baru.*' => 'nullable|string|max:100',
            'jenis_kendaraan' => 'nullable|string|max:100',
            'id_jenis_kendaraan' => 'nullable|integer|exists:sqlsrv.dbo.sesi_master_jenis_kendaraan,id_jenis_kendaraan',
            'plat_nomor_truk' => 'nullable|string|max:20|unique:sqlsrv.dbo.sesi_unit_kendaraan,plat_nomor_truk',
            'muatan_maksimal' => 'required|numeric|min:0.01',
        ]);

        if (empty($request->id_skill) && empty(array_filter($request->skill_baru ?? []))) {
            return response()->json([
                'success' => false,
                'message' => 'Pilih atau tambahkan minimal 1 area/skill.',
            ], 422);
        }

        try {
            DB::connection('sqlsrv')->beginTransaction();

            $user = auth()->user();
            $cabangId = $user->getCabangId();
            $userId = $user->id;

            if (! $cabangId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cabang tidak ditemukan dari username',
                ], 400);
            }

            $perusahaan = PerusahaanEkspedisi::findOrFail($request->perusahaan_id);

            $skillIds = collect($request->id_skill ?? [])->map(fn ($s) => (int) $s);
            foreach (collect($request->skill_baru ?? [])->filter() as $nama) {
                $idBaru = $this->resolveSkillId($nama, $cabangId);
                if ($idBaru) {
                    $skillIds->push($idBaru);
                }
            }
            $skillString = $skillIds->unique()->values()->implode(',');

            // Jenis kendaraan sekarang idealnya dipilih dari master (id_jenis_kendaraan) —
            // kolom teks bebas `jenis_kendaraan` tetap diisi (snapshot nama_jenis) buat
            // fallback tampilan di tempat yang belum sempat diupdate baca dari relasi.
            $jenisKendaraan = $request->id_jenis_kendaraan
                ? MasterJenisKendaraan::find($request->id_jenis_kendaraan)
                : null;

            // Create kendaraan
            $kendaraan = Kendaraan::create([
                'id_perusahaan' => $perusahaan->id_perusahaan,
                'id_cabang' => $cabangId,
                'id_skill' => $skillString,
                'jenis_kendaraan' => $jenisKendaraan?->nama_jenis ?? ($request->jenis_kendaraan ?: null),
                'id_jenis_kendaraan' => $jenisKendaraan?->id_jenis_kendaraan,
                'plat_nomor_truk' => $request->plat_nomor_truk ? strtoupper($request->plat_nomor_truk) : null,
                'muatan_maksimal' => $request->muatan_maksimal,
                'flag' => true,
            ]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Kendaraan berhasil ditambahkan',
                'kendaraan' => [
                    'id' => $kendaraan->id_kendaraan,
                    'nama' => $perusahaan->nama_perusahaan,
                    'kendaraan' => $kendaraan->jenis_kendaraan,
                    'plat_nomor_truk' => $kendaraan->plat_nomor_truk,
                    'muatan' => (int) $kendaraan->muatan_maksimal.' Ton',
                    'muatan_raw' => $kendaraan->muatan_maksimal,
                    'harga' => 'Rp 0',
                    'skill' => $this->resolveSkillNames($kendaraan->id_skill),
                    'updated' => now()->format('d M Y'),
                ],
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan kendaraan: '.$e->getMessage(),
            ], 500);
        }
    }

    public function submitPengajuan(Request $request)
    {
        // Base validation common to both jenis_pengajuan
        $baseRules = [
            'jenis_pengajuan' => 'required|in:sewa_truk,pengiriman_rutin',
            'tanggal_pengiriman' => 'required|date',
            'tujuan_penyewaan' => 'required|in:Toko,PAC',
            'id_cabang_tujuan' => 'required_if:tujuan_penyewaan,PAC|nullable|string|max:10|exists:sqlsrv.dbo.sesi_master_cabang,Code',
            'id_skill' => 'nullable|array',
            'id_skill.*' => 'required|integer|exists:sqlsrv.dbo.sesi_master_skill,id_skill',
            'skill_baru' => 'nullable|array',
            'skill_baru.*' => 'nullable|string|max:50',
            'kategori_toko' => 'required|string',
            'catatan' => 'nullable|string',
            'biaya_tambahan' => 'nullable|array',
            'biaya_tambahan.*.id_jenis_biaya' => 'required|integer|exists:sqlsrv.dbo.sesi_jenis_biaya,id_jenis_biaya',
            'biaya_tambahan.*.nominal' => 'required|numeric|min:0',
        ];

        // Branch-specific validation
        if ($request->jenis_pengajuan === 'sewa_truk') {
            $baseRules['id_kendaraan'] = 'required|integer|exists:sqlsrv.dbo.sesi_unit_kendaraan,id_kendaraan';
            $baseRules['harga_sewa'] = 'required|numeric|min:0';
            $baseRules['usulan_harga_sewa'] = 'nullable|boolean';
        } else { // pengiriman_rutin
            $baseRules['id_perusahaan_ekspedisi'] = 'required|integer|exists:sqlsrv.dbo.sesi_perusahaan_ekspedisi,id_perusahaan';
            $baseRules['detail_kiriman'] = 'required|array|min:1';
            $baseRules['detail_kiriman.*.id_jenis_barang'] = 'required|integer|exists:sqlsrv.dbo.sesi_jenis_barang_kiriman,id_jenis_barang';
            $baseRules['detail_kiriman.*.quantity'] = 'required|numeric|min:0.01';
            $baseRules['detail_kiriman.*.biaya_per_unit'] = 'nullable|numeric|min:0.01';
            $baseRules['detail_kiriman.*.usulan_update_master'] = 'nullable|boolean';
            $baseRules['detail_kiriman.*.harga_custom'] = 'nullable|boolean';
        }

        $request->validate($baseRules + $this->aturanDokumen($request), [
            'dokumen_dipilih.required' => 'Kiriman Rutin tujuan PAC wajib memilih minimal 1 TO-ACB.',
            'dokumen_dipilih.min' => 'Kiriman Rutin tujuan PAC wajib memilih minimal 1 TO-ACB.',
        ]);
        $this->cekJumlahArea($request);

        try {
            DB::connection('sqlsrv')->beginTransaction();

            $user = auth()->user();
            $cabangId = $user->getCabangId();

            if (! $cabangId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cabang tidak ditemukan',
                ], 400);
            }

            if ($pesan = $this->cekCabangTujuan($request, $cabangId)) {
                return response()->json(['success' => false, 'message' => $pesan], 422);
            }

            $allSkills = collect($request->id_skill)
                ->map(fn ($s) => (int) $s)
                ->filter()
                ->values();

            $skillBaru = collect($request->skill_baru ?? [])
                ->map(fn ($s) => strtoupper(trim($s)))
                ->filter()
                ->values();

            foreach ($skillBaru as $nama) {
                $idSkillBaru = $this->resolveSkillId($nama, $cabangId);
                if ($idSkillBaru) {
                    $allSkills->push($idSkillBaru);
                }
            }

            $allSkills = $allSkills->unique()->values();
            $idSkillStr = $allSkills->implode(',');

            // Branch: compute harga_sewa based on jenis_pengajuan
            $hargaSewa = 0;
            if ($request->jenis_pengajuan === 'sewa_truk') {
                $hargaSewa = $request->harga_sewa;
            } else { // pengiriman_rutin
                $this->pastikanVendorSkill((int) $request->id_perusahaan_ekspedisi, $cabangId, $allSkills);

                foreach ($request->detail_kiriman as $detail) {
                    $resolved = $this->resolveHargaKirimanRutin((int) $request->id_perusahaan_ekspedisi, $cabangId, $allSkills, $detail);
                    $hargaSewa += $resolved['biaya_per_unit'] * $detail['quantity'];
                }
            }

            // Hitung rasio sewa termasuk biaya tambahan
            $totalBiayaTambahan = collect($request->biaya_tambahan ?? [])->sum('nominal');
            $rasioSewa = $request->value_muatan > 0
                ? (($hargaSewa + $totalBiayaTambahan) / $request->value_muatan) * 100
                : null;

            $alurApproval = $this->hitungAlurApproval($request->jenis_pengajuan, $rasioSewa ?? 0.0, $request->tujuan_penyewaan);
            $kategoriApproval = $alurApproval === 'WM' ? 'normal' : 'over_threshold';

            $pengajuanData = [
                'id_cabang' => $cabangId,
                'id_cabang_tujuan' => $request->tujuan_penyewaan === 'PAC' ? $request->id_cabang_tujuan : null,
                'tanggal_pengiriman' => $request->tanggal_pengiriman,
                'value_muatan' => $request->value_muatan ?: null,
                'harga_sewa' => $hargaSewa,
                'rasio_sewa' => $rasioSewa !== null ? round($rasioSewa, 2) : null,
                'kategori_approval' => $kategoriApproval,
                'alur_approval' => $alurApproval,
                'tujuan_penyewaan' => $request->tujuan_penyewaan,
                'id_skill' => $idSkillStr,
                'kategori_toko' => $request->kategori_toko,
                'status_pengajuan' => 'Pending',
                'catatan_pengajuan' => $request->catatan,
                'jenis_pengajuan' => $request->jenis_pengajuan,
                'submitted_by' => $user->id,
                'submitted_at' => now(),
                'flag' => true,
            ];

            // Branch: add kendaraan or perusahaan based on jenis_pengajuan
            if ($request->jenis_pengajuan === 'sewa_truk') {
                $pengajuanData['id_kendaraan'] = $request->id_kendaraan;
                $pengajuanData['usulan_harga_sewa'] = (bool) $request->boolean('usulan_harga_sewa');
                $pengajuanData['usulan_status'] = $request->boolean('usulan_harga_sewa') ? 'pending' : null;
            } else { // pengiriman_rutin
                $pengajuanData['id_perusahaan_ekspedisi'] = $request->id_perusahaan_ekspedisi;
            }

            $pengajuan = PengajuanSewa::create($pengajuanData);

            // Simpan biaya tambahan
            if ($request->biaya_tambahan) {
                foreach ($request->biaya_tambahan as $biaya) {
                    BiayaTambahan::create([
                        'id_pengajuan_sewa' => $pengajuan->id_pengajuan_sewa,
                        'id_jenis_biaya' => $biaya['id_jenis_biaya'],
                        'jumlah' => $biaya['nominal'],
                        'flag' => true,
                    ]);
                }
            }

            // Branch: simpan detail_kiriman untuk pengiriman_rutin
            if ($request->jenis_pengajuan === 'pengiriman_rutin' && $request->detail_kiriman) {
                foreach ($request->detail_kiriman as $detail) {
                    $resolved = $this->resolveHargaKirimanRutin((int) $request->id_perusahaan_ekspedisi, $cabangId, $allSkills, $detail);
                    $subtotal = $resolved['biaya_per_unit'] * $detail['quantity'];

                    // Barang tanpa tarif master selalu jadi usulan harga master
                    $usulanBaris = $resolved['id_tarif'] === null || ! empty($detail['usulan_update_master']);

                    DetailKirimanRutin::create([
                        'id_pengajuan_sewa' => $pengajuan->id_pengajuan_sewa,
                        'id_jenis_barang' => $detail['id_jenis_barang'],
                        'id_tarif_kiriman_rutin' => $resolved['id_tarif'], // null kalau harga ad-hoc
                        'quantity' => $detail['quantity'],
                        'harga_satuan' => $resolved['biaya_per_unit'], // snapshot
                        'subtotal' => $subtotal, // snapshot
                        'flag' => true,
                        'created_at' => now(),
                        'usulan_update_master' => $usulanBaris,
                        'usulan_status' => $usulanBaris ? 'pending' : null,
                    ]);
                }
            }

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan berhasil disubmit',
                'id_pengajuan' => $pengajuan->id_pengajuan_sewa,
            ]);
        } catch (\InvalidArgumentException $e) {
            DB::connection('sqlsrv')->rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal submit pengajuan: '.$e->getMessage(),
            ], 500);
        }
    }

    private function resolveHargaKirimanRutin(int $idPerusahaanEkspedisi, string $cabangId, Collection $skillIds, array $detail): array
    {
        foreach ($skillIds as $skillId) {
            $idVendorSkill = PerusahaanSkill::where('id_perusahaan', $idPerusahaanEkspedisi)
                ->where('id_skill', $skillId)
                ->where('cabang_code', $cabangId)
                ->where('flag', true)
                ->value('id_vendor_skill');

            if (! $idVendorSkill) {
                continue;
            }

            $tarif = TarifKirimanRutin::where('id_vendor_skill', $idVendorSkill)
                ->where('id_jenis_barang', $detail['id_jenis_barang'])
                ->where('flag', true)
                ->first();

            if ($tarif) {
                // Harga dari client hanya dipakai kalau KG mencentang usulan harga master
                // ATAU (mode edit) sengaja mempertahankan/mengisi harga sendiri (harga_custom);
                // selain itu selalu harga master (tidak ada override diam-diam).
                $hargaUsulan = (float) ($detail['biaya_per_unit'] ?? 0);
                $pakaiUsulan = (! empty($detail['usulan_update_master']) || ! empty($detail['harga_custom'])) && $hargaUsulan > 0;

                return [
                    'id_tarif' => $tarif->id_tarif,
                    'biaya_per_unit' => $pakaiUsulan ? $hargaUsulan : (float) $tarif->biaya_per_unit,
                ];
            }
        }

        if (empty($detail['biaya_per_unit']) || $detail['biaya_per_unit'] <= 0) {
            throw new \InvalidArgumentException('Tarif belum terdaftar untuk salah satu jenis barang yang dipilih. Isi harga per unit dulu.');
        }

        return ['id_tarif' => null, 'biaya_per_unit' => (float) $detail['biaya_per_unit']];
    }

    // GET /api/pengajuan/skill-list
    public function getSkillList()
    {
        $result = DbHelper::safeQuery(function () {
            $user = auth()->user();
            $cabangCode = $user->getCabangId();

            if (! $cabangCode) {
                return [];
            }

            $skills = DB::connection('sqlsrv')
                ->table('sesi_cabang_skill as cs')
                ->join('sesi_master_skill as ms', 'ms.id_skill', '=', 'cs.id_skill')
                ->where('cs.cabang_code', $cabangCode)
                ->where('cs.flag', true)
                ->where('ms.flag', true)
                ->orderBy('ms.nama_skill')
                ->select('ms.id_skill', 'ms.nama_skill')
                ->get()
                ->toArray();

            return $skills;
        });

        return response()->json($result);
    }

    // GET /api/pengajuan/kategori-toko-list
    public function getKategoriTokoList()
    {
        $result = DbHelper::safeQuery(function () {
            $user = auth()->user();
            $cabangCode = $user->getCabangId();

            if (! $cabangCode) {
                return [];
            }

            return DB::connection('sqlsrv')
                ->table('sesi_cabang_skill as cs')
                ->join('sesi_master_skill as ms', 'ms.id_skill', '=', 'cs.id_skill')
                ->join('Q_CustomerLocusAtribute as q', function ($join) {
                    $join->on(DB::raw('UPPER(TRIM(q.skills))'), '=', 'ms.nama_skill');
                })
                ->where('cs.cabang_code', $cabangCode)
                ->where('cs.flag', true)
                ->where('ms.flag', true)
                ->whereNotNull('q.kategoriToko')
                ->where('q.kategoriToko', '!=', '')
                ->where('q.kategoriToko', '!=', ' ')
                ->distinct()
                ->orderBy('q.kategoriToko')
                ->pluck('q.kategoriToko')
                ->map(fn ($k) => trim($k))
                ->filter(fn ($k) => $k !== '')
                ->unique()
                ->values()
                ->toArray();
        });

        return response()->json($result);
    }

    // GET /api/pengajuan/jenis-biaya
    public function getJenisBiaya()
    {
        $result = DbHelper::safeQuery(function () {
            return JenisBiaya::where('flag', true)
                ->orderBy('nama_biaya')
                ->get(['id_jenis_biaya', 'nama_biaya'])
                ->toArray();
        });

        return response()->json($result);
    }

    /** Kiriman Rutin tujuan Toko tidak memakai dokumen (step 3 dikunci, tanpa value muatan). */
    private function tanpaDokumen(Request $request): bool
    {
        return $request->jenis_pengajuan === 'pengiriman_rutin' && $request->tujuan_penyewaan === 'Toko';
    }

    /** Aturan value muatan & dokumen: rutin + Toko tanpa dokumen, rutin + PAC wajib TO-ACB. */
    private function aturanDokumen(Request $request): array
    {
        if ($this->tanpaDokumen($request)) {
            return ['value_muatan' => 'nullable|numeric|min:0'];
        }

        $wajib = $request->jenis_pengajuan === 'pengiriman_rutin' && $request->tujuan_penyewaan === 'PAC';

        return [
            'value_muatan' => 'required|numeric|min:1',
            'dokumen_dipilih' => $wajib ? 'required|array|min:1' : 'nullable|array',
            'dokumen_dipilih.*' => 'string|max:50',
        ];
    }

    /** Edit: lepas tautan SJ / TO-ACB yang tidak lagi dipilih. */
    private function sinkronDokumen(PengajuanSewa $pengajuan, Request $request): void
    {
        $dipilih = $this->tanpaDokumen($request)
            ? []
            : array_map('strval', (array) $request->input('dokumen_dipilih', []));

        PengajuanSewaSuratJalan::where('id_pengajuan_sewa', $pengajuan->id_pengajuan_sewa)
            ->whereNotIn('id_surat_jalan', $dipilih)
            ->update(['flag' => false]);
        PengajuanSewaToAcb::where('id_pengajuan_sewa', $pengajuan->id_pengajuan_sewa)
            ->whereNotIn('id_to_acb', $dipilih)
            ->update(['flag' => false]);
    }

    /** Dropdown "Cabang Tujuan" (PAC) di wizard. */
    public function getCabangList()
    {
        $result = DbHelper::safeQuery(
            fn () => $this->cabangTujuanOptions(auth()->user()->getCabangId())->toArray()
        );

        return response()->json($result);
    }

    /**
     * Pilihan cabang tujuan PAC: cabang satu area (sesi_master_cabang.Area) dengan
     * cabang pengaju, selain cabang pengaju sendiri. Area cabang pengaju kosong → semua cabang.
     */
    private function cabangTujuanOptions(?string $cabangSendiri)
    {
        $db = DB::connection('sqlsrv');
        $area = $cabangSendiri
            ? $db->table('sesi_master_cabang')->where('Code', $cabangSendiri)->value('Area')
            : null;

        return $db->table('sesi_master_cabang')
            ->whereNotNull('Name')
            ->when($cabangSendiri, fn ($q) => $q->where('Code', '!=', $cabangSendiri))
            ->when($area, fn ($q) => $q->where('Area', $area))
            ->orderBy('Name')
            ->get(['Code', 'Name']);
    }

    /** Pesan error kalau cabang tujuan PAC tidak valid untuk cabang pengaju, null kalau valid. */
    private function cekCabangTujuan(Request $request, ?string $cabangId): ?string
    {
        if ($request->tujuan_penyewaan !== 'PAC') {
            return null;
        }

        if ($request->id_cabang_tujuan === $cabangId) {
            return 'Cabang tujuan tidak boleh sama dengan cabang sendiri untuk PAC';
        }

        $boleh = $this->cabangTujuanOptions($cabangId)->pluck('Code')->all();

        return in_array($request->id_cabang_tujuan, $boleh, true)
            ? null
            : 'Cabang tujuan harus cabang yang satu area dengan cabang sendiri';
    }

    /**
     * GET /api/dokumen/list?tujuan_penyewaan=Toko&cabang=01A&kecuali=<id pengajuan yang diedit>
     * SJ: milik cabang pengaju. TO-ACB: semua (kolom Code = cabang tujuan). Dokumen yang
     * sedang dipakai pengajuan lain (pending/approved) tidak ditampilkan.
     */
    public function getDokumenList(Request $request)
    {
        $tujuanPenyewaan = $request->get('tujuan_penyewaan');
        $cabang = $request->get('cabang');
        $kecuali = $request->integer('kecuali') ?: null;
        $linkage = app(DocumentLinkageService::class);

        $dokumen = [];

        try {
            if (in_array($tujuanPenyewaan, ['Toko', 'Umum'])) {
                $suratJalans = DB::connection('sqlsrv')
                    ->table('Surat Jalan Belum Kirim')
                    ->where('Flag_Correction', '')
                    ->where('Sell-to County', 'LIKE', $cabang.'%')
                    ->whereNotIn('No_', $linkage->dokumenTerpakai('SJ', $kecuali))
                    ->select(
                        DB::raw('No_ as nomor_dokumen'),
                        DB::raw('[Sell-to Customer Name] as nama_customer'),
                        DB::raw('[Ship-to Address] as alamat'),
                        DB::raw('[Sell-to City] as kota'),
                        DB::raw('[Sell-to Customer No_] as customer_no'),
                        DB::raw('ISNULL(GW, 0) as berat'),
                        DB::raw('ISNULL(Amount, 0) as value')
                    )
                    ->get();

                $targetSkills = collect(explode(',', (string) $request->get('skill', '')))
                    ->map(fn ($s) => strtoupper(trim($s)))
                    ->filter()
                    ->unique()
                    ->values();

                $customerNos = $suratJalans->pluck('customer_no')->filter()->unique()->values();
                $customerSkillMap = $customerNos->isEmpty() ? collect() : DB::connection('sqlsrv')
                    ->table('Q_CustomerLocusAtribute')
                    ->whereIn('No_', $customerNos)
                    ->select('No_ as customer_no', 'skills')
                    ->get()
                    ->keyBy('customer_no');

                $sjItems = [];
                foreach ($suratJalans as $sj) {
                    $skillCustomer = strtoupper(trim($customerSkillMap->get($sj->customer_no)->skills ?? ''));
                    $skillPriority = $skillCustomer !== '' && $targetSkills->contains($skillCustomer);

                    $sjItems[] = [
                        'id' => $sj->nomor_dokumen,
                        'nomor_dokumen' => $sj->nomor_dokumen,
                        'tipe' => 'SJ',
                        'nama_customer' => $sj->nama_customer,
                        'customer_no' => $sj->customer_no,
                        'alamat' => $sj->alamat,
                        'kota' => $sj->kota,
                        'berat' => (float) $sj->berat,
                        'value' => (float) $sj->value,
                        'skill_priority' => $skillPriority,
                    ];
                }

                usort($sjItems, fn ($a, $b) => ($b['skill_priority'] <=> $a['skill_priority']));

                array_push($dokumen, ...$sjItems);
            }

            // 2. Fetch Transfer Antar Cabang (jika tujuan = PAC atau Umum)
            if (in_array($tujuanPenyewaan, ['PAC', 'Umum'])) {
                $toAcbs = DB::connection('sqlsrv')
                    ->table('Transfer Antar Cabang')
                    ->whereNotIn('No_', $linkage->dokumenTerpakai('TO-ACB', $kecuali))
                    ->orderBy('Code')
                    ->select(
                        DB::raw('No_ as nomor_dokumen'),
                        DB::raw('Code as cabang_tujuan'),
                        DB::raw('Name as nama_cabang_tujuan'),
                        DB::raw('[Last Shipment No_] as last_shipment_no'),
                        DB::raw('ISNULL([Gross Weight], 0) as berat'),
                        DB::raw('ISNULL([Net Weight], 0) as berat_bersih'),
                        DB::raw('ISNULL(Amount, 0) as value')
                    )
                    ->get();

                foreach ($toAcbs as $to) {
                    $dokumen[] = [
                        'id' => $to->nomor_dokumen,
                        'nomor_dokumen' => $to->nomor_dokumen,
                        'tipe' => 'TO-ACB',
                        'cabang_tujuan' => $to->cabang_tujuan,
                        'nama_cabang_tujuan' => $to->nama_cabang_tujuan,
                        'last_shipment_no' => $to->last_shipment_no,
                        'berat' => (float) $to->berat,
                        'berat_bersih' => (float) $to->berat_bersih,
                        'value' => (float) $to->value,
                    ];
                }
            }

            return response()->json(['dokumen' => $dokumen]);
        } catch (\Exception $e) {
            \Log::error('Error fetching dokumen list: '.$e->getMessage());

            return response()->json(['dokumen' => [], 'error' => $e->getMessage()], 400);
        }
    }

    public function edit($id)
    {
        $relations = [
            'biayaTambahan.jenisBiaya',
            'suratJalans',
            'transferAntarCabang',
        ];

        $pengajuan = PengajuanSewa::with(array_merge($relations, [
            'kendaraan.perusahaan',
            'perusahaanEkspedisi',
            'detailKirimanRutin.tarif',
            'detailKirimanRutin.jenisBarang',
        ]))->findOrFail($id);

        // Authorization: hanya KG pemilik pengajuan, dalam cabang yang sama
        if (! auth()->user()->canAccessCabang($pengajuan->id_cabang)) {
            abort(403);
        }

        if ($pengajuan->submitted_by != auth()->id()) {
            abort(403);
        }

        // Hanya yang masih menunggu validasi WM yang bisa diedit (setelah divalidasi terkunci)
        if (! $pengajuan->bisaDieditPengaju()) {
            abort(403, 'Pengajuan sudah divalidasi WM, tidak bisa diedit.');
        }

        // Fetch linked dokumen dari junction table
        $linkedSuratJalans = $pengajuan->suratJalans()->where('flag', true)->pluck('id_surat_jalan')->toArray();
        $linkedToAcbs = $pengajuan->transferAntarCabang()->where('flag', true)->pluck('id_to_acb')->toArray();
        $linkedDokumen = array_merge($linkedSuratJalans, $linkedToAcbs);

        $editData = [
            'id_pengajuan_sewa' => $pengajuan->id_pengajuan_sewa,
            'jenis_pengajuan' => $pengajuan->jenis_pengajuan,
            'id_cabang' => $pengajuan->id_cabang,
            'id_cabang_tujuan' => $pengajuan->id_cabang_tujuan,
            'tanggal_pengiriman' => $pengajuan->tanggal_pengiriman->format('Y-m-d'),
            'harga_sewa' => (float) $pengajuan->harga_sewa,
            'value_muatan' => (float) $pengajuan->value_muatan,
            'tujuan_penyewaan' => $pengajuan->tujuan_penyewaan,
            'kategori_toko' => $pengajuan->kategori_toko,
            'id_skill' => array_map('trim', explode(',', $pengajuan->id_skill)),
            'catatan_pengajuan' => $pengajuan->catatan_pengajuan,
            'biaya_tambahan' => $pengajuan->biayaTambahan->map(function ($biaya) {
                return [
                    'id_jenis_biaya' => (int) $biaya->id_jenis_biaya,
                    'nominal' => (float) $biaya->jumlah,
                ];
            })->toArray(),
            'dokumen_dipilih' => $linkedDokumen, // Prefill linked dokumen dari junction table
        ];

        if ($pengajuan->jenis_pengajuan === 'sewa_truk') {
            $editData['id_kendaraan'] = $pengajuan->id_kendaraan;
            $editData['usulan_status'] = $pengajuan->usulan_status;
            $editData['usulan_harga_sewa'] = (bool) $pengajuan->usulan_harga_sewa
                && ! in_array($pengajuan->usulan_status, ['approved', 'rejected'], true);
            $editData['kendaraan'] = [
                'id' => $pengajuan->kendaraan->id_kendaraan,
                'nama' => $pengajuan->kendaraan->perusahaan->nama_perusahaan,
                'kendaraan' => $pengajuan->kendaraan->jenis_kendaraan,
                'muatan' => (int) $pengajuan->kendaraan->muatan_maksimal.' Ton',
                'muatan_raw' => (float) $pengajuan->kendaraan->muatan_maksimal,
                'skill' => $this->resolveSkillNames($pengajuan->kendaraan->id_skill),
                'updated_at' => $pengajuan->kendaraan->updated_at->format('d M Y'),
            ];
        } else { // pengiriman_rutin
            $editData['id_perusahaan_ekspedisi'] = $pengajuan->id_perusahaan_ekspedisi;
            $editData['perusahaan'] = [
                'id' => $pengajuan->perusahaanEkspedisi->id_perusahaan,
                'id_perusahaan' => $pengajuan->perusahaanEkspedisi->id_perusahaan,
                'nama_perusahaan' => $pengajuan->perusahaanEkspedisi->nama_perusahaan,
            ];
            $editData['id_vendor_skill_list'] = PerusahaanSkill::where('id_perusahaan', $pengajuan->id_perusahaan_ekspedisi)
                ->where('cabang_code', $pengajuan->id_cabang)
                ->where('flag', true)
                ->pluck('id_vendor_skill')
                ->values()
                ->toArray();
            $editData['detail_kiriman_dipilih'] = $pengajuan->detailKirimanRutin()
                ->where('flag', true)
                ->get()
                ->map(function ($detail) {
                    return [
                        'id_jenis_barang' => $detail->id_jenis_barang,
                        'id_tarif_kiriman_rutin' => $detail->id_tarif_kiriman_rutin ? (int) $detail->id_tarif_kiriman_rutin : null,
                        'jenis_barang' => $detail->jenisBarang?->nama_barang ?? $detail->tarif?->jenisBarang?->nama_barang ?? 'N/A',
                        'quantity' => (float) $detail->quantity,
                        'harga_satuan' => (float) $detail->harga_satuan,
                        'subtotal' => (float) $detail->subtotal,
                        'tarif_baru' => $detail->id_tarif_kiriman_rutin === null,
                        'usulan_status' => $detail->usulan_status,
                        // terkunci (sudah diputuskan) => state client false, checkbox diganti badge
                        'usulan_update_master' => (bool) $detail->usulan_update_master
                            && ! in_array($detail->usulan_status, ['approved', 'rejected'], true),
                        // snapshot lama dipertahankan kalau beda dari master saat ini
                        'harga_custom' => $detail->id_tarif_kiriman_rutin !== null
                            && $detail->tarif !== null
                            && (float) $detail->tarif->biaya_per_unit !== (float) $detail->harga_satuan,
                    ];
                })->toArray();
        }

        return view('pages.pengajuan.index', ['editPengajuan' => $editData]);
    }

    public function update(Request $request, $id)
    {
        $pengajuan = PengajuanSewa::findOrFail($id);

        // Authorization checks
        if (! auth()->user()->canAccessCabang($pengajuan->id_cabang)) {
            abort(403);
        }

        if ($pengajuan->submitted_by != auth()->id()) {
            abort(403);
        }

        if (! $pengajuan->bisaDieditPengaju()) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan sudah divalidasi WM, tidak bisa diedit.',
            ], 403);
        }

        // Base validation common to both jenis_pengajuan
        $baseRules = [
            'jenis_pengajuan' => 'required|in:sewa_truk,pengiriman_rutin',
            'tanggal_pengiriman' => 'required|date',
            'tujuan_penyewaan' => 'required|in:Toko,PAC',
            'id_cabang_tujuan' => 'required_if:tujuan_penyewaan,PAC|nullable|string|max:10|exists:sqlsrv.dbo.sesi_master_cabang,Code',
            'id_skill' => 'nullable|array',
            'id_skill.*' => 'required|integer|exists:sqlsrv.dbo.sesi_master_skill,id_skill',
            'skill_baru' => 'nullable|array',
            'skill_baru.*' => 'nullable|string|max:50',
            'kategori_toko' => 'required|string',
            'catatan' => 'nullable|string',
            'biaya_tambahan' => 'nullable|array',
            'biaya_tambahan.*.id_jenis_biaya' => 'required|integer|exists:sqlsrv.dbo.sesi_jenis_biaya,id_jenis_biaya',
            'biaya_tambahan.*.nominal' => 'required|numeric|min:0',
        ];

        // Branch-specific validation
        if ($request->jenis_pengajuan === 'sewa_truk') {
            $baseRules['id_kendaraan'] = 'required|integer|exists:sqlsrv.dbo.sesi_unit_kendaraan,id_kendaraan';
            $baseRules['harga_sewa'] = 'required|numeric|min:0';
            $baseRules['usulan_harga_sewa'] = 'nullable|boolean';
        } else { // pengiriman_rutin
            $baseRules['id_perusahaan_ekspedisi'] = 'required|integer|exists:sqlsrv.dbo.sesi_perusahaan_ekspedisi,id_perusahaan';
            $baseRules['detail_kiriman'] = 'required|array|min:1';
            $baseRules['detail_kiriman.*.id_jenis_barang'] = 'required|integer|exists:sqlsrv.dbo.sesi_jenis_barang_kiriman,id_jenis_barang';
            $baseRules['detail_kiriman.*.quantity'] = 'required|numeric|min:0.01';
            $baseRules['detail_kiriman.*.biaya_per_unit'] = 'nullable|numeric|min:0.01';
            $baseRules['detail_kiriman.*.usulan_update_master'] = 'nullable|boolean';
            $baseRules['detail_kiriman.*.harga_custom'] = 'nullable|boolean';
        }

        $request->validate($baseRules + $this->aturanDokumen($request), [
            'dokumen_dipilih.required' => 'Kiriman Rutin tujuan PAC wajib memilih minimal 1 TO-ACB.',
            'dokumen_dipilih.min' => 'Kiriman Rutin tujuan PAC wajib memilih minimal 1 TO-ACB.',
        ]);
        $this->cekJumlahArea($request);

        try {
            DB::connection('sqlsrv')->beginTransaction();

            // Kunci baris & cek ulang: jangan sampai WM memvalidasi di tengah proses edit
            $pengajuan = PengajuanSewa::lockForUpdate()->findOrFail($id);
            if (! $pengajuan->bisaDieditPengaju()) {
                DB::connection('sqlsrv')->rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Pengajuan sudah divalidasi WM, tidak bisa diedit.',
                ], 403);
            }

            $detailKiriman = $this->kunciHargaSaatEdit($request, $pengajuan);

            $user = auth()->user();
            $cabangId = $user->getCabangId();

            if ($pesan = $this->cekCabangTujuan($request, $cabangId)) {
                DB::connection('sqlsrv')->rollBack();

                return response()->json(['success' => false, 'message' => $pesan], 422);
            }

            $allSkills = collect($request->id_skill)
                ->map(fn ($s) => (int) $s)
                ->filter()
                ->values();

            $skillBaru = collect($request->skill_baru ?? [])
                ->map(fn ($s) => strtoupper(trim($s)))
                ->filter()
                ->values();

            foreach ($skillBaru as $nama) {
                $idSkillBaru = $this->resolveSkillId($nama, $cabangId);
                if ($idSkillBaru) {
                    $allSkills->push($idSkillBaru);
                }
            }

            $allSkills = $allSkills->unique()->values();
            $idSkillStr = $allSkills->implode(',');

            // Branch: compute harga_sewa based on jenis_pengajuan
            $hargaSewa = 0;
            if ($request->jenis_pengajuan === 'sewa_truk') {
                $hargaSewa = $request->harga_sewa;
            } else { // pengiriman_rutin
                $this->pastikanVendorSkill((int) $request->id_perusahaan_ekspedisi, $cabangId, $allSkills);

                foreach ($detailKiriman as $detail) {
                    $resolved = $this->resolveHargaKirimanRutin((int) $request->id_perusahaan_ekspedisi, $cabangId, $allSkills, $detail);
                    $hargaSewa += $resolved['biaya_per_unit'] * $detail['quantity'];
                }
            }

            // Hitung rasio sewa termasuk biaya tambahan
            $totalBiayaTambahan = collect($request->biaya_tambahan ?? [])->sum('nominal');
            $rasioSewa = $request->value_muatan > 0
                ? (($hargaSewa + $totalBiayaTambahan) / $request->value_muatan) * 100
                : null;
            $alurApproval = $this->hitungAlurApproval($request->jenis_pengajuan, $rasioSewa ?? 0.0, $request->tujuan_penyewaan);
            $kategoriApproval = $alurApproval === 'WM' ? 'normal' : 'over_threshold';

            $updateData = [
                'id_cabang_tujuan' => $request->tujuan_penyewaan === 'PAC' ? $request->id_cabang_tujuan : null,
                'tanggal_pengiriman' => $request->tanggal_pengiriman,
                'value_muatan' => $request->value_muatan ?: null,
                'harga_sewa' => $hargaSewa,
                'rasio_sewa' => $rasioSewa !== null ? round($rasioSewa, 2) : null,
                'kategori_approval' => $kategoriApproval,
                'alur_approval' => $alurApproval,
                'tujuan_penyewaan' => $request->tujuan_penyewaan,
                'id_skill' => $idSkillStr,
                'kategori_toko' => $request->kategori_toko,
                'status_pengajuan' => 'Pending',
                'catatan_pengajuan' => $request->catatan,
                'jenis_pengajuan' => $request->jenis_pengajuan,
                // Edit = diajukan ulang (cuma bisa sebelum WM validasi, lihat bisaDieditPengaju)
                'submitted_at' => now(),
            ];

            if ($request->jenis_pengajuan === 'sewa_truk') {
                $updateData['id_kendaraan'] = $request->id_kendaraan;
                $updateData['id_perusahaan_ekspedisi'] = null;
                // Usulan harga (usulan_*) tidak disentuh saat edit — harga terkunci
            } else { // pengiriman_rutin
                $updateData['id_kendaraan'] = null;
                $updateData['id_perusahaan_ekspedisi'] = $request->id_perusahaan_ekspedisi;
            }

            $pengajuan->update($updateData);
            $this->sinkronDokumen($pengajuan, $request);

            BiayaTambahan::where('id_pengajuan_sewa', $pengajuan->id_pengajuan_sewa)->delete();

            if ($request->biaya_tambahan) {
                foreach ($request->biaya_tambahan as $biaya) {
                    BiayaTambahan::create([
                        'id_pengajuan_sewa' => $pengajuan->id_pengajuan_sewa,
                        'id_jenis_biaya' => $biaya['id_jenis_biaya'],
                        'jumlah' => $biaya['nominal'],
                        'flag' => true,
                    ]);
                }
            }

            if ($request->jenis_pengajuan === 'pengiriman_rutin' && $detailKiriman) {
                // Usulan harga baris lama (status apa pun) dibawa apa adanya; baris baru boleh mengusulkan
                $barisLama = DetailKirimanRutin::where('id_pengajuan_sewa', $pengajuan->id_pengajuan_sewa)
                    ->where('flag', true)
                    ->get()
                    ->keyBy('id_jenis_barang');

                // Flag lama menjadi false (soft delete)
                DetailKirimanRutin::where('id_pengajuan_sewa', $pengajuan->id_pengajuan_sewa)
                    ->where('flag', true)
                    ->update(['flag' => false]);

                // Insert baru
                foreach ($detailKiriman as $detail) {
                    $lama = $barisLama->get($detail['id_jenis_barang']);
                    $resolved = $this->resolveHargaKirimanRutin((int) $request->id_perusahaan_ekspedisi, $cabangId, $allSkills, $detail);
                    $subtotal = $resolved['biaya_per_unit'] * $detail['quantity'];
                    // Barang baru tanpa tarif master selalu jadi usulan harga master
                    $usulanBaru = ! $lama && ($resolved['id_tarif'] === null || ! empty($detail['usulan_update_master']));

                    DetailKirimanRutin::create([
                        'id_pengajuan_sewa' => $pengajuan->id_pengajuan_sewa,
                        'id_jenis_barang' => $detail['id_jenis_barang'],
                        'id_tarif_kiriman_rutin' => $resolved['id_tarif'], // null kalau harga ad-hoc
                        'quantity' => $detail['quantity'],
                        'harga_satuan' => $resolved['biaya_per_unit'], // snapshot
                        'subtotal' => $subtotal, // snapshot
                        'flag' => true,
                        'created_at' => now(),
                        'usulan_update_master' => $lama ? (bool) $lama->usulan_update_master : $usulanBaru,
                        'usulan_status' => $lama ? $lama->usulan_status : ($usulanBaru ? 'pending' : null),
                        'usulan_decided_by' => $lama?->usulan_decided_by,
                        'usulan_decided_at' => $lama?->usulan_decided_at,
                    ]);
                }
            }

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan berhasil diperbarui',
                'id_pengajuan' => $pengajuan->id_pengajuan_sewa,
            ]);
        } catch (\InvalidArgumentException $e) {
            DB::connection('sqlsrv')->rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal update pengajuan: '.$e->getMessage(),
            ], 500);
        }
    }

    /** GET /pengajuan/{id}/buka — link email: approver (WM/WC/WH) ke halaman review, role lain ke detail. */
    public function buka($id)
    {
        $role = auth()->user()->userUtility?->role;

        return in_array($role, ApprovalController::PERAN_APPROVER, true)
            ? redirect()->route('approval.show', $id)
            : redirect()->route('pengajuan.show', $id);
    }

    public function show($id)
    {
        $pengajuan = PengajuanSewa::with([
            'kendaraan.perusahaan',
            'perusahaanEkspedisi',
            'detailKirimanRutin.jenisBarang',
            'submittedBy',
            'biayaTambahan.jenisBiaya',
            'approvalLogs.approver',
            'approvalLogs.approval',
            'suratJalans',
            'transferAntarCabang',
        ])->findOrFail($id);

        if (! auth()->user()->canAccessCabang($pengajuan->id_cabang)) {
            abort(403);
        }

        // KA (KaAdmin) view-only: cuma boleh lihat pengajuan yang sudah approved
        if (auth()->user()->userUtility?->role === 'KA' && strtolower($pengajuan->status_pengajuan) !== 'approved') {
            abort(403);
        }

        $timeline = collect($pengajuan->approvalLogs)->map(function ($log) {
            return [
                'type' => 'approval',
                'status' => $log->status,
                'peran' => $log->peran,
                'approver' => $log->approver,
                'decided_at' => $log->decided_at,
                'reason' => $log->alasan_penolakan,
            ];
        });

        if ($pengajuan->status_pengajuan === 'Pending' && $timeline->isNotEmpty()) {
            $lastRejection = $timeline
                ->where('status', 'Rejected')
                ->sortBy('decided_at')
                ->last();

            if ($lastRejection && $pengajuan->updated_at > $lastRejection['decided_at']) {
                // Insert synthetic resubmit entry di posisi yang tepat (berdasarkan waktu)
                $timeline->push([
                    'type' => 'resubmit',
                    'aktor' => $pengajuan->submittedBy,
                    'decided_at' => $pengajuan->updated_at,
                ]);
            }
        }

        if ($pengajuan->dibatalkan_at) {
            $timeline->push([
                'type' => 'dibatalkan',
                'aktor' => $pengajuan->submittedBy,
                'decided_at' => $pengajuan->dibatalkan_at,
                'reason' => $pengajuan->alasan_pembatalan,
            ]);
        }

        $timeline = $timeline->sortBy('decided_at')->values();

        $rasioSewaSetting = RasioSewa::aktif();
        $ambangRasio = $rasioSewaSetting ? $rasioSewaSetting->persentase_maksimal : 2.5;

        return view('pages.pengajuan.detailPengajuan', [
            'pengajuan' => $pengajuan,
            'dokumen' => app(SnapshotDokumenService::class)->ambil($pengajuan),
            'timeline' => $timeline,
            'ambangRasio' => $ambangRasio,
            'alurApproval' => $pengajuan->alurApproval(),
            'peranSudahApprove' => $pengajuan->peranSudahApprove(),
            'approverBerikutnya' => $pengajuan->approverBerikutnya(),
            'breadcrumb' => [
                'back_url' => route('dashboard'),
                'back_label' => 'Dashboard',
                'title' => 'Detail Pengajuan',
                'status' => strtolower($pengajuan->status_pengajuan),
                'jenis' => $pengajuan->jenis_pengajuan,
            ],
        ]);
    }

    public function getPerusahaanList(Request $request)
    {
        $user = auth()->user();
        $jenis = $request->get('jenis', 'sewa_truk');
        $search = $request->get('search');
        $own = $this->ownCabang();

        // Opsi B (keputusan Jo 28 Sept — sebelumnya perusahaan baru selalu hilang dari
        // list ini setelah pindah pilihan/refresh): lihat ownOrUnclaimedScope() di trait.
        $extraScope = $this->ownOrUnclaimedScope($own);

        $perusahaan = $this->summaryRows($own, $search, 5, $extraScope);

        if ($jenis === 'pengiriman_rutin' && $perusahaan->isNotEmpty()) {
            $idPerusahaanList = $perusahaan->pluck('id_perusahaan')->all();
            $vendorSkillQuery = PerusahaanSkill::whereIn('id_perusahaan', $idPerusahaanList)
                ->where('flag', true);
            if (! $user->isGlobalAccess()) {
                $cabangIds = $user->getCabangIds() ?: array_values(array_filter([$user->getCabangId()]));
                $vendorSkillQuery->whereIn('cabang_code', $cabangIds ?: ['__none__']);
            }
            $vendorSkills = $vendorSkillQuery->get(['id_vendor_skill', 'id_perusahaan']);
            $idVendorSkillList = $vendorSkills->pluck('id_vendor_skill')->all();

            $tarifByVendorSkill = empty($idVendorSkillList) ? collect() : TarifKirimanRutin::with('jenisBarang')
                ->whereIn('id_vendor_skill', $idVendorSkillList)
                ->where('flag', true)
                ->get()
                ->groupBy('id_vendor_skill');

            $tarifByPerusahaan = [];
            foreach ($vendorSkills as $vs) {
                foreach ($tarifByVendorSkill->get($vs->id_vendor_skill, collect()) as $t) {
                    $tarifByPerusahaan[$vs->id_perusahaan][] = [
                        'nama_barang' => $t->jenisBarang->nama_barang ?? '—',
                        'harga' => (float) $t->biaya_per_unit,
                    ];
                }
            }

            $perusahaan = $perusahaan->map(function ($p) use ($tarifByPerusahaan) {
                $p->tarif_breakdown = $tarifByPerusahaan[$p->id_perusahaan] ?? [];

                return $p;
            });
        }

        return response()->json(['data' => $perusahaan->values()]);
    }

    /**
     * Create perusahaan baru + upload identitas owner
     */
    public function storePerusahaan(Request $request)
    {
        $request->validate([
            'nama_perusahaan' => 'required|string|max:255|unique:sqlsrv.dbo.sesi_perusahaan_ekspedisi,nama_perusahaan',
            'badan_usaha' => 'required|in:PT,CV,UD,Perseorangan',
            'no_telepon' => 'required|string|max:20',
            'alamat_kantor' => 'required|string',
            'identitas_owner' => 'required|array|min:1|max:3',
            'identitas_owner.*' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        try {
            DB::connection('sqlsrv')->beginTransaction();

            $perusahaan = PerusahaanEkspedisi::create([
                'nama_perusahaan' => $request->nama_perusahaan,
                'badan_usaha' => $request->badan_usaha,
                'no_telepon' => $request->no_telepon,
                'alamat_kantor' => $request->alamat_kantor,
                'flag' => true,
            ]);

            if ($request->hasFile('identitas_owner')) {
                $perusahaan->identitas_owner = $this->storeIdentitasOwnerFiles(
                    $request->file('identitas_owner'),
                    (string) $perusahaan->id_perusahaan
                );
                $perusahaan->save();
            }

            $idVendorSkillList = $this->vendorSkillIdsDiCabang($perusahaan->id_perusahaan, auth()->user()->getCabangId());

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Perusahaan berhasil disimpan',
                'perusahaan' => $perusahaan,
                'id_vendor_skill_list' => $idVendorSkillList,
            ], 201);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan perusahaan: '.$e->getMessage(),
            ], 500);
        }
    }

    public function updateVendor(Request $request, int $id)
    {
        $vendor = PerusahaanEkspedisi::where('flag', true)->findOrFail($id);

        $data = $request->validate([
            'nama_perusahaan' => 'nullable|string|max:255|unique:sqlsrv.dbo.sesi_perusahaan_ekspedisi,nama_perusahaan,'.$id.',id_perusahaan',
            'badan_usaha' => 'required|in:PT,CV,UD,Perseorangan',
            'no_telepon' => 'required|string|max:20',
            'alamat_kantor' => 'required|string',
            'identitas_owner_keep' => 'nullable|array',
            'identitas_owner_keep.*' => 'string',
            'identitas_owner' => 'nullable|array',
            'identitas_owner.*' => 'file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        try {
            DB::connection('sqlsrv')->beginTransaction();

            if (! empty($data['nama_perusahaan'])) {
                $vendor->nama_perusahaan = $data['nama_perusahaan'];
            }
            $vendor->badan_usaha = $data['badan_usaha'];
            $vendor->no_telepon = $data['no_telepon'];
            $vendor->alamat_kantor = $data['alamat_kantor'];

            $existing = $vendor->identitas_owner ?? [];
            $newFiles = $request->hasFile('identitas_owner') ? $request->file('identitas_owner') : [];

            if ($request->has('identitas_owner_keep')) {
                $keep = array_values(array_intersect($existing, $request->input('identitas_owner_keep', [])));
                $toDelete = array_values(array_diff($existing, $keep));

                if (count($keep) + count($newFiles) > 3) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Maksimal 3 foto identitas owner.',
                    ], 422);
                }

                if (! empty($toDelete)) {
                    $this->deleteIdentitasOwnerFiles($toDelete);
                }

                $newPaths = ! empty($newFiles)
                    ? $this->storeIdentitasOwnerFiles($newFiles, (string) $vendor->id_perusahaan, count($keep) + 1)
                    : [];

                $vendor->identitas_owner = array_values(array_merge($keep, $newPaths));
            } elseif (! empty($newFiles)) {
                $this->deleteIdentitasOwnerFiles($existing);
                $vendor->identitas_owner = $this->storeIdentitasOwnerFiles(
                    $newFiles,
                    (string) $vendor->id_perusahaan
                );
            }

            $vendor->save();
            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Data vendor berhasil diperbarui',
                'perusahaan' => $vendor->fresh(),
            ]);
        } catch (\Throwable $e) {
            DB::connection('sqlsrv')->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui vendor: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch kendaraan by perusahaan ID (untuk ditampilkan setelah user pilih perusahaan)
     */
    public function getKendaraanByPerusahaan(Request $request)
    {
        $request->validate(['perusahaan_id' => 'required|integer']);

        try {
            $user = auth()->user();

            $query = DB::connection('sqlsrv')->table('sesi_unit_kendaraan')
                ->leftJoin('sesi_perusahaan_ekspedisi', 'sesi_unit_kendaraan.id_perusahaan', '=', 'sesi_perusahaan_ekspedisi.id_perusahaan')
                ->select(
                    'sesi_unit_kendaraan.id_kendaraan as id',
                    'sesi_perusahaan_ekspedisi.badan_usaha as badan_usaha',
                    'sesi_perusahaan_ekspedisi.nama_perusahaan as nama',
                    'sesi_unit_kendaraan.id_skill as skill',
                    'sesi_unit_kendaraan.jenis_kendaraan as kendaraan',
                    'sesi_unit_kendaraan.plat_nomor_truk',
                    'sesi_unit_kendaraan.muatan_maksimal as muatan_raw',
                    DB::raw("FORMAT(sesi_unit_kendaraan.updated_at, 'dd MMM yyyy') as updated")
                )
                ->where('sesi_unit_kendaraan.id_perusahaan', $request->perusahaan_id)
                ->where('sesi_unit_kendaraan.flag', true);

            // Scope ke cabang user (WH/DCI global access lihat semua)
            if (! $user->isGlobalAccess()) {
                $cabangIds = $user->getCabangIds() ?: array_values(array_filter([$user->getCabangId()]));
                $query->whereIn('sesi_unit_kendaraan.id_cabang', $cabangIds ?: ['__none__']);
            }

            $kendaraan = $query
                ->orderByDesc('sesi_unit_kendaraan.updated_at')
                ->get()
                ->map(function ($a) {
                    return [
                        ...(array) $a,
                        'skill' => $this->resolveSkillNames($a->skill),
                        'muatan' => FormatHelper::ton($a->muatan_raw),
                    ];
                });

            return response()->json(['data' => $kendaraan]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal fetch kendaraan: '.$e->getMessage(),
            ], 500);
        }
    }

    public function resolveRateCard(Request $request)
    {
        $request->validate([
            'id_perusahaan_ekspedisi' => 'required|integer|exists:sqlsrv.dbo.sesi_perusahaan_ekspedisi,id_perusahaan',
        ]);

        $cabangId = auth()->user()->getCabangId();
        if (! $cabangId) {
            return response()->json(['id_vendor_skill' => [], 'areas' => []]);
        }

        $ids = $this->vendorSkillIdsDiCabang((int) $request->id_perusahaan_ekspedisi, $cabangId);

        // Daftar area milik vendor di cabang user, untuk pilihan area kirim di wizard.
        $rows = PerusahaanSkill::whereIn('id_vendor_skill', $ids)->get(['id_vendor_skill', 'id_skill']);
        $skillNames = DB::connection('sqlsrv')->table('sesi_master_skill')
            ->whereIn('id_skill', $rows->pluck('id_skill'))
            ->pluck('nama_skill', 'id_skill');

        $areas = $rows->map(fn ($r) => [
            'id_vendor_skill' => $r->id_vendor_skill,
            'id_skill' => (string) $r->id_skill,
            'nama_skill' => $skillNames->get($r->id_skill) ?? ('Skill #'.$r->id_skill),
        ])->sortBy('nama_skill')->values();

        return response()->json([
            'id_vendor_skill' => $ids,
            'areas' => $areas,
        ]);
    }

    /**
     * Urutan approver pengajuan sewa. WH ikut kalau rasio sewa truk di atas batas;
     * WC kalau PAC (lihat PengajuanSewa::hitungAlur). Kiriman rutin tidak pakai rasio.
     * Area baru dan vendor/harga baru tidak memengaruhi alur.
     */
    private function hitungAlurApproval(string $jenisPengajuan, float $rasioSewa, string $tujuanPenyewaan): string
    {
        $rasioMaks = RasioSewa::aktif()?->persentase_maksimal ?? 2.5;

        $butuhWh = PengajuanSewa::pakaiRasioUntuk($jenisPengajuan, $tujuanPenyewaan) && $rasioSewa > $rasioMaks;

        return PengajuanSewa::hitungAlur($tujuanPenyewaan === 'PAC', $butuhWh);
    }

    /**
     * Harga tidak bisa diubah lewat edit pengajuan; vendor/kendaraan ikut dikunci karena
     * harga melekat ke vendor. Kiriman rutin: baris yang sudah tersimpan dipaksa ke harga
     * snapshot lama, barang baru diproses seperti pengajuan baru.
     */
    private function kunciHargaSaatEdit(Request $request, PengajuanSewa $pengajuan): array
    {
        if ($request->jenis_pengajuan !== $pengajuan->jenis_pengajuan) {
            throw new \InvalidArgumentException('Jenis pengajuan tidak bisa diubah saat edit.');
        }

        if ($pengajuan->jenis_pengajuan === 'sewa_truk') {
            if ((int) $request->id_kendaraan !== (int) $pengajuan->id_kendaraan) {
                throw new \InvalidArgumentException('Kendaraan/vendor tidak bisa diganti saat edit pengajuan.');
            }
            if (abs((float) $request->harga_sewa - (float) $pengajuan->harga_sewa) >= 0.01) {
                throw new \InvalidArgumentException('Harga sewa tidak bisa diubah saat edit. Gunakan pengajuan perubahan harga.');
            }

            return [];
        }

        if ((int) $request->id_perusahaan_ekspedisi !== (int) $pengajuan->id_perusahaan_ekspedisi) {
            throw new \InvalidArgumentException('Vendor tidak bisa diganti saat edit pengajuan.');
        }

        $barisLama = DetailKirimanRutin::where('id_pengajuan_sewa', $pengajuan->id_pengajuan_sewa)
            ->where('flag', true)
            ->get()
            ->keyBy('id_jenis_barang');

        return collect($request->detail_kiriman ?? [])->map(function ($detail) use ($barisLama) {
            $lama = $barisLama->get($detail['id_jenis_barang']);
            $diminta = $detail['biaya_per_unit'] ?? null;

            if ($lama) {
                if ($diminta !== null && $diminta !== '' && abs((float) $diminta - (float) $lama->harga_satuan) >= 0.01) {
                    throw new \InvalidArgumentException('Harga per unit tidak bisa diubah saat edit. Gunakan pengajuan perubahan harga.');
                }
                $detail['biaya_per_unit'] = (float) $lama->harga_satuan;
                $detail['harga_custom'] = true;
                $detail['usulan_update_master'] = false;
            }

            return $detail;
        })->all();
    }

    /** Area (vendor-skill aktif) milik vendor di satu cabang. Read-only. */
    private function vendorSkillIdsDiCabang(int $idPerusahaan, ?string $cabangId): array
    {
        if (! $cabangId) {
            return [];
        }

        return PerusahaanSkill::where('id_perusahaan', $idPerusahaan)
            ->where('cabang_code', $cabangId)
            ->where('flag', true)
            ->orderBy('id_skill')
            ->pluck('id_vendor_skill')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Pastikan vendor punya baris area aktif untuk tiap skill (dibuat kalau belum ada). */
    private function pastikanVendorSkill(int $idPerusahaan, string $cabangId, Collection $skillIds): void
    {
        foreach ($skillIds as $skillId) {
            $row = PerusahaanSkill::withInactive()->firstOrCreate(
                ['id_perusahaan' => $idPerusahaan, 'id_skill' => $skillId, 'cabang_code' => $cabangId],
                ['flag' => true]
            );
            if (! $row->flag) {
                $row->update(['flag' => true]);
            }
        }
    }

    /** Minimal 1 area kirim (id_skill atau skill_baru); Kiriman Rutin tepat 1. */
    private function cekJumlahArea(Request $request): void
    {
        $jumlah = count(array_filter((array) $request->id_skill))
            + count(array_filter((array) ($request->skill_baru ?? []), fn ($s) => trim((string) $s) !== ''));

        if ($jumlah === 0) {
            throw ValidationException::withMessages(['id_skill' => 'Pilih minimal 1 area kirim.']);
        }
        if ($request->jenis_pengajuan === 'pengiriman_rutin' && $jumlah !== 1) {
            throw ValidationException::withMessages(['id_skill' => 'Kiriman Rutin hanya boleh memilih 1 area kirim.']);
        }
    }

    /**
     * POST /api/pengajuan/{id}/notifikasi-baru — dipanggil frontend SETELAH SJ/TO-ACB ter-link
     * (link dilakukan lewat request terpisah pasca-submit), biar daftar SJ di email tidak kosong.
     * ulang=1 dipakai jalur edit/resubmit.
     */
    public function notifikasiBaru(Request $request, $id)
    {
        $pengajuan = PengajuanSewa::findOrFail($id);

        if (! $this->milikPengaju($pengajuan)) {
            return response()->json(['success' => false, 'message' => 'Bukan pengajuan Anda'], 403);
        }
        if (strtolower($pengajuan->status_pengajuan) !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Pengajuan tidak berstatus pending'], 422);
        }

        try {
            app(NotifikasiPengajuanService::class)->pengajuanBaru($pengajuan, $request->boolean('ulang'));
        } catch (\Throwable $e) {
            \Log::warning('Notifikasi pengajuan baru gagal: '.$e->getMessage());
        }

        return response()->json(['success' => true]);
    }

    /** POST /api/pengajuan/{id}/batalkan — pengaju membatalkan pengajuan yang masih pending. */
    public function batalkan(Request $request, $id)
    {
        $request->validate(['alasan' => 'required|string|max:500']);

        $pengajuan = PengajuanSewa::findOrFail($id);

        if (! $this->milikPengaju($pengajuan)) {
            return response()->json(['success' => false, 'message' => 'Bukan pengajuan Anda'], 403);
        }
        if (! $pengajuan->bisaDibatalkanPengaju()) {
            return response()->json(['success' => false, 'message' => 'Hanya pengajuan berstatus pending yang bisa dibatalkan'], 422);
        }

        $peranGiliran = $pengajuan->approverBerikutnya();
        $pengajuan->update([
            'status_pengajuan' => 'Cancelled',
            'dibatalkan_at' => now(),
            'alasan_pembatalan' => trim($request->alasan),
        ]);

        try {
            app(NotifikasiPengajuanService::class)->dibatalkan($pengajuan, $peranGiliran);
        } catch (\Throwable $e) {
            \Log::warning('Notifikasi pembatalan gagal: '.$e->getMessage());
        }

        return response()->json(['success' => true, 'message' => 'Pengajuan dibatalkan']);
    }

    private function milikPengaju(PengajuanSewa $pengajuan): bool
    {
        return strcasecmp((string) $pengajuan->pengaju?->username, (string) auth()->user()->username) === 0;
    }
}
