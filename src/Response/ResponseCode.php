<?php

declare(strict_types=1);

namespace JuanAn6\Redsys\Response;

/** Traducción de los códigos Ds_Response de Redsys. */
final class ResponseCode
{
    /** @var array<int, string> */
    private const MESSAGES = [
        101 => 'Tarjeta caducada',
        102 => 'Tarjeta en excepción transitoria o bajo sospecha de fraude',
        104 => 'Operación no permitida para esa tarjeta o terminal',
        106 => 'Intentos de PIN excedidos',
        116 => 'Disponible insuficiente',
        118 => 'Tarjeta no registrada',
        125 => 'Tarjeta no efectiva',
        129 => 'Código de seguridad (CVV2/CVC2) incorrecto',
        167 => 'Contactar con el emisor: sospecha de fraude',
        172 => 'Denegada, no repetir',
        173 => 'Denegada, no repetir sin actualizar los datos de la tarjeta',
        174 => 'Denegada, no repetir antes de 72 horas',
        180 => 'Tarjeta ajena al servicio',
        181 => 'Tarjeta con restricciones de débito o crédito',
        182 => 'Tarjeta con restricciones de débito o crédito',
        184 => 'Error en la autenticación del titular',
        190 => 'Denegación del emisor sin especificar motivo',
        191 => 'Fecha de caducidad errónea',
        195 => 'Requiere autenticación SCA',
        201 => 'Tarjeta caducada',
        202 => 'Tarjeta en excepción transitoria o bajo sospecha de fraude, con retirada de tarjeta',
        904 => 'Comercio no registrado en el FUC',
        909 => 'Error de sistema',
        912 => 'Emisor no disponible',
        913 => 'Pedido repetido',
        944 => 'Sesión incorrecta',
        950 => 'Operación de devolución no permitida',
        9064 => 'Número de posiciones de la tarjeta incorrecto',
        9078 => 'Tipo de operación no permitida para esa tarjeta',
        9093 => 'Tarjeta no existente',
        9094 => 'Rechazo de los servidores internacionales',
        9104 => 'Comercio con titular seguro y titular sin clave de compra segura',
        9218 => 'El comercio no permite operaciones seguras por entrada /operaciones',
        9253 => 'La tarjeta no cumple el check-digit',
        9256 => 'El comercio no puede realizar preautorizaciones',
        9257 => 'La tarjeta no permite preautorizaciones',
        9261 => 'Operación detenida por superar el control de restricciones de entrada al SIS',
        9912 => 'Emisor no disponible',
        9913 => 'Error en la confirmación que el comercio envía al TPV Virtual',
        9914 => 'Confirmación KO del comercio',
        9915 => 'El usuario ha cancelado el pago',
        9928 => 'Anulación de autorización en diferido realizada por el SIS',
        9929 => 'Anulación de autorización en diferido realizada por el comercio',
        9997 => 'Se está procesando otra transacción en el SIS con la misma tarjeta',
        9998 => 'Operación en proceso de solicitud de datos de tarjeta',
        9999 => 'Operación redirigida al emisor para autenticar',
    ];

    public static function message(?string $code): string
    {
        if ($code === null || $code === '' || !ctype_digit($code)) {
            return 'Respuesta sin código de Redsys';
        }

        $n = (int) $code;
        if ($n <= 99) {
            return 'Transacción autorizada para pagos y preautorizaciones';
        }
        if ($n === 900) {
            return 'Transacción autorizada para devoluciones y confirmaciones';
        }
        if ($n === 400) {
            return 'Transacción autorizada para anulaciones';
        }

        return self::MESSAGES[$n] ?? "Transacción denegada (código {$code})";
    }

    public static function isAuthorized(?string $code): bool
    {
        if ($code === null || !ctype_digit($code)) {
            return false;
        }
        $n = (int) $code;

        return $n <= 99 || $n === 900 || $n === 400;
    }
}
