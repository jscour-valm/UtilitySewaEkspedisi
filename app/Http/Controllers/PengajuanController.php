<?php

namespace App\Http\Controllers;

use App\Models\Kendaraan;
use App\Models\PerusahaanEkspedisi;
use App\Models\PengajuanSewa;
use App\Models\RasioSewa;
use App\Models\JenisBiaya;
use App\Models\BiayaTambahan;
use App\Models\TarifKirimanRutin;
use App\Models\DetailKirimanRutin;
use App\Models\PerusahaanSkill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Helpers\DbHelper;
use App\Http\Controllers\Concerns\ManagesVendorMasterData;

class PengajuanController extends Controller
{
    use ManagesVendorMasterData;

    public function storeKendaraan(Request $request)
    {
        $request->validate([
            'perusahaan_id'   => 'required|exists:sqlsrv.dbo.sesi_perusahaan_ekspedisi,id_perusahaan',
            // id_skill = area yang SUDAH ADA (numerik, dipilih via checkbox) — divalidasi exists.
            // skill_baru = nama area baru (teks bebas) — di-resolve/insert lewat resolveSkillId().
            // Dipisah biar konsisten sama submitPengajuan()/update() — jangan digabung jadi 1
            // array lagi, itu bikin resolveSkillId() nganggep id numerik existing sbg nama baru
            // dan bikin baris sesi_master_skill sampah (nama_skill = angka itu sendiri).
            'id_skill'        => 'nullable|array',
            'id_skill.*'      => 'integer|exists:sqlsrv.dbo.sesi_master_skill,id_skill',
            'skill_baru'      => 'nullable|array',
            'skill_baru.*'    => 'nullable|string|max:100',
            'jenis_kendaraan'  => 'nullable|string|max:100',
            'plat_nomor_truk'      => 'nullable|string|max:20|unique:sqlsrv.dbo.sesi_unit_kendaraan,plat_nomor_truk',
            'muatan_maksimal' => 'required|numeric|min:0.01',
        ]);

        // Minimal 1 area (existing atau baru) — dicek manual krn keduanya array optional.
        if (empty($request->id_skill) && empty(array_filter($request->skill_baru ?? []))) {
            return response()->json([
                'success' => false,
                'message' => 'Pilih atau tambahkan minimal 1 area/skill.',
            ], 422);
        }

        try {
            DB::connection('sqlsrv')->beginTransaction();

            $user     = auth()->user();
            $cabangId = $user->getCabangId();
            $userId   = $user->id;

            if (!$cabangId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cabang tidak ditemukan dari username',
                ], 400);
            }

            // Perusahaan sudah ada (dibuat terpisah di storePerusahaan), cukup gunakan ID
            $perusahaan = PerusahaanEkspedisi::findOrFail($request->perusahaan_id);

            // id_skill existing (numerik, sudah divalidasi exists) dipakai apa adanya;
            // skill_baru (nama bebas) di-resolve/insert dulu jadi id numeric.
            $skillIds = collect($request->id_skill ?? [])->map(fn ($s) => (int) $s);
            foreach (collect($request->skill_baru ?? [])->filter() as $nama) {
                $idBaru = $this->resolveSkillId($nama, $cabangId);
                if ($idBaru) {
                    $skillIds->push($idBaru);
                }
            }
            $skillString = $skillIds->unique()->values()->implode(',');

            // Create kendaraan
            $kendaraan = Kendaraan::create([
                'id_perusahaan'  => $perusahaan->id_perusahaan,
                'id_cabang'      => $cabangId,
                'id_skill'       => $skillString,
                'jenis_kendaraan' => $request->jenis_kendaraan ? $request->jenis_kendaraan : null,
                'plat_nomor_truk'     => $request->plat_nomor_truk ? strtoupper($request->plat_nomor_truk) : null,
                'muatan_maksimal'=> $request->muatan_maksimal,
                'flag'           => true,
            ]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Kendaraan berhasil ditambahkan',
                'kendaraan'  => [
                    'id'              => $kendaraan->id_kendaraan,
                    'nama'            => $perusahaan->nama_perusahaan,
                    'kendaraan'       => $kendaraan->jenis_kendaraan,
                    'plat_nomor_truk' => $kendaraan->plat_nomor_truk,
                    'muatan'          => (int)$kendaraan->muatan_maksimal . ' Ton',
                    'muatan_raw'      => $kendaraan->muatan_maksimal,
                    'harga'           => 'Rp 0',
                    'skill'           => $this->resolveSkillNames($kendaraan->id_skill),
                    'updated'         => now()->format('d M Y'),
                ],
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan kendaraan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function submitPengajuan(Request $request)
    {
        // Base validation common to both jenis_pengajuan
        $baseRules = [
            'jenis_pengajuan'    => 'required|in:sewa_truk,pengiriman_rutin',
            'tanggal_pengiriman' => 'required|date',
            'value_muatan'       => 'required|numeric|min:1',
            'tujuan_penyewaan'   => 'required|in:Toko,PAC',
            'id_skill'           => 'required|array|min:1',
            'id_skill.*'         => 'required|integer|exists:sqlsrv.dbo.sesi_master_skill,id_skill',
            'skill_baru'         => 'nullable|array',
            'skill_baru.*'       => 'nullable|string|max:50',
            'kategori_toko'      => 'required|string',
            'catatan'            => 'nullable|string',
            'biaya_tambahan'     => 'nullable|array',
            'biaya_tambahan.*.id_jenis_biaya' => 'required|integer|exists:sqlsrv.dbo.sesi_jenis_biaya,id_jenis_biaya',
            'biaya_tambahan.*.nominal'        => 'required|numeric|min:0',
        ];

        // Branch-specific validation
        if ($request->jenis_pengajuan === 'sewa_truk') {
            $baseRules['id_kendaraan'] = 'required|integer|exists:sqlsrv.dbo.sesi_unit_kendaraan,id_kendaraan';
            $baseRules['harga_sewa'] = 'required|numeric|min:0';
        } else { // pengiriman_rutin
            $baseRules['id_perusahaan_ekspedisi'] = 'required|integer|exists:sqlsrv.dbo.sesi_perusahaan_ekspedisi,id_perusahaan';
            $baseRules['detail_kiriman'] = 'required|array|min:1';
            $baseRules['detail_kiriman.*.id_jenis_barang'] = 'required|integer|exists:sqlsrv.dbo.sesi_jenis_barang_kiriman,id_jenis_barang';
            $baseRules['detail_kiriman.*.quantity'] = 'required|numeric|min:0.01';
            // biaya_per_unit cuma wajib kalau vendor belum punya tarif utk jenis barang ini —
            // dicek manual di resolveHargaKirimanRutin(), krn tergantung data existing di DB.
            $baseRules['detail_kiriman.*.biaya_per_unit'] = 'nullable|numeric|min:0.01';
        }

        $request->validate($baseRules);

        try {
            DB::connection('sqlsrv')->beginTransaction();

            $user     = auth()->user();
            $cabangId = $user->getCabangId();

            if (!$cabangId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cabang tidak ditemukan',
                ], 400);
            }

            // Gabung skill existing (udah numeric id, tervalidasi exists di master)
            // + skill baru (masih nama, di-resolve/insert dulu jadi numeric id)
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
                // Resolve harga per baris — dari tarif resmi kalau ada, atau harga
                // ad-hoc dari input user (TIDAK menulis ke tabel master tarif)
                foreach ($request->detail_kiriman as $detail) {
                    $resolved = $this->resolveHargaKirimanRutin((int) $request->id_perusahaan_ekspedisi, $cabangId, $allSkills, $detail);
                    $hargaSewa += $resolved['biaya_per_unit'] * $detail['quantity'];
                }
            }

