<?php

declare(strict_types=1);

namespace JuanAn6\Redsys\Enum;

/** Códigos ISO 4217 numéricos más habituales (Ds_Merchant_Currency). */
enum Currency: string
{
    case EUR = '978';
    case USD = '840';
    case GBP = '826';
    case CHF = '756';
    case JPY = '392';
    case CNY = '156';
    case MXN = '484';
    case ARS = '032';
    case CAD = '124';
    case AUD = '036';
    case SEK = '752';
    case NOK = '578';
    case DKK = '208';
    case PLN = '985';
}
