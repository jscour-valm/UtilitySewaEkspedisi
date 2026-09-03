<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        // Cek session dulu (cache dari login), fallback ke DB buat konteks non-web (artisan, queue)
        if (session()->has('cabang_code')) {
            return session('cabang_code');
        }

        $userCabang = \DB::connection('sqlsrv')->table('sesi_user_cabang')
            ->where('username', $this->username)
            ->first();
        return $userCabang?->cabang_code;
    }

    public function getCabangIds(): array
    {
        // Untuk role multi-cabang (WM = area-based, punya banyak cabang dalam 1 area)
        // Query SEMUA cabang_code yang di-assign ke user ini
        $userCabangs = \DB::connection('sqlsrv')->table('sesi_user_cabang')
            ->where('username', $this->username)
            ->where('flag', true)
            ->pluck('cabang_code')
            ->toArray();

        return array_values($userCabangs); // Re-index numerik, bukan string keys
    }

    public function getRoleLabel(): string
    {
        $role = $this->userUtility?->role;
        $roleMap = [
            'KG'  => 'Kepala Gudang',
            'WM'  => 'Warehouse Manager',
            'WH'  => 'Warehouse Handler',
            'DCI' => 'DCI',
            'KA'  => 'Kepala Area',
        ];
        return $roleMap[$role] ?? $role ?? '-';
    }

    public function isGlobalAccess(): bool
    {
        $role = $this->userUtility?->role;
        return in_array($role, ['WH', 'DCI']);
    }

    public function canAccessCabang(?string $cabangCode): bool
    {
        if ($this->isGlobalAccess()) {
            return true;
        }

        if (!$cabangCode) {
            return false;
        }

        $userCabang = \DB::connection('sqlsrv')->table('sesi_user_cabang')
            ->where('username', $this->username)
            ->where('cabang_code', $cabangCode)
            ->exists();

        return $userCabang;
    }

    public function getArea(): ?string
    {
        return \DB::connection('sqlsrv')->table('sesi_user_cabang')
            ->where('username', $this->username)
            ->where('flag', true)
            ->whereNotNull('area')
            ->value('area');
    }

    public function getCabangDetails(): array
    {
        // [code => name] untuk semua cabang yang di-assign ke user ini
        $codes = $this->getCabangIds();
        if (empty($codes)) return [];

        return \DB::connection('sqlsrv')->table('sesi_master_cabang')
            ->whereIn('Code', $codes)
            ->pluck('Name', 'Code')
            ->toArray();
    }
}

