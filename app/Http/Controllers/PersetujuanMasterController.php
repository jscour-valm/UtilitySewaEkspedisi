<?php

namespace App\Http\Controllers;

use App\Exceptions\PersetujuanMasterException;
use App\Helpers\FormatHelper;
use App\Http\Controllers\Concerns\BuildsPerusahaanSummary;
use App\Http\Controllers\Concerns\ManagesVendorMasterData;
use App\Models\PengajuanSewa;
use App\Models\PerusahaanEkspedisi;
use App\Models\UsulanHarga;
use App\Services\PersetujuanMasterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Proses persetujuan vendor baru & usulan harga master (KG → WM → WH).
 * Akses: KG cabang pengaju, WM yang memegang cabang pengaju, WH & DCI semua. WC/KA tidak.
 */
class PersetujuanMasterController extends Controller
{
    use BuildsPerusahaanSummary;
    use ManagesVendorMasterData;

    /** Link di email notifikasi (1 email dibaca banyak role). */
    public function buka(string $jenis, int $id)
    {
        $role = auth()->user()->userUtility?->role;

        if ($jenis === 'vendor') {
            $vendor = PerusahaanEkspedisi::withInactive()->findOrFail($id);
            if (in_array($role, ['KG', 'WM', 'WH', 'DCI'], true) && $this->bolehAksesProses($vendor->id_cabang_pengaju)) {
                return redirect()->route('persetujuan.vendor.show', $id);
            }

            return $vendor->flag
                ? redirect()->route('perusahaan.show', $id)
                : redirect()->route('dashboard');
        }

        $usulan = UsulanHarga::withInactive()->findOrFail($id);
        if (in_array($role, ['KG', 'WM', 'WH', 'DCI'], true) && $this->bolehAksesProses($usulan->id_cabang_pengaju)) {
            return redirect()->route('persetujuan.harga.show', $id);
        }

        return redirect()->route('perusahaan.show', $usulan->id_perusahaan);
    }

    public function showVendor(int $id)
    {
        $vendor = PerusahaanEkspedisi::withInactive()->with(['submittedBy', 'approvalLogs.approver'])->findOrFail($id);
        abort_unless($vendor->id_cabang_pengaju && $this->bolehAksesProses($vendor->id_cabang_pengaju), 404);

        $user = auth()->user();
        $role = $user->userUtility?->role;
        $giliran = $vendor->giliranPersetujuan();

        $pengajuan = PengajuanSewa::with(['submittedBy'])
            ->where(function ($q) use ($id) {
                $q->where('id_perusahaan_ekspedisi', $id)
                    ->orWhereHas('kendaraan', fn ($k) => $k->withInactive()->where('id_perusahaan', $id));
            })
            ->orderByDesc('submitted_at')
            ->limit(20)
            ->get();

        return view('pages.persetujuan.vendor', [
            'vendor' => $vendor,
            'peran' => $role,
            'bisaPutuskan' => $giliran !== null && $giliran === $role,
            'bisaAjukanUlang' => $role === 'KG' && $vendor->statusPersetujuan() === PerusahaanEkspedisi::STATUS_REJECTED
                && (int) $vendor->submitted_by === (int) $user->id,
            'pengajuan' => $pengajuan,
            'namaCabang' => DB::connection('sqlsrv')->table('sesi_master_cabang')->where('Code', $vendor->id_cabang_pengaju)->value('Name'),
            'docs' => collect($vendor->identitas_owner ?? [])->map(fn ($p) => FormatHelper::identitasOwnerSrc($p))->values(),
            'breadcrumb' => [
                'back_url' => route('dashboard'),
                'back_label' => 'Dashboard',
                'title' => 'Pengajuan Vendor Baru',
            ],
        ]);
    }

