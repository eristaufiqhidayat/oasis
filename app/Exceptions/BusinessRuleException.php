<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan bisnis (stok tidak cukup, status muatan salah, dst.).
 * Web: kembali ke halaman sebelumnya dengan pesan galat. API: 422 {"error": {"code", "message"}}.
 */
class BusinessRuleException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode = 'business_rule', public readonly ?string $field = null)
    {
        parent::__construct($message);
    }
}
