<?php

namespace App\Http\Controllers;

use App\Models\PengajuanSewa;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Semua pengajuan sewa tanpa batas periode dashboard, sesuai cakupan peran: KG/KA cabang
 * sendiri, WM cabang di areanya, WC/WH/DCI semua cabang. Untuk KG menunya bernama "History".
 */
class RiwayatPengajuanController extends Controller
{
    private const PER_HALAMAN = 25;

    public function index(Request $request)
    {
        $user = auth()->user();
        $isKg = $user->userUtility?->role === 'KG';

        $cakupan = function () use ($user) {
            $q = $this->dasar();
            if (! $user->isGlobalAccess()) {
                $q->whereIn('p.id_cabang', $user->getCabangIds());
            }

            return $q;
        };

        // Nama cabang diambil terpisah: kolom Code master cabang beda collation dengan id_cabang (tidak bisa di-join).
        $kodeCabang = $cakupan()->distinct()->orderBy('p.id_cabang')->pluck('p.id_cabang');
        $namaCabang = DB::connection('sqlsrv')->table('sesi_master_cabang')->whereIn('Code', $kodeCabang)->pluck('Name', 'Code');

        $search = trim((string) $request->get('search', ''));
        $filters = [
            'status' => $this->pilihan($request, 'status', array_keys(PengajuanSewa::BADGE_STATUS)),
            'jenis' => $this->pilihan($request, 'jenis', array_keys(PengajuanSewa::JENIS)),
            'tujuan' => $this->pilihan($request, 'tujuan', PengajuanSewa::TUJUAN),
            'cabang' => $this->pilihan($request, 'cabang', $kodeCabang->all()),
        ];
        $tglDari = $this->tanggal($request->get('tgl_dari'));
        $tglSampai = $this->tanggal($request->get('tgl_sampai'));
        if ($tglDari && $tglSampai && $tglDari->gt($tglSampai)) {
            [$tglDari, $tglSampai] = [$tglSampai, $tglDari];
        }

        $diajukan = DB::raw('COALESCE(p.submitted_at, p.created_at)');
        $rows = $this->halaman(
            $cakupan()
                ->leftJoin('lntrn_users as u', 'u.id', '=', 'p.submitted_by')
                ->addSelect('u.name as pengaju')
                ->when($search !== '', function ($q) use ($search) {
                    $q->where(function ($qq) use ($search) {
                        $qq->where('pe.nama_perusahaan', 'like', '%'.$search.'%')
                            ->orWhere('u.name', 'like', '%'.$search.'%');
                        if (ctype_digit(ltrim($search, '#'))) {
                            $qq->orWhere('p.id_pengajuan_sewa', (int) ltrim($search, '#'));
                        }
                    });
                })
                ->when($filters['status'], fn ($q, $v) => $q->whereIn(DB::raw('LOWER(p.status_pengajuan)'), $v))
                ->when($filters['jenis'], fn ($q, $v) => $q->whereIn('p.jenis_pengajuan', $v))
                ->when($filters['tujuan'], fn ($q, $v) => $q->whereIn('p.tujuan_penyewaan', $v))
                ->when($filters['cabang'], fn ($q, $v) => $q->whereIn('p.id_cabang', $v))
                ->when($tglDari, fn ($q, $v) => $q->where($diajukan, '>=', $v->copy()->startOfDay()))
                ->when($tglSampai, fn ($q, $v) => $q->where($diajukan, '<=', $v->copy()->endOfDay())),
            self::PER_HALAMAN,
        );

        $opsi = fn (array $nilaiLabel) => collect($nilaiLabel)->map(fn ($label, $nilai) => ['value' => (string) $nilai, 'label' => $label])->values()->all();
        $facets = [];
        $facets[] = ['key' => 'status', 'label' => 'Status', 'options' => $opsi(array_map(fn ($b) => $b['label'], PengajuanSewa::BADGE_STATUS))];
        $facets[] = ['key' => 'jenis', 'label' => 'Jenis Pengajuan', 'options' => $opsi(PengajuanSewa::JENIS)];
        $facets[] = ['key' => 'tujuan', 'label' => 'Tujuan Penyewaan', 'options' => $opsi(array_combine(PengajuanSewa::TUJUAN, PengajuanSewa::TUJUAN))];
        $multiCabang = $user->isGlobalAccess() || count($user->getCabangIds()) > 1;
        if ($kodeCabang->count() > 1) {
            $facets[] = ['key' => 'cabang', 'label' => 'Cabang', 'options' => $opsi(
                $kodeCabang->mapWithKeys(fn ($kode) => [$kode => $kode.(isset($namaCabang[$kode]) ? ' - '.$namaCabang[$kode] : '')])->all()
            )];
        }

        return view('pages.pengajuan.daftar', [
            'rows' => $rows,
            'search' => $search,
            'facets' => $facets,
            'tglDari' => $tglDari?->format('Y-m-d') ?? '',
            'tglSampai' => $tglSampai?->format('Y-m-d') ?? '',
            'multiCabang' => $multiCabang,
            'isKg' => $isKg,
            'adaFilter' => $search !== '' || $tglDari || $tglSampai || collect($filters)->flatten()->isNotEmpty(),
        ]);
    }