    /** Validasi WM / approval WH / tolak. */
    public function putuskanVendor(Request $request, int $id)
    {
        $data = $request->validate([
            'keputusan' => 'required|in:setuju,tolak',
            'alasan' => 'nullable|required_if:keputusan,tolak|string|max:500',
        ]);

        $vendor = PerusahaanEkspedisi::withInactive()->findOrFail($id);

        try {
            app(PersetujuanMasterService::class)->putuskan($vendor, auth()->user(), $data['keputusan'] === 'setuju', $data['alasan'] ?? null);
        } catch (PersetujuanMasterException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        }

        $vendor->refresh();

        return response()->json([
            'message' => match ($vendor->statusPersetujuan()) {
                PerusahaanEkspedisi::STATUS_MENUNGGU_APPROVAL => 'Vendor divalidasi, menunggu approval WH.',
                PerusahaanEkspedisi::STATUS_APPROVED => 'Vendor disetujui.',
                default => 'Vendor ditolak.',
            },
        ]);
    }

    /** KG pengaju memperbaiki data vendor yang ditolak lalu mengajukannya lagi. */
    public function ajukanUlangVendor(Request $request, int $id)
    {
        $vendor = PerusahaanEkspedisi::withInactive()->findOrFail($id);

        $data = $request->validate([
            'nama_perusahaan' => 'required|string|max:255|unique:sqlsrv.dbo.sesi_perusahaan_ekspedisi,nama_perusahaan,'.$id.',id_perusahaan',
            'badan_usaha' => 'required|in:PT,CV,UD,Perseorangan',
            'no_telepon' => 'required|string|max:20',
            'alamat_kantor' => 'required|string',
            'identitas_owner' => 'nullable|array|max:3',
            'identitas_owner.*' => 'file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $db = DB::connection('sqlsrv');
        $db->beginTransaction();
        try {
            $vendor->fill([
                'nama_perusahaan' => $data['nama_perusahaan'],
                'badan_usaha' => $data['badan_usaha'],
                'no_telepon' => $data['no_telepon'],
                'alamat_kantor' => $data['alamat_kantor'],
            ]);

            app(PersetujuanMasterService::class)->ajukanUlangVendor($vendor, auth()->user());

            // Foto baru menggantikan semua foto lama; tanpa upload, foto lama dipertahankan.
            if ($request->hasFile('identitas_owner')) {
                $this->deleteIdentitasOwnerFiles($vendor->identitas_owner);
                $vendor->identitas_owner = $this->storeIdentitasOwnerFiles($request->file('identitas_owner'), (string) $vendor->id_perusahaan);
                $vendor->save();
            }

            $db->commit();
        } catch (PersetujuanMasterException $e) {
            $db->rollBack();

            return response()->json(['error' => $e->getMessage()], $e->status);
        } catch (\Throwable $e) {
            $db->rollBack();

            return response()->json(['error' => 'Gagal mengajukan ulang vendor: '.$e->getMessage()], 500);
        }

        return response()->json(['message' => 'Vendor diajukan ulang dan menunggu validasi WM.']);
    }

    /** Usulan harga master dari halaman detail perusahaan (KG, tarif cabang sendiri). */
    public function storeUsulan(Request $request)
    {
        $data = $request->validate([
            'jenis' => 'required|in:sewa_truk,pengiriman_rutin',
            'id_perusahaan' => 'required|integer',
            'id_skill' => 'required|integer',
            'cabang_code' => 'required|string|max:10',
            'id_jenis_barang' => 'nullable|required_if:jenis,pengiriman_rutin|integer|exists:sqlsrv.dbo.sesi_jenis_barang_kiriman,id_jenis_barang',
            'harga' => 'required|numeric|min:1',
            'catatan' => 'nullable|string|max:1000',
        ]);

        if (! in_array($data['cabang_code'], $this->ownCabang(), true)) {
            return response()->json(['error' => 'Usulan hanya untuk tarif cabang Anda sendiri.'], 403);
        }
        $vendor = PerusahaanEkspedisi::find($data['id_perusahaan']);
        if (! $vendor || ! $vendor->sudahDisetujui()) {
            return response()->json(['error' => 'Vendor belum disetujui, harga master belum bisa diusulkan.'], 422);
        }

        try {
            $usulan = app(PersetujuanMasterService::class)->ajukanUsulan(
                [
                    'jenis' => $data['jenis'],
                    'id_perusahaan' => (int) $data['id_perusahaan'],
                    'id_skill' => (int) $data['id_skill'],
                    'cabang_code' => $data['cabang_code'],
                    'id_jenis_barang' => isset($data['id_jenis_barang']) ? (int) $data['id_jenis_barang'] : null,
                ],
                (float) $data['harga'],
                UsulanHarga::SUMBER_PERUSAHAAN,
                $data['catatan'] ?? null,
                auth()->user(),
            );
        } catch (PersetujuanMasterException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        }

        if (! $usulan) {
            return response()->json(['error' => 'Harga yang diusulkan sama dengan harga master sekarang.'], 422);
        }
        if (! $usulan->wasRecentlyCreated) {
            return response()->json(['error' => 'Usulan dengan harga yang sama sudah diajukan dan masih menunggu keputusan.'], 422);
        }

        return response()->json(['message' => 'Usulan harga diajukan dan menunggu validasi WM.', 'id_usulan_harga' => $usulan->id_usulan_harga]);
    }

    public function showUsulan(int $id)
    {
        $usulan = UsulanHarga::withInactive()->with(['submittedBy', 'approvalLogs.approver', 'jenisBarang'])->findOrFail($id);
        abort_unless($this->bolehAksesProses($usulan->id_cabang_pengaju), 404);

        $role = auth()->user()->userUtility?->role;
        $giliran = $usulan->giliranPersetujuan();

        $pengajuan = PengajuanSewa::with('submittedBy')
            ->where(function ($q) use ($id) {
                $q->where('id_usulan_harga', $id)
                    ->orWhereHas('detailKirimanRutin', fn ($d) => $d->where('id_usulan_harga', $id));
            })
            ->orderByDesc('submitted_at')
            ->get();

        $db = DB::connection('sqlsrv');

        return view('pages.persetujuan.harga', [
            'usulan' => $usulan,
            'vendor' => PerusahaanEkspedisi::withInactive()->find($usulan->id_perusahaan),
            'peran' => $role,
            'bisaPutuskan' => $giliran !== null && $giliran === $role,
            'pengajuan' => $pengajuan,
            'namaArea' => $db->table('sesi_master_skill')->where('id_skill', $usulan->id_skill)->value('nama_skill'),
            'namaCabang' => $db->table('sesi_master_cabang')->where('Code', $usulan->cabang_code)->value('Name'),
            'breadcrumb' => [
                'back_url' => route('dashboard'),
                'back_label' => 'Dashboard',
                'title' => 'Usulan Harga Master',
            ],
        ]);
    }

    public function putuskanUsulan(Request $request, int $id)
    {
        $data = $request->validate([
            'keputusan' => 'required|in:setuju,tolak',
            'alasan' => 'nullable|required_if:keputusan,tolak|string|max:500',
        ]);

        $usulan = UsulanHarga::withInactive()->findOrFail($id);

        try {
            app(PersetujuanMasterService::class)->putuskan($usulan, auth()->user(), $data['keputusan'] === 'setuju', $data['alasan'] ?? null);
        } catch (PersetujuanMasterException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        }

        $usulan->refresh();

        return response()->json([
            'message' => match ($usulan->status) {
                UsulanHarga::STATUS_MENUNGGU_APPROVAL => 'Usulan divalidasi, menunggu approval WH.',
                UsulanHarga::STATUS_APPROVED => 'Usulan disetujui, harga master sudah diperbarui.',
                default => 'Usulan ditolak.',
            },
        ]);
    }

    /** KG = cabang sendiri, WM = cabang yang dipegang, WH/DCI = semua. */
    private function bolehAksesProses(?string $cabangPengaju): bool
    {
        $user = auth()->user();
        $role = $user->userUtility?->role;

        return match ($role) {
            'WH', 'DCI' => true,
            'KG' => in_array($cabangPengaju, $this->ownCabang(), true),
            'WM' => $user->canAccessCabang($cabangPengaju),
            default => false,
        };
    }
}