            // Hitung rasio sewa termasuk biaya tambahan
            $totalBiayaTambahan = collect($request->biaya_tambahan ?? [])->sum('nominal');
            $rasioSewa = (($hargaSewa + $totalBiayaTambahan) / $request->value_muatan) * 100;

            // Cek area baru (ada skill yang belum pernah ada di cabang ini sebelum submit ini)
            $isAreaBaru = $skillBaru->isNotEmpty();

            // Tentukan kategori approval
            $kategoriApproval = 'normal';
            $rasioMaks = \App\Models\RasioSewa::aktif()?->persentase_maksimal ?? 2.5;
            if ($rasioSewa > $rasioMaks || $request->tujuan_penyewaan === 'PAC' || $isAreaBaru) {
                $kategoriApproval = 'over_threshold';
            }

            $pengajuanData = [
                'id_cabang'              => $cabangId,
                'tanggal_pengiriman'     => $request->tanggal_pengiriman,
                'value_muatan'           => $request->value_muatan,
                'harga_sewa'             => $hargaSewa,
                'rasio_sewa'             => round($rasioSewa, 2),
                'kategori_approval'      => $kategoriApproval,
                'tujuan_penyewaan'       => $request->tujuan_penyewaan,
                'id_skill'               => $idSkillStr,
                'kategori_toko'          => $request->kategori_toko,
                'status_pengajuan'       => 'Pending',
                'catatan_pengajuan'      => $request->catatan,
                'jenis_pengajuan'        => $request->jenis_pengajuan,
                'submitted_by'           => $user->id,
                'submitted_at'           => now(),
                'flag'                   => true,
            ];

            // Branch: add kendaraan or perusahaan based on jenis_pengajuan
            if ($request->jenis_pengajuan === 'sewa_truk') {
                $pengajuanData['id_kendaraan'] = $request->id_kendaraan;
            } else { // pengiriman_rutin
                $pengajuanData['id_perusahaan_ekspedisi'] = $request->id_perusahaan_ekspedisi;
            }

            $pengajuan = PengajuanSewa::create($pengajuanData);

            // Simpan biaya tambahan
            if ($request->biaya_tambahan) {
                foreach ($request->biaya_tambahan as $biaya) {
                    BiayaTambahan::create([
                        'id_pengajuan_sewa' => $pengajuan->id_pengajuan_sewa,
                        'id_jenis_biaya'    => $biaya['id_jenis_biaya'],
                        'jumlah'            => $biaya['nominal'],
                        'flag'              => true,
                    ]);
                }
            }

