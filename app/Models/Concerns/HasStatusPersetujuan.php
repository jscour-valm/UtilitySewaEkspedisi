<?php

namespace App\Models\Concerns;

/**
 * Status proses persetujuan master (vendor baru, usulan harga): KG mengajukan →
 * WM memvalidasi → WH menyetujui. Penolakan oleh WM atau WH langsung final.
 * Model pemakai menentukan nama kolom status lewat kolomStatusPersetujuan().
 */
trait HasStatusPersetujuan
{
    public const STATUS_MENUNGGU_VALIDASI = 'menunggu_validasi';

    public const STATUS_MENUNGGU_APPROVAL = 'menunggu_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    abstract public function kolomStatusPersetujuan(): string;

    public function statusPersetujuan(): ?string
    {
        return $this->getAttribute($this->kolomStatusPersetujuan());
    }

    /** Peran yang sedang giliran memutuskan (WM / WH), null kalau sudah final. */
    public function giliranPersetujuan(): ?string
    {
        return self::giliranDari($this->statusPersetujuan());
    }

    public static function giliranDari(?string $status): ?string
    {
        return match ($status) {
            self::STATUS_MENUNGGU_VALIDASI => 'WM',
            self::STATUS_MENUNGGU_APPROVAL => 'WH',
            default => null,
        };
    }

    /** Belum diputuskan final (masih menunggu WM atau WH). */
    public function sedangBerjalan(): bool
    {
        return $this->giliranPersetujuan() !== null;
    }

    public function sudahDisetujui(): bool
    {
        return $this->statusPersetujuan() === self::STATUS_APPROVED;
    }

    public function labelStatusPersetujuan(): string
    {
        return match ($this->statusPersetujuan()) {
            self::STATUS_MENUNGGU_VALIDASI => 'Menunggu Validasi WM',
            self::STATUS_MENUNGGU_APPROVAL => 'Menunggu Approval WH',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_REJECTED => 'Ditolak',
            default => '-',
        };
    }

    public function scopeSedangBerjalan($query)
    {
        return $query->whereIn($this->kolomStatusPersetujuan(), [self::STATUS_MENUNGGU_VALIDASI, self::STATUS_MENUNGGU_APPROVAL]);
    }
}
