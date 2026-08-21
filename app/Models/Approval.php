<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Approval extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'sesi_approval';
    protected $primaryKey = 'id_approval_rule';
    public $timestamps = true;

    protected $fillable = [
        'id_cabang',
        'kategori_approval',
        'tingkat',
        'role_berwenang',
        'id_approver_cadangan',
        'flag',
    ];

    protected $casts = [
        'flag' => 'boolean',
    ];

    public function approvalLogs(): HasMany
    {
        return $this->hasMany(ApprovalLog::class, 'id_approval_rule', 'id_approval_rule');
    }
}
