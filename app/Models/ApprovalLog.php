<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalLog extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'sesi_approval_log';
    protected $primaryKey = 'id_approval_log';
    public $timestamps = true;

    protected $fillable = [
        'id_pengajuan_sewa',
        'id_approval_rule',
        'id_approver',
        'status',
        'decided_at',
        'alasan_penolakan',
        'flag',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
        'flag' => 'boolean',
    ];

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(PengajuanSewa::class, 'id_pengajuan_sewa', 'id_pengajuan_sewa');
    }

    public function approval(): BelongsTo
    {
        return $this->belongsTo(Approval::class, 'id_approval_rule', 'id_approval_rule');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_approver', 'id');
    }
}