            // Branch: simpan detail_kiriman untuk pengiriman_rutin
            if ($request->jenis_pengajuan === 'pengiriman_rutin' && $request->detail_kiriman) {
                foreach ($request->detail_kiriman as $detail) {
                    $resolved = $this->resolveHargaKirimanRutin((int) $request->id_perusahaan_ekspedisi, $cabangId, $allSkills, $detail);
                    $subtotal = $resolved['biaya_per_unit'] * $detail['quantity'];

                    DetailKirimanRutin::create([
                        'id_pengajuan_sewa' => $pengajuan->id_pengajuan_sewa,
                        'id_jenis_barang' => $detail['id_jenis_barang'],
                        'id_tarif_kiriman_rutin' => $resolved['id_tarif'], // null kalau harga ad-hoc
                        'quantity' => $detail['quantity'],
                        'harga_satuan' => $resolved['biaya_per_unit'], // snapshot
                        'subtotal' => $subtotal, // snapshot
                        'flag' => true,
                        'created_at' => now(),
                    ]);
                }
            }

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success'      => true,
                'message'      => 'Pengajuan berhasil disubmit',
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
                'message' => 'Gagal submit pengajuan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resolve harga per unit utk 1 baris detail_kiriman: dari tarif resmi
     * kalau sudah terdaftar, atau dari input user kalau belum (harga ad-hoc,
     * HANYA tersimpan sbg snapshot di sesi_detail_kiriman_rutin — TIDAK
     * menulis apapun ke tabel master sesi_tarif_kiriman_rutin). Tarif resmi
     * cuma didaftarkan manual lewat halaman Kelola Tarif Kiriman Rutin.
     *
     * Tarif sekarang nempel ke sesi_perusahaan_skill (vendor+skill/area
     * +cabang — 9 Sept, keputusan Jo, direfactor 14 Sept ke tabel baru) bukan
     * vendor doang — krn vendor+barang yang sama bisa punya harga beda per
     * Area Kirim. Kalau pengajuan ini pilih >1 area/skill, dicoba satu-satu
     * SAMPAI ketemu vendor-skill yang punya tarif resmi — area2 yang dipilih
     * dianggap tarifnya sama (keputusan Jo), jadi yang PERTAMA ketemu
     * langsung dipakai.
     *
     * @param \Illuminate\Support\Collection<int> $skillIds id_skill (numeric) yang dipilih pengajuan ini
     * @return array{id_tarif: int|null, biaya_per_unit: float}
     * @throws \InvalidArgumentException kalau tarif belum ada & biaya_per_unit tidak dikirim
     */
    private function resolveHargaKirimanRutin(int $idPerusahaanEkspedisi, string $cabangId, \Illuminate\Support\Collection $skillIds, array $detail): array
    {
        foreach ($skillIds as $skillId) {
            $idVendorSkill = PerusahaanSkill::where('id_perusahaan', $idPerusahaanEkspedisi)
                ->where('id_skill', $skillId)
                ->where('cabang_code', $cabangId)
                ->where('flag', true)
                ->value('id_vendor_skill');

            if (!$idVendorSkill) {
                continue;
            }

            $tarif = TarifKirimanRutin::where('id_vendor_skill', $idVendorSkill)
                ->where('id_jenis_barang', $detail['id_jenis_barang'])
                ->where('flag', true)
                ->first();

            if ($tarif) {
                return ['id_tarif' => $tarif->id_tarif, 'biaya_per_unit' => (float) $tarif->biaya_per_unit];
            }
        }

        if (empty($detail['biaya_per_unit']) || $detail['biaya_per_unit'] <= 0) {
            throw new \InvalidArgumentException('Tarif belum terdaftar untuk salah satu jenis barang yang dipilih. Isi harga per unit dulu.');
        }

        // Harga ad-hoc — TIDAK didaftarkan ke sesi_tarif_kiriman_rutin, cuma
        // dipakai sbg snapshot pengajuan ini.
        return ['id_tarif' => null, 'biaya_per_unit' => (float) $detail['biaya_per_unit']];
    }

