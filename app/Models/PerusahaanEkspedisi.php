<?php

namespace App\Models;

use App\Models\Concerns\HasFlag;
use App\Models\Concerns\HasStatusPersetujuan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Vendor ekspedisi. Vendor baru dari KG berstatus menunggu_validasi → menunggu_approval
 * → approved; selama menunggu tetap flag=1 supaya bisa dipakai di pengajuan, ditolak → flag=0.
 */
class PerusahaanEkspedisi extends Model
{
    use HasFlag;
    use HasStatusPersetujuan;

    protected $connection = 'sqlsrv';

    protected $table = 'sesi_perusahaan_ekspedisi';

    protected $primaryKey = 'id_perusahaan';

    public $timestamps = true;

    protected $fillable = [
        'nama_perusahaan',
        'badan_usaha',
        'ktp_npwp',
        'no_telepon',
        'alamat_kantor',
        'identitas_owner',
        'flag',
        'status_approval',
        'id_cabang_pengaju',
        'submitted_by',
        'submitted_at',
        'alasan_penolakan',
    ];

    protected $casts = [
        'identitas_owner' => 'array',
        'flag' => 'boolean',
        'submitted_at' => 'datetime',
    ];

    public function kolomStatusPersetujuan(): string
    {
        return 'status_approval';
    }

    public function kendaraan(): HasMany
    {
        return $this->hasMany(Kendaraan::class, 'id_perusahaan', 'id_perusahaan');
    }

    /** Tanpa FK ke lntrn_users (tabel eksternal IT). */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by', 'id');
    }

    /** Keputusan WM/WH atas pengajuan vendor baru ini. */
    public function approvalLogs(): HasMany
    {
        return $this->hasMany(ApprovalLog::class, 'id_perusahaan', 'id_perusahaan')->orderBy('decided_at');
    }
}
