<?php

namespace App\Models;

use App\Models\Concerns\HasFlag;
use App\Models\Concerns\HasStatusPersetujuan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Usulan perubahan harga master (KG → validasi WM → approval WH), terpisah dari
 * pengajuan sewa. Target tarif = perusahaan + skill + cabang (+ jenis barang untuk
 * Kiriman Rutin). Satu tarif hanya boleh punya satu usulan yang sedang berjalan.
 */
class UsulanHarga extends Model
{
    use HasFlag;
    use HasStatusPersetujuan;

    public const JENIS_SEWA_TRUK = 'sewa_truk';

    public const JENIS_KIRIMAN_RUTIN = 'pengiriman_rutin';

    public const SUMBER_PENGAJUAN = 'pengajuan';

    public const SUMBER_PERUSAHAAN = 'perusahaan';

    protected $connection = 'sqlsrv';

    protected $table = 'sesi_usulan_harga';

    protected $primaryKey = 'id_usulan_harga';

    protected $fillable = [
        'jenis', 'id_perusahaan', 'id_skill', 'cabang_code', 'id_jenis_barang',
        'id_vendor_skill', 'id_tarif', 'harga_lama', 'harga_usulan', 'sumber', 'catatan',
        'status', 'id_cabang_pengaju', 'submitted_by', 'submitted_at', 'alasan_penolakan', 'flag',
    ];

    protected $casts = [
        'harga_lama' => 'decimal:2',
        'harga_usulan' => 'decimal:2',
        'submitted_at' => 'datetime',
    ];

    public function kolomStatusPersetujuan(): string
    {
        return 'status';
    }

    /**
     * Keputusan saat pengajuan baru ingin mengusulkan harga untuk tarif yang sama
     * dengan usulan yang sedang berjalan:
     * - baru  : belum ada usulan berjalan → buat usulan baru
     * - ikut  : harga sama dengan usulan berjalan → pengajuan memakai usulan itu
     * - tolak : harga berbeda → ditolak sampai usulan berjalan diputuskan
     */
    public static function putusanBentrok(?float $hargaBerjalan, float $hargaBaru): string
    {
        if ($hargaBerjalan === null) {
            return 'baru';
        }

        return round($hargaBerjalan, 2) === round($hargaBaru, 2) ? 'ikut' : 'tolak';
    }

    public function scopeUntukTarif(Builder $query, string $jenis, int $idPerusahaan, int $idSkill, string $cabang, ?int $idJenisBarang = null): Builder
    {
        return $query->where('jenis', $jenis)
            ->where('id_perusahaan', $idPerusahaan)
            ->where('id_skill', $idSkill)
            ->where('cabang_code', $cabang)
            ->when(
                $jenis === self::JENIS_KIRIMAN_RUTIN,
                fn ($q) => $q->where('id_jenis_barang', $idJenisBarang),
            );
    }

    public function perusahaan(): BelongsTo
    {
        return $this->belongsTo(PerusahaanEkspedisi::class, 'id_perusahaan', 'id_perusahaan');
    }

    public function vendorSkill(): BelongsTo
    {
        return $this->belongsTo(PerusahaanSkill::class, 'id_vendor_skill', 'id_vendor_skill');
    }

    public function tarif(): BelongsTo
    {
        return $this->belongsTo(TarifKirimanRutin::class, 'id_tarif', 'id_tarif');
    }

    public function jenisBarang(): BelongsTo
    {
        return $this->belongsTo(JenisBarangKiriman::class, 'id_jenis_barang', 'id_jenis_barang');
    }

    /** Tanpa FK ke lntrn_users (tabel eksternal IT). */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by', 'id');
    }

    /** Pengajuan Sewa Truk yang ikut mengusulkan harga ini. */
    public function pengajuanSewa(): HasMany
    {
        return $this->hasMany(PengajuanSewa::class, 'id_usulan_harga', 'id_usulan_harga');
    }

    /** Baris Kiriman Rutin yang ikut mengusulkan harga ini. */
    public function detailKirimanRutin(): HasMany
    {
        return $this->hasMany(DetailKirimanRutin::class, 'id_usulan_harga', 'id_usulan_harga');
    }

    /** Keputusan WM/WH atas usulan ini. */
    public function approvalLogs(): HasMany
    {
        return $this->hasMany(ApprovalLog::class, 'id_usulan_harga', 'id_usulan_harga')->orderBy('decided_at');
    }
}
