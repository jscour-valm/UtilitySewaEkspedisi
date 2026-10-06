<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserCabangResolver
{
    private static ?string $cachedLatestWmEffectiveDate = null;

    private static array $liveWmRowsCache = [];

    private static array $wmOverrideCabangIdsCache = [];

    private static ?Collection $liveWmMapByCabangCache = null;

    private static ?Collection $overrideWmMapByCabangCache = null;

    private static array $liveBranchIdCache = [];

    private static ?Collection $liveBranchIdMapByCabangCache = null;

    public static function latestWmEffectiveDate(): ?string
    {
        if (self::$cachedLatestWmEffectiveDate === null) {
            self::$cachedLatestWmEffectiveDate = DB::connection('sqlsrv')
                ->table('lntrn_view_summary_mapping_areas')
                ->where('type', 'wm')
                ->where('company', 'TT')
                ->max('effective_date');
        }

        return self::$cachedLatestWmEffectiveDate;
    }

    public static function liveWmRows(string $username): Collection
    {
        if (! array_key_exists($username, self::$liveWmRowsCache)) {
            $tanggal = self::latestWmEffectiveDate();

            self::$liveWmRowsCache[$username] = $tanggal
                ? DB::connection('sqlsrv')->table('lntrn_view_summary_mapping_areas')
                    ->where('type', 'wm')
                    ->where('company', 'TT')
                    ->where('effective_date', $tanggal)
                    ->where('username', $username)
                    ->get()
                : collect();
        }

        return self::$liveWmRowsCache[$username];
    }

    public static function liveWmMapByCabang(): Collection
    {
        if (self::$liveWmMapByCabangCache === null) {
            $tanggal = self::latestWmEffectiveDate();

            self::$liveWmMapByCabangCache = $tanggal
                ? DB::connection('sqlsrv')->table('lntrn_view_summary_mapping_areas')
                    ->where('type', 'wm')
                    ->where('company', 'TT')
                    ->where('effective_date', $tanggal)
                    ->get(['username', 'code'])
                    ->groupBy(fn ($row) => strtoupper($row->code))
                : collect();
        }

        return self::$liveWmMapByCabangCache;
    }

    public static function liveBranchId(string $username): ?string
    {
        if (! array_key_exists($username, self::$liveBranchIdCache)) {
            $branchId = DB::connection('sqlsrv')->table('lntrn_users')
                ->where('username', $username)
                ->value('branch_id');

            $branchId = $branchId ? strtoupper(trim($branchId)) : null;

            if ($branchId === null && preg_match('/^(\d{2}[a-z])-/i', $username, $m)) {
                $branchId = strtoupper($m[1]);
            }

            self::$liveBranchIdCache[$username] = $branchId;
        }

        return self::$liveBranchIdCache[$username];
    }

    public static function liveBranchIdMapByCabang(): Collection
    {
        if (self::$liveBranchIdMapByCabangCache === null) {
            self::$liveBranchIdMapByCabangCache = DB::connection('sqlsrv')->table('lntrn_users')
                ->whereNotNull('branch_id')
                ->get(['username', 'branch_id'])
                ->groupBy(fn ($row) => strtoupper(trim($row->branch_id)));
        }

        return self::$liveBranchIdMapByCabangCache;
    }

    public static function wmOverrideCabangIds(string $username): array
    {
        if (! array_key_exists($username, self::$wmOverrideCabangIdsCache)) {
            $userId = DB::connection('sqlsrv')->table('lntrn_users')
                ->where('username', $username)
                ->value('id');

            self::$wmOverrideCabangIdsCache[$username] = $userId
                ? DB::connection('sqlsrv')->table('sesi_approval')
                    ->where('role_berwenang', 'WM')
                    ->where('flag', true)
                    ->where('id_approver', $userId)
                    ->whereNotNull('id_cabang')
                    ->pluck('id_cabang')
                    ->map(fn ($c) => strtoupper($c))
                    ->unique()
                    ->values()
                    ->all()
                : [];
        }

        return self::$wmOverrideCabangIdsCache[$username];
    }

    public static function overrideWmMapByCabang(): Collection
    {
        if (self::$overrideWmMapByCabangCache === null) {
            self::$overrideWmMapByCabangCache = DB::connection('sqlsrv')
                ->table('sesi_approval as a')
                ->join('lntrn_users as u', 'u.id', '=', 'a.id_approver')
                ->where('a.role_berwenang', 'WM')
                ->where('a.flag', true)
                ->whereNotNull('a.id_cabang')
                ->get(['a.id_approval_rule', 'a.id_cabang', 'u.id as user_id', 'u.name', 'u.username'])
                ->groupBy(fn ($row) => strtoupper($row->id_cabang));
        }

        return self::$overrideWmMapByCabangCache;
    }

    public static function resolveCabangIds(string $username, ?string $role): array
    {
        if ($role === 'WM') {
            $override = self::wmOverrideCabangIds($username);
            if (! empty($override)) {
                return $override;
            }

            return self::liveWmRows($username)
                ->pluck('code')
                ->filter()
                ->map(fn ($c) => strtoupper($c))
                ->unique()
                ->values()
                ->all();
        }

        if (in_array($role, ['KG', 'KA'], true)) {
            $branchId = self::liveBranchId($username);

            return $branchId ? [$branchId] : [];
        }

        return [];
    }

    public static function resolveArea(string $username, ?string $role): ?string
    {
        if ($role === 'WM') {
            if (! empty(self::wmOverrideCabangIds($username))) {
                return null;
            }

            return self::liveWmRows($username)->first()?->area;
        }

        return null;
    }

    public static function canAccess(string $username, ?string $role, string $cabangCode): bool
    {
        return in_array(strtoupper($cabangCode), self::resolveCabangIds($username, $role), true);
    }

    /** Reset semua cache statis — dipakai di test biar tiap test mulai bersih. */
    public static function clearCache(): void
    {
        self::$cachedLatestWmEffectiveDate = null;
        self::$liveWmRowsCache = [];
        self::$wmOverrideCabangIdsCache = [];
        self::$liveWmMapByCabangCache = null;
        self::$overrideWmMapByCabangCache = null;
        self::$liveBranchIdCache = [];
        self::$liveBranchIdMapByCabangCache = null;
    }
}
