<?php

declare(strict_types=1);

namespace JuanAn6\Redsys\Response;

use JuanAn6\Redsys\Crypto\Signature;
use JuanAn6\Redsys\Enum\TransactionType;
use JuanAn6\Redsys\Exception\InvalidParameterException;
use JuanAn6\Redsys\Exception\InvalidSignatureException;
use JuanAn6\Redsys\Support\Amount;

/**
 * Respuesta de Redsys ya verificada: la notificación online (POST a la URL de notificación)
 * o los parámetros que llegan por GET a las URL OK/KO.
 */
final class Notification
{
    public readonly PaymentStatus $status;

    /** @param array<string, string> $parameters Ds_MerchantParameters decodificado */
    private function __construct(
        private readonly array $parameters,
        public readonly string $signatureVersion,
    ) {
        $this->status = $this->resolveStatus();
    }

    /**
     * Valida la firma y construye la notificación.
     *
     * @param array<string, mixed> $data $_POST, $_GET o $request->all()
     * @throws InvalidSignatureException si la firma no coincide
     * @throws InvalidParameterException si faltan parámetros
     */
    public static function fromRequest(array $data, Signature $signature): self
    {
        $data = array_change_key_case($data, CASE_LOWER);
        $version = (string) ($data['ds_signatureversion'] ?? '');
        $merchantParameters = (string) ($data['ds_merchantparameters'] ?? '');
        $receivedSignature = (string) ($data['ds_signature'] ?? '');

        if ($merchantParameters === '' || $receivedSignature === '') {
            throw new InvalidParameterException('La petición no contiene Ds_MerchantParameters y Ds_Signature: no es una respuesta de Redsys.');
        }
        if ($version !== '' && $version !== Signature::VERSION) {
            throw new InvalidSignatureException("Versión de firma no soportada: '{$version}'.");
        }

        $parameters = self::decodeParameters($merchantParameters);
        $order = $parameters['Ds_Order'] ?? '';
        if ($order === '') {
            throw new InvalidParameterException('La respuesta de Redsys no contiene Ds_Order.');
        }

        if (!$signature->verify($merchantParameters, $order, $receivedSignature)) {
            throw new InvalidSignatureException("Firma de Redsys no válida para el pedido '{$order}'.");
        }

        return new self($parameters, $version !== '' ? $version : Signature::VERSION);
    }

    /** @return array<string, string> */
    private static function decodeParameters(string $merchantParameters): array
    {
        $json = base64_decode(strtr($merchantParameters, '-_', '+/'), true);
        $decoded = $json === false ? null : json_decode($json, true);
        if (!is_array($decoded)) {
            throw new InvalidParameterException('Ds_MerchantParameters no es un base64/JSON válido.');
        }

        $parameters = [];
        foreach ($decoded as $key => $value) {
            if (is_scalar($value) || $value === null) {
                // Redsys envía algunos valores url-encoded (p. ej. Ds_Date = "22%2F10%2F2026").
                $parameters[(string) $key] = urldecode((string) $value);
            }
        }

        return $parameters;
    }

    private function resolveStatus(): PaymentStatus
    {
        $code = $this->getResponseCode();
        if ($code === null || !ctype_digit($code)) {
            return PaymentStatus::Error;
        }

        $n = (int) $code;
        if ($n <= 99) {
            return PaymentStatus::Authorized;
        }
        if ($n === 900) {
            return $this->getTransactionType() === TransactionType::Refund ? PaymentStatus::Refunded : PaymentStatus::Confirmed;
        }
        if ($n === 400) {
            return PaymentStatus::Cancelled;
        }
        if ($n === 9915) {
            return PaymentStatus::CancelledByUser;
        }

        return PaymentStatus::Denied;
    }

    // ---- Estado ---------------------------------------------------------------