    // GET /api/pengajuan/skill-list
    public function getSkillList()
    {
        $result = DbHelper::safeQuery(function () {
            $user       = auth()->user();
            $cabangCode = $user->getCabangId();

            if (!$cabangCode) {
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
    // Return distinct kategoriToko values (untuk dropdown)
    public function getKategoriTokoList()
    {
        $result = DbHelper::safeQuery(function () {
            $user       = auth()->user();
            $cabangCode = $user->getCabangId();

            if (!$cabangCode) {
                return [];
            }

            return DB::connection('sqlsrv')
                ->table('sesi_cabang_skill as cs')
                ->join('sesi_master_skill as ms', 'ms.id_skill', '=', 'cs.id_skill')
                ->join('Q_CustomerLocusAtribute as q', function ($join) {
                    // id_skill sekarang numeric (bukan nama lagi) — join ke source
                    // IT (yang selamanya teks nama) harus lewat nama_skill.
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
                ->map(fn($k) => trim($k))
                ->filter(fn($k) => $k !== '')
                ->unique()
                ->values()
                ->toArray();
        });

        return response()->json($result);
    }

    // GET /api/pengajuan/jenis-biaya
    // Return daftar jenis biaya dari sesi_jenis_biaya
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

    // GET /api/dokumen/list?tujuan_penyewaan=Toko&cabang=01A
    // Fetch dokumen real (Surat Jalan + Transfer Antar Cabang) dari tabel IT
    public function getDokumenList(Request $request)
    {
        $tujuanPenyewaan = $request->get('tujuan_penyewaan'); // 'PAC', 'Toko', 'Umum'
        $cabang = $request->get('cabang');

        $dokumen = [];

        try {
            // 1. Fetch Surat Jalan Belum Kirim (jika tujuan = Toko atau Umum)
            // Nama view & kolom asli pakai spasi/strip (bukan underscore) — lihat
            // INFORMATION_SCHEMA, view ini sendiri sudah scoped ke "belum kirim".
            if (in_array($tujuanPenyewaan, ['Toko', 'Umum'])) {
                $suratJalans = DB::connection('sqlsrv')
                    ->table('Surat Jalan Belum Kirim')
                    ->where('Flag_Correction', '')  // bukan baris koreksi
                    ->where('Sell-to County', 'LIKE', $cabang . '%')  // Filter by cabang
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

                // Skill yang DIPILIH utk pengajuan ini (dikirim frontend dari
                // pengajuan.id_skill + skillBaru, sudah di-resolve jadi nama_skill di
                // client via getter skillGabunganLabel) — dipakai nentuin dokumen SJ
                // mana yang "prioritas". SENGAJA bukan "semua skill yang dilayani
                // cabang" (itu bikin hampir semua dokumen ke-flag cocok krn 1 cabang
                // biasa melayani banyak skill sekaligus - lihat Batch Fix 16).
                $targetSkills = collect(explode(',', (string) $request->get('skill', '')))
                    ->map(fn($s) => strtoupper(trim($s)))
                    ->filter()
                    ->unique()
                    ->values();

                // Cocokkan Sell-to Customer No_ (SJ) ke No_ (Q_CustomerLocusAtribute)
                // buat ambil skill customer itu — satu query buat semua customer_no
                // sekaligus, biar gak N+1.
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
                        'berat' => (float)$sj->berat,
                        'value' => (float)$sj->value,
                        'skill_priority' => $skillPriority,
                    ];
                }

                // Dokumen yang skill-nya cocok ditaruh di atas, urutan lain tetap
                // dipertahankan (stable sort).
                usort($sjItems, fn($a, $b) => ($b['skill_priority'] <=> $a['skill_priority']));

                array_push($dokumen, ...$sjItems);
            }

            // 2. Fetch Transfer Antar Cabang (jika tujuan = PAC atau Umum)
            // View ini sendiri sudah scoped ke "belum dikirim" (dikonfirmasi Jo) —
            // gak perlu filter Last Shipment No_, cukup filter cabang.
            if (in_array($tujuanPenyewaan, ['PAC', 'Umum'])) {
                $toAcbs = DB::connection('sqlsrv')
                    ->table('Transfer Antar Cabang')
                    ->where('Code', 'LIKE', $cabang . '%')  // Filter by cabang
                    ->select(
                        DB::raw('No_ as nomor_dokumen'),
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
                        'last_shipment_no' => $to->last_shipment_no,
                        'berat' => (float)$to->berat,
                        'berat_bersih' => (float)$to->berat_bersih,
                        'value' => (float)$to->value,
                    ];
                }
            }

            return response()->json(['dokumen' => $dokumen]);
        } catch (\Exception $e) {
            \Log::error('Error fetching dokumen list: ' . $e->getMessage());
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

        // Load relasi tambahan berdasarkan jenis_pengajuan
        // Untuk sewa_truk: eager load kendaraan
        // Untuk pengiriman_rutin: eager load perusahaanEkspedisi + detailKirimanRutin
        $pengajuan = PengajuanSewa::with(array_merge($relations, [
            'kendaraan.perusahaan',
            'perusahaanEkspedisi',
            'detailKirimanRutin.tarif',
            'detailKirimanRutin.jenisBarang',
        ]))->findOrFail($id);

        // Authorization: hanya KG pemilik pengajuan, dalam cabang yang sama
        if (!auth()->user()->canAccessCabang($pengajuan->id_cabang)) {
            abort(403);
        }

        if ($pengajuan->submitted_by != auth()->id()) {
            abort(403);
        }

        // Hanya Pending atau Rejected bisa diedit
        if (!in_array($pengajuan->status_pengajuan, ['pending', 'rejected'])) {
            abort(403);
        }

        // Fetch linked dokumen dari junction table
        $linkedSuratJalans = $pengajuan->suratJalans()->where('flag', true)->pluck('id_surat_jalan')->toArray();
        $linkedToAcbs = $pengajuan->transferAntarCabang()->where('flag', true)->pluck('id_to_acb')->toArray();
        $linkedDokumen = array_merge($linkedSuratJalans, $linkedToAcbs);

        $editData = [
            'id_pengajuan_sewa'  => $pengajuan->id_pengajuan_sewa,
            'jenis_pengajuan'    => $pengajuan->jenis_pengajuan,
            'id_cabang'          => $pengajuan->id_cabang, // Needed for fetchDokumenList
            'tanggal_pengiriman' => $pengajuan->tanggal_pengiriman->format('Y-m-d'),
            'harga_sewa'         => (float)$pengajuan->harga_sewa,
            'value_muatan'       => (float)$pengajuan->value_muatan,
            'tujuan_penyewaan'   => $pengajuan->tujuan_penyewaan,
            'kategori_toko'      => $pengajuan->kategori_toko,
            // Kirim token apa adanya (numeric utk skill yg udah match master,
            // atau nama mentah utk leftover lama yg belum ke-convert, misal
            // "DKAH" pre-9-Sept) — logic pemisahan existing-vs-baru di app.js
            // (bandingin ke skillList by id_skill) sudah otomatis nempatin token
            // yg ga match numeric manapun sbg "skill baru", ga perlu split di sini.
            'id_skill'           => array_map('trim', explode(',', $pengajuan->id_skill)),
            'catatan_pengajuan'  => $pengajuan->catatan_pengajuan,
            'biaya_tambahan'     => $pengajuan->biayaTambahan->map(function ($biaya) {
                return [
                    'id_jenis_biaya' => (int)$biaya->id_jenis_biaya,
                    'nominal'        => (float)$biaya->jumlah,
                ];
            })->toArray(),
            'dokumen_dipilih'    => $linkedDokumen, // Prefill linked dokumen dari junction table
        ];

        // Branch: add sewa_truk or pengiriman_rutin specific data
        if ($pengajuan->jenis_pengajuan === 'sewa_truk') {
            $editData['id_kendaraan'] = $pengajuan->id_kendaraan;
            $editData['kendaraan'] = [
                'id'        => $pengajuan->kendaraan->id_kendaraan,
                'nama'      => $pengajuan->kendaraan->perusahaan->nama_perusahaan,
                'kendaraan' => $pengajuan->kendaraan->jenis_kendaraan,
                'muatan'    => (int)$pengajuan->kendaraan->muatan_maksimal . ' Ton',
                'muatan_raw' => (float)$pengajuan->kendaraan->muatan_maksimal,
                'skill'     => $this->resolveSkillNames($pengajuan->kendaraan->id_skill),
                'updated_at' => $pengajuan->kendaraan->updated_at->format('d M Y'),
            ];
        } else { // pengiriman_rutin
            $editData['id_perusahaan_ekspedisi'] = $pengajuan->id_perusahaan_ekspedisi;
            $editData['perusahaan'] = [
                'id' => $pengajuan->perusahaanEkspedisi->id_perusahaan,
                'nama_perusahaan' => $pengajuan->perusahaanEkspedisi->nama_perusahaan,
            ];
            // Vendor-skill (sesi_perusahaan_skill) buat fetch tarif di step 2 —
            // read-only, jangan create baris baru pas edit; array kosong =>
            // frontend pakai input ad-hoc. Array krn 1 cabang bisa punya
            // beberapa skill sekaligus (masing2 1 baris sesi_perusahaan_skill).
            $editData['id_vendor_skill_list'] = PerusahaanSkill::where('id_perusahaan', $pengajuan->id_perusahaan_ekspedisi)
                ->where('cabang_code', $pengajuan->id_cabang)
                ->where('flag', true)
                ->pluck('id_vendor_skill')
                ->values()
                ->toArray();
            // Prefill detail_kiriman dengan snapshot values (NOT live tarif)
            $editData['detail_kiriman_dipilih'] = $pengajuan->detailKirimanRutin()
                ->where('flag', true)
                ->get()
                ->map(function ($detail) {
                    return [
                        'id_jenis_barang' => $detail->id_jenis_barang,
                        'id_tarif_kiriman_rutin' => $detail->id_tarif_kiriman_rutin ? (int) $detail->id_tarif_kiriman_rutin : null,
                        'jenis_barang' => $detail->jenisBarang?->nama_barang ?? $detail->tarif?->jenisBarang?->nama_barang ?? 'N/A',
                        'quantity' => (float)$detail->quantity,
                        'harga_satuan' => (float)$detail->harga_satuan,
                        'subtotal' => (float)$detail->subtotal,
                        // Ad-hoc (belum ada tarif resmi) kalau id_tarif_kiriman_rutin null —
                        // tetap ditampilkan sbg input manual saat diedit.
                        'tarif_baru' => $detail->id_tarif_kiriman_rutin === null,
                    ];
                })->toArray();
        }

        return view('pages.pengajuan.index', ['editPengajuan' => $editData]);
    }

    public function update(Request $request, $id)
    {
        $pengajuan = PengajuanSewa::findOrFail($id);

        // Authorization checks
        if (!auth()->user()->canAccessCabang($pengajuan->id_cabang)) {
            abort(403);
        }

        if ($pengajuan->submitted_by != auth()->id()) {
            abort(403);
        }

        if (!in_array($pengajuan->status_pengajuan, ['pending', 'rejected'])) {
            abort(403);
        }

        // Base validation common to both jenis_pengajuan
        $baseRules = [
            'jenis_pengajuan'    => 'required|in:sewa_truk,pengiriman_rutin',
            'tanggal_pengiriman' => 'required|date',
            'value_muatan'       => 'required|numeric|min:1',
            'tujuan_penyewaan'   => 'required|in:Toko,PAC',
            'id_skill'           => 'required|array|min:1',
            'id_skill.*'         => 'required|integer|exists:sqlsrv.dbo.sesi_master_skill,id_skill',
            'skill_baru'         => 'nullable|array',
            'skill_baru.*'       => 'nullable|string|max:50',
            'kategori_toko'      => 'required|string',
            'catatan'            => 'nullable|string',
            'biaya_tambahan'     => 'nullable|array',
            'biaya_tambahan.*.id_jenis_biaya' => 'required|integer|exists:sqlsrv.dbo.sesi_jenis_biaya,id_jenis_biaya',
            'biaya_tambahan.*.nominal'        => 'required|numeric|min:0',
        ];

        // Branch-specific validation
        if ($request->jenis_pengajuan === 'sewa_truk') {
            $baseRules['id_kendaraan'] = 'required|integer|exists:sqlsrv.dbo.sesi_unit_kendaraan,id_kendaraan';
            $baseRules['harga_sewa'] = 'required|numeric|min:0';
        } else { // pengiriman_rutin
            $baseRules['id_perusahaan_ekspedisi'] = 'required|integer|exists:sqlsrv.dbo.sesi_perusahaan_ekspedisi,id_perusahaan';
            $baseRules['detail_kiriman'] = 'required|array|min:1';
            $baseRules['detail_kiriman.*.id_jenis_barang'] = 'required|integer|exists:sqlsrv.dbo.sesi_jenis_barang_kiriman,id_jenis_barang';
            $baseRules['detail_kiriman.*.quantity'] = 'required|numeric|min:0.01';
            // biaya_per_unit cuma wajib kalau vendor belum punya tarif utk jenis barang ini —
            // dicek manual di resolveHargaKirimanRutin(), krn tergantung data existing di DB.
            $baseRules['detail_kiriman.*.biaya_per_unit'] = 'nullable|numeric|min:0.01';
        }

        $request->validate($baseRules);

        try {
            DB::connection('sqlsrv')->beginTransaction();

            $user = auth()->user();
            $cabangId = $user->getCabangId();

            // Gabung skill existing (udah numeric id) + skill baru (nama, di-resolve)
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
                // Resolve harga per baris — dari tarif resmi kalau ada, atau harga
                // ad-hoc dari input user (TIDAK menulis ke tabel master tarif)
                foreach ($request->detail_kiriman as $detail) {
                    $resolved = $this->resolveHargaKirimanRutin((int) $request->id_perusahaan_ekspedisi, $cabangId, $allSkills, $detail);
                    $hargaSewa += $resolved['biaya_per_unit'] * $detail['quantity'];
                }
            }

            // Hitung rasio sewa termasuk biaya tambahan
            $totalBiayaTambahan = collect($request->biaya_tambahan ?? [])->sum('nominal');
            $rasioSewa = (($hargaSewa + $totalBiayaTambahan) / $request->value_muatan) * 100;
            $isAreaBaru = $skillBaru->isNotEmpty();

            $kategoriApproval = 'normal';
            $rasioMaks = \App\Models\RasioSewa::aktif()?->persentase_maksimal ?? 2.5;
            if ($rasioSewa > $rasioMaks || $request->tujuan_penyewaan === 'PAC' || $isAreaBaru) {
                $kategoriApproval = 'over_threshold';
            }

            // Update pengajuan, set status_pengajuan ke Pending (baik dari Pending tetap Pending, atau dari Rejected jadi Pending)
            $updateData = [
                'tanggal_pengiriman'     => $request->tanggal_pengiriman,
                'value_muatan'           => $request->value_muatan,
                'harga_sewa'             => $hargaSewa,
                'rasio_sewa'             => round($rasioSewa, 2),
                'kategori_approval'      => $kategoriApproval,
                'tujuan_penyewaan'       => $request->tujuan_penyewaan,
                'id_skill'               => $idSkillStr,
                'kategori_toko'          => $request->kategori_toko,
                'status_pengajuan'       => 'Pending',
                'catatan_pengajuan'      => $request->catatan,
                'jenis_pengajuan'        => $request->jenis_pengajuan,
            ];

            // Branch: update kendaraan or perusahaan based on jenis_pengajuan
            if ($request->jenis_pengajuan === 'sewa_truk') {
                $updateData['id_kendaraan'] = $request->id_kendaraan;
                $updateData['id_perusahaan_ekspedisi'] = null;
            } else { // pengiriman_rutin
                $updateData['id_kendaraan'] = null;
                $updateData['id_perusahaan_ekspedisi'] = $request->id_perusahaan_ekspedisi;
            }

            $pengajuan->update($updateData);

            // Hapus biaya tambahan lama dan insert ulang
            BiayaTambahan::where('id_pengajuan_sewa', $pengajuan->id_pengajuan_sewa)->delete();

            if ($request->biaya_tambahan) {
                foreach ($request->biaya_tambahan as $biaya) {
                    BiayaTambahan::create([
                        'id_pengajuan_sewa' => $pengajuan->id_pengajuan_sewa,
                        'id_jenis_biaya'    => $biaya['id_jenis_biaya'],
                        'jumlah'            => $biaya['nominal'],
                        'flag'              => true,
                    ]);
                }
            }

            // Branch: handle detail_kiriman untuk pengiriman_rutin (soft delete old + insert new)
            if ($request->jenis_pengajuan === 'pengiriman_rutin' && $request->detail_kiriman) {
                // Flag lama menjadi false (soft delete)
                DetailKirimanRutin::where('id_pengajuan_sewa', $pengajuan->id_pengajuan_sewa)
                    ->where('flag', true)
                    ->update(['flag' => false]);

                // Insert baru
                foreach ($request->detail_kiriman as $detail) {
                    $resolved = $this->resolveHargaKirimanRutin((int) $request->id_perusahaan_ekspedisi, $cabangId, $allSkills, $detail);
                    $subtotal = $resolved['biaya_per_unit'] * $detail['quantity'];

                    DetailKirimanRutin::create([
                        'id_pengajuan_sewa' => $pengajuan->id_pengajuan_sewa,
                        'id_jenis_barang' => $detail['id_jenis_barang'],
                        'id_tarif_kiriman_rutin' => $resolved['id_tarif'], // null kalau harga ad-hoc
                        'quantity' => $detail['quantity'],
                        'harga_satuan' => $resolved['biaya_per_unit'], // snapshot
                        'subtotal' => $subtotal, // snapshot
                        'flag' => true,
                        'created_at' => now(),
                    ]);
                }
            }

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success'      => true,
                'message'      => 'Pengajuan berhasil diperbarui',
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
                'message' => 'Gagal update pengajuan: ' . $e->getMessage(),
            ], 500);
        }
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
            'suratJalans',
            'transferAntarCabang',
        ])->findOrFail($id);

