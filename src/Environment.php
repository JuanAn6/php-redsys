<?php

declare(strict_types=1);

namespace JuanAn6\Redsys;

use JuanAn6\Redsys\Exception\InvalidParameterException;

enum Environment: string
{
    case Test = 'test';
    case Production = 'production';

    public function redirectUrl(): string
    {
        return match ($this) {
            self::Test => 'https://sis-t.redsys.es:25443/sis/realizarPago',
            self::Production => 'https://sis.redsys.es/sis/realizarPago',
        };
    }

    public static function fromString(string|self $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return match (strtolower(trim($value))) {
            'test', 'testing', 'sandbox', 'pruebas', 'dev', 'local' => self::Test,
            'production', 'prod', 'live', 'real', 'produccion', 'producción' => self::Production,
            default => throw new InvalidParameterException("Entorno de Redsys desconocido: '{$value}'. Usa 'test' o 'production'."),
        };
    }
}
