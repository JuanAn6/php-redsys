<?php

declare(strict_types=1);

namespace JuanAn6\Redsys\Support;

use JuanAn6\Redsys\Exception\InvalidParameterException;

/** Conversión segura de importes a céntimos (Redsys trabaja con enteros sin decimales). */
final class Amount
{
    /**
     * Convierte un importe en unidades (12.5, "12,50", "1.234,56", 10) a céntimos (1250).
     * Los int se interpretan como euros, no como céntimos.
     */
    public static function toCents(int|float|string $amount): int
    {
        if (is_int($amount)) {
            $cents = $amount * 100;
        } else {
            if (is_float($amount)) {
                if (!is_finite($amount)) {
                    throw new InvalidParameterException('El importe no es un número válido.');
                }
                $value = number_format($amount, 2, '.', '');
            } else {
                $value = self::normalizeString($amount);
            }

            if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $m)) {
                throw new InvalidParameterException("Importe no válido: '{$amount}'. Usa como máximo 2 decimales.");
            }
            $cents = (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
        }

        if ($cents <= 0) {
            throw new InvalidParameterException('El importe debe ser mayor que 0.');
        }

        return $cents;
    }

    public static function fromCents(int $cents): float
    {
        return round($cents / 100, 2);
    }

    public static function format(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.');
    }

    private static function normalizeString(string $value): string
    {
        $value = str_replace([' ', "\u{00A0}", '€'], '', trim($value));

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');

        if ($lastComma !== false && $lastDot !== false) {
            // El separador que aparece último es el decimal: "1.234,56" o "1,234.56".
            $decimal = $lastComma > $lastDot ? ',' : '.';
            $thousands = $decimal === ',' ? '.' : ',';
            $value = str_replace($thousands, '', $value);
            $value = str_replace($decimal, '.', $value);
        } elseif ($lastComma !== false) {
            $value = str_replace(',', '.', $value);
        }

        return $value;
    }
}
