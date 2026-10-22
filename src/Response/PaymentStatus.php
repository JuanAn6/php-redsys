<?php

declare(strict_types=1);

namespace JuanAn6\Redsys\Response;

enum PaymentStatus: string
{
    /** Pago o preautorización aceptados (Ds_Response 0000-0099). */
    case Authorized = 'authorized';
    /** Devolución aceptada (Ds_Response 0900 con TransactionType 3). */
    case Refunded = 'refunded';
    /** Confirmación de preautorización aceptada (Ds_Response 0900 con TransactionType 2/P). */
    case Confirmed = 'confirmed';
    /** Anulación aceptada (Ds_Response 0400). */
    case Cancelled = 'cancelled';
    /** El cliente canceló el pago en el TPV (Ds_Response 9915). */
    case CancelledByUser = 'cancelled_by_user';
    /** Operación denegada por el emisor o por Redsys. */
    case Denied = 'denied';
    /** Respuesta sin Ds_Response o con un error SIS. */
    case Error = 'error';

    public function isSuccessful(): bool
    {
        return in_array($this, [self::Authorized, self::Refunded, self::Confirmed, self::Cancelled], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Authorized => 'Autorizado',
            self::Refunded => 'Devuelto',
            self::Confirmed => 'Confirmado',
            self::Cancelled => 'Anulado',
            self::CancelledByUser => 'Cancelado por el cliente',
            self::Denied => 'Denegado',
            self::Error => 'Error',
        };
    }
}