    /**
     * Pengajuan aktif + vendornya. leftJoin tanpa filter flag di tabel lookup: vendor/kendaraan
     * yang sudah nonaktif tidak boleh menyembunyikan pengajuannya.
     */
    private function dasar(): Builder
    {
        return DB::connection('sqlsrv')->table('sesi_pengajuan_sewa as p')
            ->leftJoin('sesi_unit_kendaraan as k', 'p.id_kendaraan', '=', 'k.id_kendaraan')
            ->leftJoin('sesi_perusahaan_ekspedisi as pe', DB::raw('COALESCE(k.id_perusahaan, p.id_perusahaan_ekspedisi)'), '=', 'pe.id_perusahaan')
            ->where('p.flag', true);
    }

    /** Satu halaman hasil, terbaru dulu, dilengkapi `giliran` (peran berikutnya) dan `pakai_rasio`. */
    private function halaman(Builder $query, int $perHalaman): LengthAwarePaginator
    {
        $rows = $query
            ->orderByDesc('p.submitted_at')->orderByDesc('p.id_pengajuan_sewa')
            ->addSelect([
                'p.id_pengajuan_sewa as id', 'p.id_cabang', 'p.jenis_pengajuan', 'p.tujuan_penyewaan', 'p.tanggal_pengiriman',
                'p.submitted_at', 'p.created_at', 'p.harga_sewa', 'p.rasio_sewa', 'p.status_pengajuan',
                'p.alur_approval', 'p.kategori_approval', 'pe.badan_usaha', 'pe.nama_perusahaan',
            ])
            ->paginate($perHalaman)->withQueryString();

        // Approve dihitung sejak submitted_at terakhir (edit = mulai ulang dari WM).
        $ids = collect($rows->items())->pluck('id');
        $logApproved = $ids->isEmpty() ? collect() : DB::connection('sqlsrv')->table('sesi_approval_log as al')
            ->leftJoin('sesi_approval as ar', 'ar.id_approval_rule', '=', 'al.id_approval_rule')
            ->whereIn('al.id_pengajuan_sewa', $ids)
            ->where('al.flag', true)->where('al.status', 'approved')
            ->get(['al.id_pengajuan_sewa', 'al.decided_at', DB::raw('COALESCE(al.role_approver, ar.role_berwenang) as peran')])
            ->groupBy('id_pengajuan_sewa');

        $rows->getCollection()->transform(function ($p) use ($logApproved) {
            $sudah = $logApproved->get($p->id, collect())
                ->filter(fn ($l) => ! $p->submitted_at || $l->decided_at >= $p->submitted_at)
                ->pluck('peran')->filter()->unique()->values()->all();
            $p->giliran = PengajuanSewa::approverBerikutnyaDari(
                $p->status_pengajuan,
                PengajuanSewa::parseAlur($p->alur_approval, $p->kategori_approval),
                $sudah
            );
            $p->pakai_rasio = PengajuanSewa::pakaiRasioUntuk($p->jenis_pengajuan, $p->tujuan_penyewaan);

            return $p;
        });

        return $rows;
    }

    /** Nilai filter multi-pilih yang termasuk daftar yang diizinkan. */
    private function pilihan(Request $request, string $kunci, array $diizinkan): array
    {
        return array_values(array_intersect(array_map('strval', (array) $request->input($kunci, [])), array_map('strval', $diizinkan)));
    }

    private function tanggal($nilai): ?Carbon
    {
        if (! is_string($nilai) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $nilai)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
