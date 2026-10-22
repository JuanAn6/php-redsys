<?php

declare(strict_types=1);

namespace JuanAn6\Redsys;

use JuanAn6\Redsys\Crypto\Signature;
use JuanAn6\Redsys\Request\PaymentRequest;
use JuanAn6\Redsys\Response\Notification;

/** Punto de entrada de la librería. */
final class Redsys
{
    private readonly Signature $signature;

    public function __construct(private readonly Config $config)
    {
        $this->signature = new Signature($config->secretKey);
    }

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        return new self(Config::fromArray($config));
    }

    /** Empieza un pago nuevo. Termina con ->form() o ->toHtml(). */
    public function createPayment(): PaymentRequest
    {
        return new PaymentRequest($this->config, $this->signature);
    }

    /**
     * Valida la notificación online (POST de Redsys a la URL de notificación).
     *
     * @param array<string, mixed>|null $data por defecto $_POST
     * @throws Exception\InvalidSignatureException
     * @throws Exception\InvalidParameterException
     */
    public function handleNotification(?array $data = null): Notification
    {
        return Notification::fromRequest($data ?? $_POST, $this->signature);
    }

    /**
     * Valida los parámetros con los que vuelve el cliente a las URL OK/KO.
     * Sirve para mostrar un mensaje, pero el estado fiable es el de la notificación.
     *
     * @param array<string, mixed>|null $data por defecto $_GET
     */
    public function handleReturn(?array $data = null): Notification
    {
        return Notification::fromRequest($data ?? $_GET, $this->signature);
    }

    /** Como handleNotification(), pero devuelve null en vez de lanzar si la petición no es válida. */
    public function tryHandleNotification(?array $data = null): ?Notification
    {
        try {
            return $this->handleNotification($data);
        } catch (Exception\RedsysException) {
            return null;
        }
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getSignature(): Signature
    {
        return $this->signature;
    }
}
