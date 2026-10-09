<?php

namespace App\Http\Controllers;

use App\Models\PerusahaanEkspedisi;
use App\Models\UsulanHarga;

/**
 * Proses persetujuan vendor baru & usulan harga master (KG → WM → WH).
 */
class PersetujuanMasterController extends Controller
{
    /** Link di email notifikasi (1 email dibaca banyak role) → halaman perusahaan terkait. */
    public function buka(string $jenis, int $id)
    {
        $idPerusahaan = $jenis === 'vendor'
            ? PerusahaanEkspedisi::withInactive()->whereKey($id)->value('id_perusahaan')
            : UsulanHarga::withInactive()->whereKey($id)->value('id_perusahaan');

        abort_unless($idPerusahaan, 404);

        return redirect()->route('perusahaan.show', $idPerusahaan);
    }
}
