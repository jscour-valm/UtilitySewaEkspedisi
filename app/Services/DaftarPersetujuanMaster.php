<?php

namespace App\Services;

use App\Helpers\RentangTanggalDashboard;
use App\Models\PerusahaanEkspedisi;
use App\Models\UsulanHarga;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Isi tab "Vendor Baru" & "Perubahan Harga" di dashboard. Cakupan: KG & WM = cabang yang
 * dipegang, WH & DCI = semua. Proses yang masih berjalan selalu tampil; yang sudah
 * diputuskan tampil kalau diajukan dalam periode filter dashboard.
 * Badge: WM/WH = jumlah yang menunggu keputusannya; KG/DCI = jumlah yang masih berjalan.
 */
class DaftarPersetujuanMaster
{
    public const PERAN = ['KG', 'WM', 'WH', 'DCI'];

    /** @return array{vendor: Collection, harga: Collection, badge: array{vendor: int, harga: int}} */
    public function untuk(User $user, RentangTanggalDashboard $rentang): array
    {
        $peran = $user->userUtility?->role;
        if (! in_array($peran, self::PERAN, true)) {
            return ['vendor' => collect(), 'harga' => collect(), 'badge' => ['vendor' => 0, 'harga' => 0]];
        }

        $cabang = in_array($peran, ['WH', 'DCI'], true)
            ? null
            : array_values(array_filter($user->getCabangIds() ?: [$user->getCabangId()]));

        $vendor = $this->saring(
            PerusahaanEkspedisi::withInactive()->with('submittedBy')->whereNotNull('id_cabang_pengaju'),
            $cabang, $rentang
        );
        $harga = $this->saring(
            UsulanHarga::withInactive()->with(['submittedBy', 'jenisBarang']),
            $cabang, $rentang
        );

        $this->lengkapiUsulan($harga);

        $menunggu = fn (Collection $c) => $c->filter(fn ($m) => in_array($peran, ['WM', 'WH'], true)
            ? $m->giliranPersetujuan() === $peran
            : $m->sedangBerjalan())->count();

        $urut = fn (Collection $c) => $c->sortBy([
            fn ($a, $b) => ($b->giliranPersetujuan() === $peran) <=> ($a->giliranPersetujuan() === $peran),
            fn ($a, $b) => $b->sedangBerjalan() <=> $a->sedangBerjalan(),
            fn ($a, $b) => $b->submitted_at <=> $a->submitted_at,
        ])->values();

        return [
            'vendor' => $urut($vendor),
            'harga' => $urut($harga),
            'badge' => ['vendor' => $menunggu($vendor), 'harga' => $menunggu($harga)],
        ];
    }

    private function saring($query, ?array $cabang, RentangTanggalDashboard $rentang): Collection
    {
        if ($cabang !== null) {
            $query->whereIn('id_cabang_pengaju', $cabang ?: ['__none__']);
        }

        return $query->orderByDesc('submitted_at')->get()
            ->filter(fn ($m) => $m->sedangBerjalan() || $rentang->mencakup($m->submitted_at))
            ->values();
    }

    /** Nama vendor & area untuk tabel usulan (sekali query, bukan per baris). */
    private function lengkapiUsulan(Collection $usulan): void
    {
        if ($usulan->isEmpty()) {
            return;
        }
        $db = DB::connection('sqlsrv');
        $vendor = $db->table('sesi_perusahaan_ekspedisi')->whereIn('id_perusahaan', $usulan->pluck('id_perusahaan')->unique())
            ->get(['id_perusahaan', 'nama_perusahaan', 'badan_usaha'])->keyBy('id_perusahaan');
        $area = $db->table('sesi_master_skill')->whereIn('id_skill', $usulan->pluck('id_skill')->unique())->pluck('nama_skill', 'id_skill');

        foreach ($usulan as $u) {
            $v = $vendor->get($u->id_perusahaan);
            $u->setAttribute('nama_vendor', $v ? trim(($v->badan_usaha && $v->badan_usaha !== '-' ? $v->badan_usaha.' ' : '').$v->nama_perusahaan) : '-');
            $u->setAttribute('nama_area', $area->get($u->id_skill) ?? '-');
        }
    }
}
