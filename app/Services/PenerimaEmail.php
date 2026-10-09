<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\User;

/**
 * Daftar alamat email per peran, dipakai semua notifikasi (pengajuan sewa,
 * vendor baru, usulan harga). WM/KA = cabang terkait, WH = approver global,
 * WC/DCI = semua user dengan role itu. Alamat tidak valid dibuang.
 */
class PenerimaEmail
{
    /**
     * @param  string[]  $peran  WM | WH | KA | WC | DCI | ...
     * @return string[]
     */
    public function peran(array $peran, ?string $cabang): array
    {
        $emails = [];
        foreach (array_unique($peran) as $r) {
            $emails = array_merge($emails, match ($r) {
                'WM' => $this->wm($cabang),
                'WH' => $this->wh(),
                'KA' => $this->roleDiCabang('KA', $cabang),
                default => $this->role($r),
            });
        }

        return array_values(array_unique($emails));
    }

    /** @return string[] */
    public function user(?int $id): array
    {
        return $id ? $this->users(User::whereKey($id)->get()) : [];
    }

    /** WM cabang: approver (+cadangan) di sesi_approval; fallback user role WM di cabang itu. */
    public function wm(?string $cabang): array
    {
        $rule = Approval::where('role_berwenang', 'WM')->where('id_cabang', $cabang)->first();

        return $this->approver($rule) ?: $this->roleDiCabang('WM', $cabang);
    }

    /** WH (global): approver (+cadangan) di sesi_approval; fallback semua user role WH. */
    public function wh(): array
    {
        $rule = Approval::where('role_berwenang', 'WH')->whereNull('id_cabang')->first();

        return $this->approver($rule) ?: $this->role('WH');
    }

    public function role(string $role): array
    {
        return $this->users(
            User::whereHas('userUtility', fn ($q) => $q->where('role', $role))->get()
        );
    }

    /**
     * Fallback kalau sesi_approval belum punya rule untuk cabang ini: WM dari override
     * di sesi_approval + view eksternal IT, KG/KA dari lntrn_users.branch_id.
     */
    public function roleDiCabang(string $role, ?string $cabang): array
    {
        if (! $cabang) {
            return [];
        }

        $usernames = collect();

        if ($role === 'WM') {
            $overrideUsernames = UserCabangResolver::overrideWmMapByCabang()
                ->get(strtoupper($cabang), collect())
                ->pluck('username');
            $liveUsernames = UserCabangResolver::liveWmMapByCabang()
                ->get(strtoupper($cabang), collect())
                ->pluck('username');
            $usernames = $overrideUsernames->merge($liveUsernames)->unique();
        } elseif (in_array($role, ['KG', 'KA'], true)) {
            $usernames = UserCabangResolver::liveBranchIdMapByCabang()
                ->get(strtoupper($cabang), collect())
                ->pluck('username');
        }

        return $this->users(
            User::whereIn('username', $usernames)
                ->whereHas('userUtility', fn ($q) => $q->where('role', $role))
                ->get()
        );
    }

    private function approver(?Approval $rule): array
    {
        if (! $rule) {
            return [];
        }

        $ids = array_filter([$rule->id_approver, $rule->id_approver_cadangan]);

        return $this->users(User::whereIn('id', $ids)->get());
    }

    private function users($users): array
    {
        return $users->pluck('email')
            ->map(fn ($e) => trim((string) $e))
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }
}