        if (!auth()->user()->canAccessCabang($pengajuan->id_cabang)) {
            abort(403);
        }

        // Build timeline: merge approval logs + synthetic entry kalau ada resubmit
        $timeline = collect($pengajuan->approvalLogs)->map(function ($log) {
            return [
                'type'       => 'approval',
                'status'     => $log->status,
                'approver'   => $log->approver,
                'decided_at' => $log->decided_at,
                'reason'     => $log->alasan_penolakan,
            ];
        });

        // Cek apakah ada resubmit: status Pending sekarang + ada log Rejected terbaru, dan updated_at lebih baru dari decided_at log terakhir
        if ($pengajuan->status_pengajuan === 'Pending' && $timeline->isNotEmpty()) {
            $lastRejection = $timeline
                ->where('status', 'Rejected')
                ->sortBy('decided_at')
                ->last();

            if ($lastRejection && $pengajuan->updated_at > $lastRejection['decided_at']) {
                // Insert synthetic resubmit entry di posisi yang tepat (berdasarkan waktu)
                $timeline->push([
                    'type'       => 'resubmit',
                    'aktor'      => $pengajuan->submittedBy,
                    'decided_at' => $pengajuan->updated_at,
                ]);
            }
        }

        // Sort timeline berdasarkan timestamp (ascending)
        $timeline = $timeline->sortBy('decided_at')->values();

