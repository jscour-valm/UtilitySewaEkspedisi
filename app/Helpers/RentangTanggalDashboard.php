<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filter tanggal diajukan di dashboard: default 7 hari terakhir s/d hari ini, rentang maks 7 hari.
 * Antrian pending milik peran approver (WM/WC/WH: giliran peran itu; DCI: semua pending)
 * selalu tampil tanpa ikut filter.
 */
class RentangTanggalDashboard
{
    public const MAKS_HARI = 7;

    public function __construct(
        public readonly Carbon $dari,
        public readonly Carbon $sampai,
        public readonly bool $dipotong = false,
    ) {}

    public static function dariRequest(?Request $request = null): self
    {
        $request ??= request();
        $hariIni = Carbon::today();

        $sampai = self::parse($request->query('sampai')) ?? $hariIni->copy();
        $dari = self::parse($request->query('dari')) ?? $sampai->copy()->subDays(self::MAKS_HARI - 1);

        if ($dari->gt($sampai)) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        $dipotong = false;
        if ($dari->diffInDays($sampai) > self::MAKS_HARI - 1) {
            $dari = $sampai->copy()->subDays(self::MAKS_HARI - 1);
            $dipotong = true;
        }

        return new self($dari, $sampai, $dipotong);
    }

    private static function parse($nilai): ?Carbon
    {
        if (! is_string($nilai) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai)) {
            return null;
        }

        try {
            $tanggal = Carbon::createFromFormat('Y-m-d', $nilai)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        // Tolak tanggal yang "meluber" (mis. 2026-13-45 → tahun berikutnya)
        return $tanggal->format('Y-m-d') === $nilai ? $tanggal : null;
    }

    public function mencakup($tanggal): bool
    {
        if (! $tanggal) {
            return false;
        }

        return Carbon::parse($tanggal)->betweenIncluded($this->dari->copy()->startOfDay(), $this->sampai->copy()->endOfDay());
    }

    /** Pengajuan tampil: diajukan dalam rentang, atau termasuk antrian pending peran ini. */
    public function tampil(?string $role, $tanggal, ?string $status, ?string $giliran): bool
    {
        return $this->mencakup($tanggal) || self::antrianPending($role, $status, $giliran);
    }

    public static function antrianPending(?string $role, ?string $status, ?string $giliran): bool
    {
        if (in_array($role, ['WM', 'WC', 'WH'], true)) {
            return $giliran === $role;
        }

        return $role === 'DCI' && strtolower((string) $status) === 'pending';
    }
}
