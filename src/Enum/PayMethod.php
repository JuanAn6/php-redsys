<?php

declare(strict_types=1);

namespace JuanAn6\Redsys\Enum;

/** Métodos de pago (Ds_Merchant_PayMethods). Deben estar activados en el comercio. */
enum PayMethod: string
{
    case Card = 'C';
    case Bizum = 'z';
    case PayPal = 'P';
    case Transfer = 'R';
    case DirectDebit = 'D';
    case PayGold = 'p';
}
