<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Aksi proses persetujuan vendor/usulan harga ditolak aturan (bukan giliran, beda cabang,
 * usulan bentrok). Pesannya aman ditampilkan ke user; $status = HTTP status yang dipakai controller.
 */
class PersetujuanMasterException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
