<?php

declare(strict_types=1);

namespace JuanAn6\Redsys\Support;

use JuanAn6\Redsys\Exception\InvalidParameterException;

/**
 * Número de pedido (Ds_Merchant_Order): entre 4 y 12 caracteres alfanuméricos
 * y los 4 primeros obligatoriamente numéricos. Debe ser único por comercio y terminal.
 */
final class OrderNumber
{
    private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public static function isValid(string $order): bool
    {
        return (bool) preg_match('/^\d{4}[0-9A-Za-z]{0,8}$/', $order);
    }

    public static function validate(string $order): string
    {
        if (!self::isValid($order)) {
            throw new InvalidParameterException(
                "Número de pedido no válido: '{$order}'. Debe tener 4-12 caracteres alfanuméricos y empezar por 4 dígitos."
            );
        }

        return $order;
    }

    /**
     * Genera un número de pedido único y válido de 12 caracteres.
     * Los 4 primeros dígitos salen del timestamp; los 8 siguientes son aleatorios.
     */
    public static function generate(): string
    {
        $order = str_pad((string) (time() % 10000), 4, '0', STR_PAD_LEFT);
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < 8; $i++) {
            $order .= self::ALPHABET[random_int(0, $max)];
        }

        return $order;
    }

    /**
     * Convierte un id interno (p. ej. el id autoincremental del pedido) en un número de pedido válido,
     * rellenando con ceros por la izquierda: 42 → "000000000042".
     */
    public static function fromId(int|string $id, int $length = 12): string
    {
        $id = (string) $id;
        if ($length < 4 || $length > 12) {
            throw new InvalidParameterException('La longitud del pedido debe estar entre 4 y 12.');
        }

        return self::validate(str_pad($id, $length, '0', STR_PAD_LEFT));
    }
}
