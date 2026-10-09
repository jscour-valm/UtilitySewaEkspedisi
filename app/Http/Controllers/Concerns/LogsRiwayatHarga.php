<?php

namespace App\Http\Controllers\Concerns;

use App\Models\RiwayatHargaSewaTruk;
use App\Models\RiwayatTarifKirimanRutin;

/**
 * Catat histori harga master — dipakai PersetujuanMasterService (usulan harga disetujui WH)
 * & TarifKirimanRutinController (edit tarif langsung oleh DCI). Ditampilkan di popup Riwayat
 * pada detail perusahaan (PerusahaanController::riwayatHarga). Cuma insert kalau harga
 * benar-benar berubah.
 */
trait LogsRiwayatHarga
{
    private function catatRiwayatHargaSewaTruk(int $idVendorSkill, $hargaLama, $hargaBaru): void
    {
        // harga_baru NOT NULL di DB — kolom harga_sewa sendiri nullable (misal
        // dikosongkan sengaja dari form Kelola Tarif), gak ada "harga baru" buat dicatat.
        if ($hargaBaru === null || (float) $hargaLama === (float) $hargaBaru) {
            return;
        }

        RiwayatHargaSewaTruk::create([
            'id_vendor_skill' => $idVendorSkill,
            'harga_lama' => $hargaLama,
            'harga_baru' => $hargaBaru,
            'tanggal_perubahan' => now(),
            'diubah_oleh' => auth()->id(),
            'flag' => true,
        ]);
    }

    private function catatRiwayatTarifKirimanRutin(int $idTarif, $biayaLama, $biayaBaru): void
    {
        if ((float) $biayaLama === (float) $biayaBaru) {
            return;
        }

        RiwayatTarifKirimanRutin::create([
            'id_tarif' => $idTarif,
            'biaya_lama' => $biayaLama,
            'biaya_baru' => $biayaBaru,
            'tanggal_perubahan' => now(),
            'diubah_oleh' => auth()->id(),
            'flag' => true,
        ]);
    }
}
