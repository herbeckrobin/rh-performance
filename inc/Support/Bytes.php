<?php

declare(strict_types=1);

namespace RhPerformance\Support;

/**
 * Kleine Byte-Helfer: PHP-ini-Größen parsen und menschenlesbar formatieren.
 * Von ServerHealth und MemoryRecorder geteilt, damit die Umrechnung an einer
 * Stelle lebt.
 */
final class Bytes
{
    /**
     * Wandelt eine PHP-ini-Größe (z.B. "256M", "1G") in Bytes. 0 = unbegrenzt/leer.
     */
    public static function fromIni(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return 0;
        }

        $unit = strtolower($value[strlen($value) - 1]);
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => (int) $value,
        };
    }

    /**
     * Formatiert Bytes als MB mit einer Nachkommastelle (z.B. "42.5 MB").
     */
    public static function toMb(int $bytes): string
    {
        return number_format_i18n($bytes / 1024 / 1024, 1) . ' MB';
    }
}
