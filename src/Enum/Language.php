<?php

declare(strict_types=1);

namespace JuanAn6\Redsys\Enum;

use JuanAn6\Redsys\Exception\InvalidParameterException;

/** Idioma del TPV (Ds_Merchant_ConsumerLanguage). Auto = '0' deja que Redsys use el del navegador. */
enum Language: string
{
    case Auto = '0';
    case Spanish = '001';
    case English = '002';
    case Catalan = '003';
    case French = '004';
    case German = '005';
    case Dutch = '006';
    case Italian = '007';
    case Swedish = '008';
    case Portuguese = '009';
    case Valencian = '010';
    case Polish = '011';
    case Galician = '012';
    case Basque = '013';

    /** Acepta un código ISO 639-1 ('es', 'en', 'ca'…), un locale ('es_ES') o el código Redsys ('001'). */
    public static function fromLocale(string|self $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        $found = self::tryFrom($value);
        if ($found !== null) {
            return $found;
        }

        return match (strtolower(substr($value, 0, 2))) {
            'es' => self::Spanish,
            'en' => self::English,
            'ca' => self::Catalan,
            'fr' => self::French,
            'de' => self::German,
            'nl' => self::Dutch,
            'it' => self::Italian,
            'sv' => self::Swedish,
            'pt' => self::Portuguese,
            'va' => self::Valencian,
            'pl' => self::Polish,
            'gl' => self::Galician,
            'eu' => self::Basque,
            default => throw new InvalidParameterException("Idioma no soportado por Redsys: '{$value}'."),
        };
    }
}
