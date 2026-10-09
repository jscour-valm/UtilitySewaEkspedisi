<?php

namespace App\Http\Controllers\Concerns;

use App\Helpers\FormatHelper;
use App\Models\PerusahaanEkspedisi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait BuildsPerusahaanSummary
{
    protected function ownCabang(): array
    {
        $user = auth()->user();
        if ($user->isGlobalAccess()) {
            return [];
        }

        return array_values(array_unique($user->getCabangIds() ?: array_filter([$user->getCabangId()])));
    }

    /**
     * SQL "vendor milik cabang user": punya tarif/unit kendaraan di cabang user, atau
     * diajukan sebagai vendor baru oleh cabang user (walau belum punya tarif/unit).
     */
    protected function mineCompanySql(array $own): array
    {
        if (! $own) {
            return ['0', []];
        }
        $in = implode(',', array_fill(0, count($own), '?'));

        return [
            "CASE WHEN EXISTS (SELECT 1 FROM sesi_perusahaan_skill mv WHERE mv.id_perusahaan = pe.id_perusahaan AND mv.flag = 1 AND mv.cabang_code IN ({$in}))"
            ." OR EXISTS (SELECT 1 FROM sesi_unit_kendaraan mu WHERE mu.id_perusahaan = pe.id_perusahaan AND mu.flag = 1 AND mu.id_cabang IN ({$in}))"
            ." OR pe.id_cabang_pengaju IN ({$in})"
            .' THEN 1 ELSE 0 END',
            array_merge($own, $own, $own),
        ];
    }

    /**
     * Vendor yang boleh dilihat user non-global: milik cabangnya, atau belum terhubung ke
     * cabang mana pun. Vendor baru yang belum disetujui hanya terlihat oleh cabang pengajunya.
     */
    protected function ownOrUnclaimedScope(array $own): ?\Closure
    {
        $user = auth()->user();
        if ($user->isGlobalAccess()) {
            return null;
        }

        if (empty($own)) {
            return fn ($q) => $q->whereRaw('1 = 0');
        }

        [$mineSql, $mineBindings] = $this->mineCompanySql($own);

        return function ($q) use ($mineSql, $mineBindings) {
            $q->whereRaw(
                "({$mineSql} = 1) OR (
                    pe.status_approval = 'approved'
                    AND NOT EXISTS (SELECT 1 FROM sesi_perusahaan_skill vs WHERE vs.id_perusahaan = pe.id_perusahaan AND vs.flag = 1)
                    AND NOT EXISTS (SELECT 1 FROM sesi_unit_kendaraan uk WHERE uk.id_perusahaan = pe.id_perusahaan AND uk.flag = 1)
                )",
                $mineBindings
            );
        };
    }

    /**
     * Vendor baru yang belum disetujui (menunggu / ditolak) cuma boleh dilihat cabang pengajunya
     * dan user global (WH, WC, DCI). $query memakai alias `pe` untuk sesi_perusahaan_ekspedisi.
     */
    protected function hanyaVendorTerlihat($query, array $own): void
    {
        if (auth()->user()->isGlobalAccess()) {
            return;
        }
        if (! $own) {
            $query->where('pe.status_approval', 'approved');

            return;
        }
        $in = implode(',', array_fill(0, count($own), '?'));
        $query->whereRaw("(pe.status_approval = 'approved' OR pe.id_cabang_pengaju IN ({$in}))", $own);
    }

    protected function bolehLihatVendor(PerusahaanEkspedisi $vendor): bool
    {
        return $vendor->sudahDisetujui()
            || auth()->user()->isGlobalAccess()
            || in_array($vendor->id_cabang_pengaju, $this->ownCabang(), true);
    }

    /** Urutan default query vendor_skill (`ps`): baris di cabang user dulu. No-op kalau user global. */
    protected function orderMineFirst($query, array $own): void
    {
        if (! $own) {
            return;
        }
        $in = implode(',', array_fill(0, count($own), '?'));
        $query->orderByRaw("CASE WHEN ps.cabang_code IN ({$in}) THEN 0 ELSE 1 END", $own);
    }

    /** Ambil vendor_skill, unit kendaraan, dan penanda tarif kiriman utk sekumpulan perusahaan sekaligus (hindari N+1). */
    protected function loadRelations(array $ids, array $own): array
    {
        $db = DB::connection('sqlsrv');

        if (empty($ids)) {
            return ['vs' => collect(), 'units' => collect(), 'tarifVsIds' => [], 'skillNames' => collect()];
        }

        $vs = $db->table('sesi_perusahaan_skill')
            ->whereIn('id_perusahaan', $ids)->where('flag', true)
            ->get(['id_vendor_skill', 'id_perusahaan', 'id_skill', 'cabang_code', 'harga_sewa']);

        $units = $db->table('sesi_unit_kendaraan')
            ->whereIn('id_perusahaan', $ids)->where('flag', true)
            ->orderBy('id_cabang')->orderBy('jenis_kendaraan')->orderBy('id_kendaraan')
            ->get(['id_kendaraan', 'id_perusahaan', 'id_cabang', 'id_skill', 'jenis_kendaraan', 'plat_nomor_truk', 'muatan_maksimal']);
        // Unit di cabang user naik ke atas (sortBy stabil, urutan lainnya tetap).
        if ($own) {
            $units = $units->sortBy(fn ($u) => in_array($u->id_cabang, $own, true) ? 0 : 1)->values();
        }

        $tarifVsIds = $vs->isEmpty() ? [] : $db->table('sesi_tarif_kiriman_rutin as t')
            ->join('sesi_perusahaan_skill as p', 'p.id_vendor_skill', '=', 't.id_vendor_skill')
            ->whereIn('p.id_perusahaan', $ids)
            ->where('t.flag', true)->where('p.flag', true)
            ->distinct()->pluck('t.id_vendor_skill')->flip()->all();

        $skillNames = $db->table('sesi_master_skill')->pluck('nama_skill', 'id_skill');

        return compact('vs', 'units', 'tarifVsIds', 'skillNames');
    }

    protected function kendaraanItems($units, $vs, $skillNames, string $namaPerusahaan): array
    {
        $isKg = auth()->user()->userUtility?->role === 'KG';
        $own = $this->ownCabang();

        $patokan = [];
        foreach ($vs as $v) {
            if ($v->harga_sewa !== null) {
                $patokan[$v->id_perusahaan.'|'.$v->cabang_code.'|'.(int) $v->id_skill] = (float) $v->harga_sewa;
            }
        }

        return $units->map(function ($u) use ($isKg, $own, $patokan, $skillNames, $namaPerusahaan) {
            $skillIds = array_values(array_filter(
                array_map('trim', explode(',', (string) $u->id_skill)),
                fn ($t) => ctype_digit($t)
            ));
            $skills = collect($skillIds)->map(fn ($sid) => $skillNames->get((int) $sid))->filter()->values()->all();

            $hargaRef = null;
            foreach ($skillIds as $sid) {
                $key = $u->id_perusahaan.'|'.$u->id_cabang.'|'.(int) $sid;
                if (isset($patokan[$key])) {
                    $hargaRef = $patokan[$key];
                    break;
                }
            }

            $muatan = $u->muatan_maksimal !== null ? FormatHelper::ton($u->muatan_maksimal) : '—';

            return [
                'id' => $u->id_kendaraan,
                'cabang' => $u->id_cabang,
                'jenis' => $u->jenis_kendaraan,
                'plat' => $u->plat_nomor_truk,
                'muatan' => $muatan,
                'skills' => $skills,
                'mine' => in_array($u->id_cabang, $own, true),
                // Shortcut pengajuan cuma buat unit di cabang KG sendiri.
                'pengajuan_url' => ($isKg && in_array($u->id_cabang, $own, true)) ? route('pengajuan.kg', [
                    'id' => $u->id_kendaraan,
                    'nama' => $namaPerusahaan,
                    'kendaraan' => $u->jenis_kendaraan,
                    'muatan' => $muatan,
                    'muatan_raw' => $u->muatan_maksimal,
                    'harga' => $hargaRef !== null ? FormatHelper::rupiah($hargaRef) : 'Rp 0',
                    'skill' => $u->id_skill,
                ]) : null,
            ];
        })->all();
    }

    /** Kondisi search dasar (dipakai indexSemua() DAN summaryRows() — sama persis). */
    protected function applySemuaSearch($query, string $search): void
    {
        if ($search === '') {
            return;
        }
        $like = "%{$search}%";
        $query->where(function ($w) use ($like) {
            $w->where('pe.nama_perusahaan', 'like', $like)
                ->orWhere('pe.badan_usaha', 'like', $like)
                ->orWhereExists(function ($s) use ($like) {
                    $s->select(DB::raw(1))->from('sesi_perusahaan_skill as ps')
                        ->join('sesi_master_skill as ms', 'ms.id_skill', '=', 'ps.id_skill')
                        ->whereColumn('ps.id_perusahaan', 'pe.id_perusahaan')
                        ->where('ps.flag', true)
                        ->where(function ($x) use ($like) {
                            $x->where('ms.nama_skill', 'like', $like)->orWhere('ps.cabang_code', 'like', $like);
                        });
                });
        });
    }

    /**
     * @param  \Closure|null  $extraScope  dipanggil dgn ($query) — tambahan whereExists dsb.
     */
    protected function summaryRows(array $own, ?string $search, int $limit, ?\Closure $extraScope = null): Collection
    {
        $db = DB::connection('sqlsrv');
        $search = trim((string) $search);

        $query = $db->table('sesi_perusahaan_ekspedisi as pe')->where('pe.flag', true);
        $this->applySemuaSearch($query, $search);
        $this->hanyaVendorTerlihat($query, $own);
        if ($extraScope) {
            $extraScope($query);
        }

        $lastUpdate = '(SELECT MAX(d) FROM (VALUES
            (pe.updated_at),
            ((SELECT MAX(x.updated_at) FROM sesi_perusahaan_skill x WHERE x.id_perusahaan = pe.id_perusahaan AND x.flag = 1)),
            ((SELECT MAX(k.updated_at) FROM sesi_unit_kendaraan k WHERE k.id_perusahaan = pe.id_perusahaan AND k.flag = 1))
        ) AS t(d))';
        [$mineSql, $mineBindings] = $this->mineCompanySql($own);

        $query->select('pe.id_perusahaan', 'pe.nama_perusahaan', 'pe.badan_usaha', 'pe.no_telepon', 'pe.alamat_kantor', 'pe.status_approval')
            ->selectRaw("{$lastUpdate} as last_update")
            ->selectRaw("{$mineSql} as is_mine", $mineBindings);

        if ($own) {
            $query->orderByDesc('is_mine');
        }
        $query->orderBy('pe.nama_perusahaan')->orderBy('pe.id_perusahaan')
            ->limit($limit);

        $rows = collect($query->get());

        $ids = $rows->pluck('id_perusahaan')->all();
        $rel = $this->loadRelations($ids, $own);
        if (auth()->user()?->userUtility?->role === 'KG') {
            $rel['vs'] = $rel['vs']->whereIn('cabang_code', $own)->values();
            $rel['units'] = $rel['units']->whereIn('id_cabang', $own)->values();
        }
        $vsBy = $rel['vs']->groupBy('id_perusahaan');
        $unitsBy = $rel['units']->groupBy('id_perusahaan');
        $cabangNames = $db->table('sesi_master_cabang')->pluck('Name', 'Code');

        return $rows->map(function ($row) use ($rel, $vsBy, $unitsBy, $cabangNames) {
            $vs = $vsBy->get($row->id_perusahaan, collect());
            $units = $unitsBy->get($row->id_perusahaan, collect());

            $areas = $vs->pluck('id_skill')->map(fn ($v) => (int) $v);
            foreach ($units as $u) {
                foreach (explode(',', (string) $u->id_skill) as $token) {
                    $token = trim($token);
                    if (ctype_digit($token)) {
                        $areas->push((int) $token);
                    }
                }
            }
            $areas = $areas->unique();
            $cabangCodes = $vs->pluck('cabang_code')->merge($units->pluck('id_cabang'))->filter()->unique();

            $row->cabang_count = $cabangCodes->count();
            $row->area_count = $areas->count();
            $row->has_sewa = $vs->contains(fn ($v) => $v->harga_sewa !== null);
            $row->has_kiriman = $vs->contains(fn ($v) => isset($rel['tarifVsIds'][$v->id_vendor_skill]));
            $row->kendaraan_count = $units->count();
            $row->kendaraan = $this->kendaraanItems($units, $vs, $rel['skillNames'], $row->nama_perusahaan);
            $row->cabang_list = $cabangCodes->sort()->values()->map(fn ($c) => ['code' => $c, 'nama' => $cabangNames->get($c)])->all();
            $row->area_list = $areas->map(fn ($id) => $rel['skillNames']->get($id))->filter()->sort()->values()->all();

            return $row;
        })->values();
    }
}
