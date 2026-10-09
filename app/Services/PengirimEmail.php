<?php

namespace App\Services;

use Closure;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Kirim email notifikasi SETELAH response lewat defer() — tanpa tabel `jobs`, dan
 * kegagalan SMTP tidak pernah menggagalkan aksi user. Semua DCI selalu di-CC.
 */
class PengirimEmail
{
    public function __construct(private PenerimaEmail $penerima) {}

    /**
     * @param  string  $label  untuk log, mis. "'baru' pengajuan #12"
     * @param  string[]  $to
     * @param  string[]  $cc
     * @param  Closure(): Mailable  $buatMailable  dipanggil di dalam defer
     */
    public function kirim(string $label, array $to, array $cc, Closure $buatMailable): void
    {
        $to = array_values(array_unique($to));
        if (empty($to)) {
            Log::warning("Email $label dilewati: tidak ada penerima beremail.");

            return;
        }

        $cc = array_values(array_diff(array_unique(array_merge($cc, $this->penerima->role('DCI'))), $to));

        defer(function () use ($label, $to, $cc, $buatMailable) {
            try {
                $mailable = $buatMailable();

                // ===== MODE TES (aktif) — semua email ke alamat tes (.env MAIL_TEST_TO), penerima asli hanya dicatat di log =====
                $testTo = config('mail.test_to');
                if (! $testTo) {
                    Log::warning("Email $label tidak dikirim: MAIL_TEST_TO kosong (mode tes).");

                    return;
                }
                Log::info("Email $label dikirim ke $testTo (mode tes). Penerima asli: To=".implode(',', $to).' CC='.implode(',', $cc));
                Mail::to($testTo)->send($mailable);

                // ===== PRODUKSI — UNCOMMENT saat deploy, lalu HAPUS blok MODE TES di atas =====
                // Mail::to($to)->cc($cc)->send($mailable);
            } catch (\Throwable $e) {
                Log::warning("Gagal kirim email $label: ".$e->getMessage());
            }
        });
    }
}