        // Get ambang rasio sewa dari database
        $rasioSewaSetting = RasioSewa::aktif();
        $ambangRasio = $rasioSewaSetting ? $rasioSewaSetting->persentase_maksimal : 2.5;

        return view('pages.pengajuan.detailPengajuan', [
            'pengajuan' => $pengajuan,
            'timeline' => $timeline,
            'ambangRasio' => $ambangRasio,
            'breadcrumb' => [
                'back_url' => route('dashboard'),
                'back_label' => 'Dashboard',
                'title' => 'Detail Pengajuan',
                'status' => strtolower($pengajuan->status_pengajuan),
            ],
        ]);
    }

    /**
     * Fetch list perusahaan existing (untuk dropdown di step1)
     */
    public function getPerusahaanList(Request $request)
    {
        $user  = auth()->user();
        $jenis = $request->get('jenis', 'sewa_truk');
        $tbl   = (new PerusahaanEkspedisi)->getTable();

        $query = PerusahaanEkspedisi::where('flag', true)->orderBy('nama_perusahaan');

        // Scope per cabang user. WH/DCI (global access) lihat semua.
        // sesi_perusahaan_ekspedisi tidak punya kolom cabang — kaitan vendor↔cabang
        // lewat baris di sesi_unit_kendaraan (id_perusahaan + id_cabang).
        if (!$user->isGlobalAccess()) {
            $cabangIds = $user->getCabangIds() ?: array_values(array_filter([$user->getCabangId()]));

            if (empty($cabangIds)) {
                $query->whereRaw('1 = 0');
            } elseif ($jenis === 'pengiriman_rutin') {
                // Kiriman Rutin: hanya vendor yang punya tarif barang terdaftar
                // di sesi_perusahaan_skill cabang user.
                $query->whereExists(function ($q) use ($cabangIds, $tbl) {
                    $q->select(DB::raw(1))
                      ->from('sesi_perusahaan_skill as ps')
                      ->join('sesi_tarif_kiriman_rutin as t', 't.id_vendor_skill', '=', 'ps.id_vendor_skill')
                      ->whereColumn('ps.id_perusahaan', "$tbl.id_perusahaan")
                      ->where('ps.flag', true)
                      ->where('t.flag', true)
                      ->whereIn('ps.cabang_code', $cabangIds);
                });
            } else {
                // Sewa Truk: vendor yang punya minimal 1 baris sesi_unit_kendaraan di cabang user.
                $query->whereExists(function ($q) use ($cabangIds, $tbl) {
                    $q->select(DB::raw(1))
                      ->from('sesi_unit_kendaraan as uk')
                      ->whereColumn('uk.id_perusahaan', "$tbl.id_perusahaan")
                      ->where('uk.flag', true)
                      ->whereIn('uk.id_cabang', $cabangIds);
                });
            }
        }

        $perusahaan = $query->get(['id_perusahaan', 'nama_perusahaan', 'badan_usaha', 'no_telepon', 'alamat_kantor']);

        // Kiriman Rutin: attach breakdown tarif per perusahaan (dari sesi_perusahaan_skill
        // di cabang user) buat panel hover di list perusahaan - kosong kalau vendor gak
        // punya tarif sama sekali.
        if ($jenis === 'pengiriman_rutin' && $perusahaan->isNotEmpty()) {
            $idPerusahaanList = $perusahaan->pluck('id_perusahaan')->all();
            $vendorSkillQuery = PerusahaanSkill::whereIn('id_perusahaan', $idPerusahaanList)
                ->where('flag', true);
            if (!$user->isGlobalAccess()) {
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

        return response()->json(['data' => $perusahaan]);
    }

    /**
     * Create perusahaan baru + upload identitas owner
     */
    public function storePerusahaan(Request $request)
    {
        $request->validate([
            'nama_perusahaan'    => 'required|string|max:255|unique:sqlsrv.dbo.sesi_perusahaan_ekspedisi,nama_perusahaan',
            'badan_usaha'        => 'required|in:PT,CV,UD,Perseorangan',
            'no_telepon'         => 'required|string|max:20',
            'alamat_kantor'      => 'required|string',
            'identitas_owner'    => 'required|array|min:1|max:3',
            'identitas_owner.*'  => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        try {
            DB::connection('sqlsrv')->beginTransaction();

            // Create perusahaan dulu (tanpa identitas_owner) - butuh id_perusahaan buat nama file.
            $perusahaan = PerusahaanEkspedisi::create([
                'nama_perusahaan' => $request->nama_perusahaan,
                'badan_usaha'     => $request->badan_usaha,
                'no_telepon'      => $request->no_telepon,
                'alamat_kantor'   => $request->alamat_kantor,
                'flag'            => true,
            ]);

            // Simpan identitas owner sbg file di public/images/ (bukan base64 di DB lagi) -
            // assign array path MENTAH, biarkan cast 'array' di model yang encode sekali
            // (jangan json_encode manual di sini, itu bikin double-encode - lihat Batch Fix 14).
            if ($request->hasFile('identitas_owner')) {
                $perusahaan->identitas_owner = $this->storeIdentitasOwnerFiles(
                    $request->file('identitas_owner'),
                    (string) $perusahaan->id_perusahaan
                );
                $perusahaan->save();
            }

            // Buat baris sesi_perusahaan_skill placeholder per skill cabang user,
            // biar vendor baru langsung ke-link ke cabang ini (dipakai target
            // tarif ad-hoc di step 2 Kiriman Rutin). Resolve-or-create biar idempotent.
            $idVendorSkillList = $this->resolveVendorSkillIds($perusahaan->id_perusahaan, auth()->user()->getCabangId());

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
                'message' => 'Gagal menyimpan perusahaan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update data vendor existing (badan_usaha / no_telepon / alamat_kantor / identitas_owner).
     * Dipakai kartu "Lengkapi Data Vendor" di step 2 Kiriman Rutin. nama_perusahaan
     * SENGAJA tidak bisa diubah (unique identity key).
     * POST /api/pengajuan/perusahaan/{id}  (spoof PATCH via _method)
     */
    public function updateVendor(Request $request, int $id)
    {
        $vendor = PerusahaanEkspedisi::where('flag', true)->findOrFail($id);

        $data = $request->validate([
            // nama_perusahaan: nullable biar caller lama (wizard Pengajuan Sewa)
            // yang nggak pernah kirim field ini tetap jalan tanpa error — cuma
            // divalidasi/di-update kalau memang dikirim (halaman Kelola
            // Perusahaan di Master Data, 16 Sept, field ini sekarang editable).
            'nama_perusahaan'        => 'nullable|string|max:255|unique:sqlsrv.dbo.sesi_perusahaan_ekspedisi,nama_perusahaan,' . $id . ',id_perusahaan',
            'badan_usaha'            => 'required|in:PT,CV,UD,Perseorangan',
            'no_telepon'             => 'required|string|max:20',
            'alamat_kantor'          => 'required|string',
            // identitas_owner_keep: path foto LAMA yang mau dipertahankan (baru dipakai
            // halaman Kelola Perusahaan di Master Data — hapus satuan pakai tombol "×").
            // Kalau field ini SAMA SEKALI nggak dikirim (caller lama, mis. wizard
            // Pengajuan Sewa), fallback ke behavior lama: upload baru = ganti semua.
            'identitas_owner_keep'   => 'nullable|array',
            'identitas_owner_keep.*' => 'string',
            'identitas_owner'        => 'nullable|array',
            'identitas_owner.*'      => 'file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        try {
            DB::connection('sqlsrv')->beginTransaction();

            if (!empty($data['nama_perusahaan'])) {
                $vendor->nama_perusahaan = $data['nama_perusahaan'];
            }
            $vendor->badan_usaha   = $data['badan_usaha'];
            $vendor->no_telepon    = $data['no_telepon'];
            $vendor->alamat_kantor = $data['alamat_kantor'];

            $existing = $vendor->identitas_owner ?? [];
            $newFiles = $request->hasFile('identitas_owner') ? $request->file('identitas_owner') : [];

            if ($request->has('identitas_owner_keep')) {
                // Mode granular: keep cuma path yang eksplisit diminta dipertahankan,
                // sisanya dihapus filenya. Total (keep + baru) maks 3.
                $keep = array_values(array_intersect($existing, $request->input('identitas_owner_keep', [])));
                $toDelete = array_values(array_diff($existing, $keep));

                if (count($keep) + count($newFiles) > 3) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Maksimal 3 foto identitas owner.',
                    ], 422);
                }

                if (!empty($toDelete)) {
                    $this->deleteIdentitasOwnerFiles($toDelete);
                }

                $newPaths = !empty($newFiles)
                    ? $this->storeIdentitasOwnerFiles($newFiles, (string) $vendor->id_perusahaan, count($keep) + 1)
                    : [];

                // Cast 'array' di model — assign array path mentah, biar di-encode sekali.
                $vendor->identitas_owner = array_values(array_merge($keep, $newPaths));
            } elseif (!empty($newFiles)) {
                // Behavior lama (wizard Pengajuan Sewa): upload baru = ganti semua foto lama.
                $this->deleteIdentitasOwnerFiles($existing);
                $vendor->identitas_owner = $this->storeIdentitasOwnerFiles(
                    $newFiles,
                    (string) $vendor->id_perusahaan
                );
            }

            $vendor->save();
            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success'    => true,
                'message'    => 'Data vendor berhasil diperbarui',
                'perusahaan' => $vendor->fresh(),
            ]);
        } catch (\Throwable $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui vendor: ' . $e->getMessage(),
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
                ->where('sesi_unit_kendaraan.flag', true)
                // Picker kendaraan fisik Sewa Truk: buang baris rate-card (tanpa plat)
                ->whereNotNull('sesi_unit_kendaraan.plat_nomor_truk');

            // Scope ke cabang user (WH/DCI global access lihat semua)
            if (!$user->isGlobalAccess()) {
                $cabangIds = $user->getCabangIds() ?: array_values(array_filter([$user->getCabangId()]));
                $query->whereIn('sesi_unit_kendaraan.id_cabang', $cabangIds ?: ['__none__']);
            }

            $kendaraan = $query
                ->orderByDesc('sesi_unit_kendaraan.updated_at')
                ->get()
                ->map(function($a) {
                    return [
                        ...(array) $a,
                        'skill'  => $this->resolveSkillNames($a->skill),
                        'muatan' => \App\Helpers\FormatHelper::ton($a->muatan_raw),
                    ];
                });

            return response()->json(['data' => $kendaraan]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal fetch kendaraan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/pengajuan/rate-card?id_perusahaan_ekspedisi={id}
     * Resolve (buat kalau belum ada) baris sesi_perusahaan_skill untuk vendor
     * + tiap skill di cabang user. Dipakai step 1 Kiriman Rutin buat nge-fetch
     * tarif terdaftar (by id_vendor_skill) & jadi target tarif ad-hoc di step 2.
     */
    public function resolveRateCard(Request $request)
    {
        $request->validate([
            'id_perusahaan_ekspedisi' => 'required|integer|exists:sqlsrv.dbo.sesi_perusahaan_ekspedisi,id_perusahaan',
        ]);

        $cabangId = auth()->user()->getCabangId();
        if (!$cabangId) {
            return response()->json(['id_vendor_skill' => []]);
        }

        return response()->json([
            'id_vendor_skill' => $this->resolveVendorSkillIds((int) $request->id_perusahaan_ekspedisi, $cabangId),
        ]);
    }

    /**
     * Resolve-or-create baris sesi_perusahaan_skill untuk vendor + tiap skill
     * yang terdaftar di cabang tsb (sesi_cabang_skill). Return array id_vendor_skill,
     * atau [] kalau cabang tidak diketahui / belum punya skill terdaftar.
     * firstOrCreate per skill menjaga idempotency (unique id_perusahaan+id_skill+cabang_code).
     */
    private function resolveVendorSkillIds(int $idPerusahaan, ?string $cabangId): array
    {
        if (!$cabangId) {
            return [];
        }

        $skillIds = DB::connection('sqlsrv')->table('sesi_cabang_skill')
            ->where('cabang_code', $cabangId)
            ->where('flag', true)
            ->orderBy('id_skill')
            ->pluck('id_skill');

        $ids = [];
        foreach ($skillIds as $skillId) {
            $ids[] = PerusahaanSkill::firstOrCreate(
                ['id_perusahaan' => $idPerusahaan, 'id_skill' => $skillId, 'cabang_code' => $cabangId],
                ['flag' => true]
            )->id_vendor_skill;
        }

        return $ids;
    }
}