    /** true si el cobro/preautorización se ha aceptado. Es lo único que debes comprobar para dar el pedido por pagado. */
    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::Authorized;
    }

    public function isAuthorized(): bool
    {
        return $this->status === PaymentStatus::Authorized;
    }

    public function isSuccessful(): bool
    {
        return $this->status->isSuccessful();
    }

    public function isCancelledByUser(): bool
    {
        return $this->status === PaymentStatus::CancelledByUser;
    }

    public function isDenied(): bool
    {
        return $this->status === PaymentStatus::Denied || $this->status === PaymentStatus::Error;
    }

    /** Código Ds_Response tal cual ("0000", "0190", "9915"…). */
    public function getResponseCode(): ?string
    {
        $code = $this->get('Ds_Response');

        return $code === null ? null : trim($code);
    }

    public function getResponseMessage(): string
    {
        $errorCode = $this->get('Ds_ErrorCode');
        if ($this->status === PaymentStatus::Error && $errorCode !== null && $errorCode !== '') {
            return "Error de Redsys {$errorCode}";
        }

        return ResponseCode::message($this->getResponseCode());
    }

    /** Código de error SIS (SIS0051…), si Redsys lo envía. */
    public function getErrorCode(): ?string
    {
        return $this->get('Ds_ErrorCode');
    }

    // ---- Datos de la operación ------------------------------------------------

    public function getOrder(): string
    {
        return (string) $this->get('Ds_Order');
    }

    /** Importe en céntimos. */
    public function getAmountInCents(): int
    {
        return (int) $this->get('Ds_Amount');
    }

    /** Importe en unidades (12.50). */
    public function getAmount(): float
    {
        return Amount::fromCents($this->getAmountInCents());
    }

    /** Comprueba que el importe cobrado coincide con el del pedido (en unidades, p. ej. 12.50). */
    public function amountEquals(int|float|string $amount): bool
    {
        return $this->getAmountInCents() === Amount::toCents($amount);
    }

    public function getCurrency(): ?string
    {
        return $this->get('Ds_Currency');
    }

    public function getMerchantCode(): ?string
    {
        return $this->get('Ds_MerchantCode');
    }

    public function getTerminal(): ?string
    {
        return $this->get('Ds_Terminal');
    }

    public function getTransactionType(): ?TransactionType
    {
        $type = $this->get('Ds_TransactionType');

        return $type === null ? null : TransactionType::tryFrom($type);
    }

    public function getAuthorisationCode(): ?string
    {
        $code = $this->get('Ds_AuthorisationCode');

        return $code === null || trim($code) === '' ? null : trim($code);
    }

    /** Datos propios enviados con merchantData(). */
    public function getMerchantData(): ?string
    {
        return $this->get('Ds_MerchantData');
    }

    /** Decodifica merchantData() si se envió como JSON. */
    public function getMerchantDataArray(): ?array
    {
        $data = $this->getMerchantData();
        if ($data === null || $data === '') {
            return null;
        }
        $decoded = json_decode($data, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function isSecurePayment(): bool
    {
        return $this->get('Ds_SecurePayment') === '1';
    }

    /** Fecha y hora de la operación según Redsys. */
    public function getDateTime(): ?\DateTimeImmutable
    {
        $date = $this->get('Ds_Date');
        $hour = $this->get('Ds_Hour');
        if ($date === null || $hour === null) {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('d/m/Y H:i', "{$date} {$hour}", new \DateTimeZone('Europe/Madrid'));

        return $dt === false ? null : $dt;
    }

    // ---- Tarjeta / token ------------------------------------------------------

    /** Token de la tarjeta (pagos con requestToken()); úsalo luego con useToken(). */
    public function getIdentifier(): ?string
    {
        return $this->get('Ds_Merchant_Identifier');
    }

    /** Caducidad de la tarjeta tokenizada (AAMM). */
    public function getExpiryDate(): ?string
    {
        return $this->get('Ds_ExpiryDate');
    }

    /** Id de transacción COF de la operación inicial, necesario para pagos MIT posteriores. */
    public function getCofTxnId(): ?string
    {
        return $this->get('Ds_Merchant_Cof_Txnid');
    }

    /** Marca: 1 VISA, 2 Mastercard, 8 AMEX, 9 JCB, 22 UPI, 6 Diners… */
    public function getCardBrand(): ?string
    {
        return $this->get('Ds_Card_Brand');
    }

    /** País emisor (ISO 3166 numérico). */
    public function getCardCountry(): ?string
    {
        return $this->get('Ds_Card_Country');
    }

    /** 'C' crédito o 'D' débito. */
    public function getCardType(): ?string
    {
        return $this->get('Ds_Card_Type');
    }

    /** Últimos dígitos/tarjeta enmascarada, si el comercio lo tiene activado. */
    public function getCardNumber(): ?string
    {
        return $this->get('Ds_Card_Number');
    }

    /** Código del método de pago con el que se procesó la operación, si Redsys lo envía. */
    public function getProcessedPayMethod(): ?string
    {
        return $this->get('Ds_ProcessedPayMethod');
    }

    // ---- Acceso genérico ------------------------------------------------------

    /** Obtiene cualquier parámetro sin distinguir mayúsculas (Ds_Order, ds_order…). */
    public function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, $this->parameters)) {
            return $this->parameters[$key];
        }
        foreach ($this->parameters as $k => $v) {
            if (strcasecmp($k, $key) === 0) {
                return $v;
            }
        }

        return $default;
    }

    /** @return array<string, string> Todos los parámetros decodificados. */
    public function getRaw(): array
    {
        return $this->parameters;
    }

    /** @return array<string, mixed> Resumen útil para logs o para guardar en BD. */
    public function toArray(): array
    {
        return [
            'order' => $this->getOrder(),
            'status' => $this->status->value,
            'paid' => $this->isPaid(),
            'response_code' => $this->getResponseCode(),
            'response_message' => $this->getResponseMessage(),
            'amount' => $this->getAmount(),
            'amount_cents' => $this->getAmountInCents(),
            'currency' => $this->getCurrency(),
            'transaction_type' => $this->getTransactionType()?->value,
            'authorisation_code' => $this->getAuthorisationCode(),
            'identifier' => $this->getIdentifier(),
            'secure_payment' => $this->isSecurePayment(),
            'card_brand' => $this->getCardBrand(),
            'card_country' => $this->getCardCountry(),
            'merchant_data' => $this->getMerchantData(),
            'raw' => $this->parameters,
        ];
    }
}
