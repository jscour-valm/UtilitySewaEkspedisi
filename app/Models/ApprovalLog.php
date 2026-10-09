<?php

namespace App\Models;

use App\Models\Concerns\HasFlag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log keputusan approval. Satu baris milik salah satu: pengajuan sewa (id_pengajuan_sewa),
 * vendor baru (id_perusahaan), atau usulan harga master (id_usulan_harga).
 */
class ApprovalLog extends Model
{
    use HasFlag;

    protected $connection = 'sqlsrv';

    protected $table = 'sesi_approval_log';

    protected $primaryKey = 'id_approval_log';

    public $timestamps = true;

    protected $fillable = [
        'id_pengajuan_sewa',
        'id_perusahaan',
        'id_usulan_harga',
        'id_approval_rule',
        'id_approver',
        'role_approver',
        'tingkat',
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

    public function perusahaan(): BelongsTo
    {
        return $this->belongsTo(PerusahaanEkspedisi::class, 'id_perusahaan', 'id_perusahaan');
    }

    public function usulanHarga(): BelongsTo
    {
        return $this->belongsTo(UsulanHarga::class, 'id_usulan_harga', 'id_usulan_harga');
    }

    public function approval(): BelongsTo
    {
        return $this->belongsTo(Approval::class, 'id_approval_rule', 'id_approval_rule');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_approver', 'id');
    }

    /** Peran approver di log ini. Log lama (sebelum kolom role_approver ada) diturunkan dari rule-nya. */
    public function getPeranAttribute(): ?string
    {
        return $this->role_approver ?? $this->approval?->role_berwenang;
    }
}
