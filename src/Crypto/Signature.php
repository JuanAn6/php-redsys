<?php

declare(strict_types=1);

namespace JuanAn6\Redsys\Crypto;

use JuanAn6\Redsys\Exception\InvalidParameterException;
use JuanAn6\Redsys\Exception\RedsysException;

/** Firma HMAC_SHA256_V1 de Redsys. */
final class Signature
{
    public const VERSION = 'HMAC_SHA256_V1';

    private readonly string $key;

    public function __construct(string $secretKey)
    {
        $key = base64_decode($secretKey, true);
        if ($key === false || $key === '') {
            throw new InvalidParameterException('La clave secreta de Redsys (clave SHA-256) no es un base64 válido.');
        }
        $this->key = $key;
    }

    /** Firma un Ds_MerchantParameters ya codificado en base64. Devuelve base64 estándar. */
    public function sign(string $merchantParameters, string $order): string
    {
        $hmac = hash_hmac('sha256', $merchantParameters, $this->diversifyKey($order), true);

        return base64_encode($hmac);
    }

    /** Compara una firma recibida (base64 normal o URL-safe) con la esperada, en tiempo constante. */
    public function verify(string $merchantParameters, string $order, string $receivedSignature): bool
    {
        $expected = self::normalize($this->sign($merchantParameters, $order));

        return hash_equals($expected, self::normalize($receivedSignature));
    }

    /** Clave diversificada: 3DES-CBC del número de pedido con IV a ceros y relleno con \0. */
    private function diversifyKey(string $order): string
    {
        $length = (int) ceil(strlen($order) / 8) * 8;
        $data = str_pad($order, max($length, 8), "\0");

        $encrypted = openssl_encrypt($data, 'des-ede3-cbc', $this->key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, "\0\0\0\0\0\0\0\0");
        if ($encrypted === false) {
            throw new RedsysException('No se pudo calcular la clave 3DES (¿OpenSSL sin soporte des-ede3-cbc?).');
        }

        return $encrypted;
    }

    private static function normalize(string $signature): string
    {
        return rtrim(strtr(trim($signature), '-_', '+/'), '=');
    }
}
