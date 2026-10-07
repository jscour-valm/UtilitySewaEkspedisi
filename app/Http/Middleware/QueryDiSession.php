<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjaga URL halaman tetap bersih: parameter filter/urutan (dari, sampai, sort_*, order_*)
 * yang datang lewat query disimpan di session per route lalu di-redirect ke URL tanpa
 * parameter itu. Request berikutnya mendapat nilai tersimpan kembali di query, sehingga
 * kode yang membaca request('sort_pengajuan') dsb. tidak perlu berubah.
 * Parameter kosong (mis. `sort_pengajuan=`) menghapus nilai tersimpan (kembali ke default).
 */
class QueryDiSession
{
    private const POLA = '/^(dari|sampai|sort_[a-z_]+|order_[a-z_]+)$/';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') || $request->expectsJson() || ! $request->route()?->getName()) {
            return $next($request);
        }

        $kunci = 'query_sesi.'.$request->route()->getName();
        $baru = array_filter(
            $request->query(),
            fn ($nilai, $nama) => (is_string($nilai) || $nilai === null) && preg_match(self::POLA, $nama),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($baru) {
            $tersimpan = array_merge($request->session()->get($kunci, []), $baru);
            $request->session()->put($kunci, array_filter($tersimpan, fn ($nilai) => $nilai !== null && $nilai !== ''));
            $sisa = array_diff_key($request->query(), $baru);

            return redirect($request->url().($sisa ? '?'.http_build_query($sisa) : ''));
        }

        $request->query->add($request->session()->get($kunci, []));

        return $next($request);
    }
}
