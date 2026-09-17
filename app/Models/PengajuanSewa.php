<?php

namespace App\Models;

use App\Models\Concerns\HasFlag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class PengajuanSewa extends Model
{
    use HasFlag;

    protected $connection = 'sqlsrv';
    protected $table = 'sesi_pengajuan_sewa';
    protected $primaryKey = 'id_pengajuan_sewa';
    public $timestamps = true;

    protected $fillable = [
        'id_kendaraan',
        'id_perusahaan_ekspedisi',
        'id_cabang',
        'tanggal_pengiriman',
        'value_muatan',
        'harga_sewa',
        'rasio_sewa',
        'kategori_approval',
        'tujuan_penyewaan',
        'jenis_pengajuan',
        'id_skill',
        'status_pengajuan',
        'current_approval_rule_id',
        'catatan_pengajuan',
        'submitted_by',
        'submitted_at',
        'flag',
        'kategori_toko',
    ];

    protected $casts = [
        'tanggal_pengiriman' => 'date',
        'value_muatan' => 'decimal:2',
        'harga_sewa' => 'decimal:2',
        'rasio_sewa' => 'decimal:2',
        'flag' => 'boolean',
        'submitted_at' => 'datetime',
    ];

    public function kendaraan(): BelongsTo
    {
        return $this->belongsTo(Kendaraan::class, 'id_kendaraan', 'id_kendaraan');
    }

    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by', 'id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by', 'id');
    }

    public function biayaTambahan(): HasMany
    {
        return $this->hasMany(BiayaTambahan::class, 'id_pengajuan_sewa', 'id_pengajuan_sewa');
    }

    public function approvalLogs(): HasMany
    {
        return $this->hasMany(ApprovalLog::class, 'id_pengajuan_sewa', 'id_pengajuan_sewa');
    }

    /**
     * Relasi ke Surat Jalan (junction table)
     * Reference-Only: id_surat_jalan adalah reference ke Quantum (bukan FK)
     */
    public function suratJalans(): HasMany
    {
        return $this->hasMany(PengajuanSewaSuratJalan::class, 'id_pengajuan_sewa', 'id_pengajuan_sewa');
    }

    /**
     * Relasi ke Transfer Antar Cabang (junction table)
     * Reference-Only: id_to_acb adalah reference ke ERP (bukan FK)
     */
    public function transferAntarCabang(): HasMany
    {
        return $this->hasMany(PengajuanSewaToAcb::class, 'id_pengajuan_sewa', 'id_pengajuan_sewa');
    }

    /**
     * Relasi ke Vendor (Perusahaan Ekspedisi) untuk Kiriman Rutin
     */
    public function perusahaanEkspedisi(): BelongsTo
    {
        return $this->belongsTo(PerusahaanEkspedisi::class, 'id_perusahaan_ekspedisi', 'id_perusahaan');
    }

    /**
     * Relasi ke Detail Kiriman Rutin (line items)
     */
    public function detailKirimanRutin(): HasMany
    {
        return $this->hasMany(DetailKirimanRutin::class, 'id_pengajuan_sewa', 'id_pengajuan_sewa');
    }

    public function hitungRasio()
    {
        if (!$this->value_muatan || $this->value_muatan == 0) {
            return null;
        }
        $totalBiaya = $this->harga_sewa + $this->biayaTambahan()->sum('jumlah');
        return round(($totalBiaya / $this->value_muatan) * 100, 2);
    }

    public function tentukanKategoriApproval()
    {
        $rasio = $this->hitungRasio();
        $rasioMaks = RasioSewa::aktif()?->persentase_maksimal ?? 2.5;

        if ($rasio > $rasioMaks) {
            return 'over_threshold';
        }
        if ($this->tujuan_penyewaan === 'PAC') {
            return 'over_threshold';
        }
        // TODO: BLOCKING — area baru check (apakah id_skill sudah di Cabang_Skill)

        return 'normal';
    }
}
