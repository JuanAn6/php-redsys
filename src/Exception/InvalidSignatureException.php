<?php

declare(strict_types=1);

namespace JuanAn6\Redsys\Exception;

/**
 * La firma recibida no coincide con la calculada: la respuesta no procede de Redsys
 * o ha sido manipulada. Nunca debe darse por bueno el pago.
 */
class InvalidSignatureException extends RedsysException
{
}
