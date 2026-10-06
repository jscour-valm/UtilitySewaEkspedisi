<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\UserCabangResolver;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable, SoftDeletes;

    protected $connection = 'sqlsrv';

    protected $table = 'lntrn_users';

    protected $primaryKey = 'id';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    public function userUtility()
    {
        return $this->hasOne(UserUtility::class, 'user_id', 'id')->sesi();
    }

    public function isBlocked(): bool
    {
        return (bool) $this->is_blocked;
    }

    public function getCabangId(): ?string
    {
        // Cek session dulu (cache dari login), fallback ke resolver buat konteks non-web (artisan, queue)
        if (session()->has('cabang_code')) {
            return session('cabang_code');
        }

        return $this->getCabangIds()[0] ?? null;
    }

    public function getCabangIds(): array
    {
        // Untuk role multi-cabang (WM = area-based, punya banyak cabang dalam 1 area).
        // Sumbernya: override manual (sesi_approval, Task 18 2 Okt 2026) kalau
        // ada, else live-query ke view eksternal IT (lihat UserCabangResolver —
        // WM bisa di-rolling kapan saja jadi gak bisa cuma andelin sync sekali jalan).
        return UserCabangResolver::resolveCabangIds($this->username, $this->userUtility?->role);
    }

    public function getRoleLabel(): string
    {
        $role = $this->userUtility?->role;
        $roleMap = [
            'KG' => 'Kepala Gudang',
            'WM' => 'Warehouse Manager',
            'WC' => 'Warehouse Manager Coordinator',
            'WH' => 'Warehouse Head',
            'DCI' => 'Distribution Continuous Improvement',
            'KA' => 'Kepala Admin',
        ];

        return $roleMap[$role] ?? $role ?? '-';
    }

    public function isGlobalAccess(): bool
    {
        $role = $this->userUtility?->role;

        return in_array($role, ['WH', 'WC', 'DCI']);
    }

    public function canAccessCabang(?string $cabangCode): bool
    {
        if ($this->isGlobalAccess()) {
            return true;
        }

        if (! $cabangCode) {
            return false;
        }

        return UserCabangResolver::canAccess($this->username, $this->userUtility?->role, $cabangCode);
    }

    public function getArea(): ?string
    {
        return UserCabangResolver::resolveArea($this->username, $this->userUtility?->role);
    }

    public function getCabangDetails(): array
    {
        // [code => name] untuk semua cabang yang di-assign ke user ini
        $codes = $this->getCabangIds();
        if (empty($codes)) {
            return [];
        }

        return \DB::connection('sqlsrv')->table('sesi_master_cabang')
            ->whereIn('Code', $codes)
            ->pluck('Name', 'Code')
            ->toArray();
    }
}
