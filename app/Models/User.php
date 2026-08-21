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
}

