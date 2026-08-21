<?php

namespace App\Helpers;

class FormatHelper
{
    /**
     * Format angka desimal, trim trailing zero
     * Contoh: 2.00 -> "2", 2.50 -> "2.5", 2.75 -> "2.75"
     */
    public static function trimDecimal($value, int $maxDecimals = 2): string
    {
        $formatted = number_format((float) $value, $maxDecimals, '.', '');
        if (str_contains($formatted, '.')) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }
        return $formatted;
    }

    /**
     * Format muatan (ton) dengan trim decimal
     */
    public static function ton($value): string
    {
        return self::trimDecimal($value) . ' Ton';
    }
}
