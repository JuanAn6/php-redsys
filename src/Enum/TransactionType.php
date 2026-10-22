<?php

declare(strict_types=1);

namespace JuanAn6\Redsys\Enum;

/**
 * Tipos de operación (Ds_Merchant_TransactionType).
 * En modo Redirección se usan normalmente Authorization, Preauthorization y Authentication;
 * el resto pueden llegar en notificaciones de operaciones hechas desde el portal.
 */
enum TransactionType: string
{
    case Authorization = '0';
    case Preauthorization = '1';
    case Confirmation = '2';
    case Refund = '3';
    case Authentication = '7';
    case Cancellation = '9';
    case DeferredPreauthorization = 'O';
    case DeferredConfirmation = 'P';
    case DeferredCancellation = 'Q';
    case InitialRecurring = 'L';
    case SuccessiveRecurring = 'M';

    public function label(): string
    {
        return match ($this) {
            self::Authorization => 'Autorización',
            self::Preauthorization => 'Preautorización',
            self::Confirmation => 'Confirmación de preautorización',
            self::Refund => 'Devolución',
            self::Authentication => 'Autenticación',
            self::Cancellation => 'Anulación',
            self::DeferredPreauthorization => 'Preautorización diferida',
            self::DeferredConfirmation => 'Confirmación de preautorización diferida',
            self::DeferredCancellation => 'Anulación de preautorización diferida',
            self::InitialRecurring => 'Cuota inicial recurrente',
            self::SuccessiveRecurring => 'Cuota sucesiva recurrente',
        };
    }
}
