<?php

declare(strict_types=1);

namespace JuanAn6\Redsys\Request;

use JuanAn6\Redsys\Config;
use JuanAn6\Redsys\Crypto\Signature;
use JuanAn6\Redsys\Enum\Currency;
use JuanAn6\Redsys\Enum\Language;
use JuanAn6\Redsys\Enum\PayMethod;
use JuanAn6\Redsys\Enum\TransactionType;
use JuanAn6\Redsys\Exception\InvalidParameterException;
use JuanAn6\Redsys\Support\Amount;
use JuanAn6\Redsys\Support\OrderNumber;

/** Constructor fluido de una petición de pago por Redirección. */
final class PaymentRequest
{
    /** @var array<string, string> */
    private array $params = [];
    private ?int $amountInCents = null;
    private ?string $order = null;

    public function __construct(
        private readonly Config $config,
        private readonly Signature $signature,
    ) {
        $this->transactionType($config->transactionType);
        $this->params['DS_MERCHANT_CURRENCY'] = $config->currency;
        if ($config->language !== null) {
            $this->language($config->language);
        }
        if ($config->merchantName !== null && $config->merchantName !== '') {
            $this->merchantName($config->merchantName);
        }
        if ($config->notificationUrl) {
            $this->notificationUrl($config->notificationUrl);
        }
        if ($config->okUrl) {
            $this->okUrl($config->okUrl);
        }
        if ($config->koUrl) {
            $this->koUrl($config->koUrl);
        }
    }

    // ---- Datos básicos --------------------------------------------------------

    /** Importe en unidades: 12.5, "12,50", 10 (= 10,00 €). */
    public function amount(int|float|string $amount): self
    {
        $this->amountInCents = Amount::toCents($amount);

        return $this;
    }

    /** Importe en céntimos: 1250 (= 12,50 €). */
    public function amountInCents(int $cents): self
    {
        if ($cents <= 0) {
            throw new InvalidParameterException('El importe debe ser mayor que 0.');
        }
        $this->amountInCents = $cents;

        return $this;
    }

    /** Número de pedido (4-12 caracteres alfanuméricos, los 4 primeros dígitos). Si no se indica, se genera uno. */
    public function order(string|int $order): self
    {
        $this->order = OrderNumber::validate((string) $order);

        return $this;
    }

    public function description(string $description): self
    {
        return $this->set('DS_MERCHANT_PRODUCTDESCRIPTION', self::limit($description, 125));
    }

    /** Nombre del titular/comprador que se muestra en el TPV. */
    public function holder(string $name): self
    {
        return $this->set('DS_MERCHANT_TITULAR', self::limit($name, 60));
    }

    public function merchantName(string $name): self
    {
        return $this->set('DS_MERCHANT_MERCHANTNAME', self::limit($name, 25));
    }

    public function currency(Currency|string|int $currency): self
    {
        return $this->set('DS_MERCHANT_CURRENCY', Config::currencyCode($currency));
    }

    /** Idioma del TPV: Language::English, 'en', 'ca_ES'… */
    public function language(Language|string $language): self
    {
        return $this->set('DS_MERCHANT_CONSUMERLANGUAGE', Language::fromLocale($language)->value);
    }

    /**
     * Datos propios que Redsys devolverá en la notificación (máx. 1024 caracteres).
     * Si es un array se envía como JSON y se recupera con getMerchantDataArray().
     */
    public function merchantData(string|array $data): self
    {
        if (is_array($data)) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        if (strlen($data) > 1024) {
            throw new InvalidParameterException('merchantData no puede superar 1024 caracteres.');
        }

        return $this->set('DS_MERCHANT_MERCHANTDATA', $data);
    }

    // ---- URLs -----------------------------------------------------------------

    /** URL a la que Redsys hace el POST servidor-a-servidor con el resultado (la fuente fiable). */
    public function notificationUrl(string $url): self
    {
        return $this->set('DS_MERCHANT_MERCHANTURL', $url);
    }

    /** URL a la que vuelve el cliente si el pago se acepta. */
    public function okUrl(string $url): self
    {
        return $this->set('DS_MERCHANT_URLOK', $url);
    }

    /** URL a la que vuelve el cliente si el pago falla o lo cancela. */
    public function koUrl(string $url): self
    {
        return $this->set('DS_MERCHANT_URLKO', $url);
    }

    // ---- Tipo de operación ----------------------------------------------------

    public function transactionType(TransactionType|string $type): self
    {
        return $this->set('DS_MERCHANT_TRANSACTIONTYPE', $type instanceof TransactionType ? $type->value : $type);
    }

    /** Pago normal (tipo 0). Es el valor por defecto. */
    public function authorization(): self
    {
        return $this->transactionType(TransactionType::Authorization);
    }

    /** Preautorización (tipo 1): retiene el importe para confirmarlo después. */
    public function preauthorization(): self
    {
        return $this->transactionType(TransactionType::Preauthorization);
    }

    /** Autenticación (tipo 7). */
    public function authentication(): self
    {
        return $this->transactionType(TransactionType::Authentication);
    }

    // ---- Métodos de pago ------------------------------------------------------

    /** Limita el TPV a uno o varios métodos: payMethods(PayMethod::Bizum) o payMethods('z'). */
    public function payMethods(PayMethod|string ...$methods): self
    {
        $value = implode('', array_map(static fn (PayMethod|string $m) => $m instanceof PayMethod ? $m->value : $m, $methods));

        return $this->set('DS_MERCHANT_PAYMETHODS', $value);
    }

    /** Pago solo con Bizum. */
    public function bizum(): self
    {
        return $this->payMethods(PayMethod::Bizum);
    }

    /** Pago solo con tarjeta. */
    public function card(): self
    {
        return $this->payMethods(PayMethod::Card);
    }

    // ---- Tokenización / pagos recurrentes (COF) --------------------------------

    /**
     * Pide a Redsys que guarde la tarjeta y devuelva un token (Ds_Merchant_Identifier) en la notificación.
     * $cofType: 'R' recurrente, 'I' cuotas, 'C' otros/one-click, 'H' reauth, 'E' resubmission, 'D' delayed, 'M' incremental, 'N' no show.
     */
    public function requestToken(?string $cofType = null): self
    {
        $this->set('DS_MERCHANT_IDENTIFIER', 'REQUIRED');
        if ($cofType !== null) {
            $this->set('DS_MERCHANT_COF_INI', 'S');
            $this->set('DS_MERCHANT_COF_TYPE', $cofType);
        }

        return $this;
    }

    /**
     * Paga con un token guardado. Con $directPayment = true no se muestran pantallas al cliente
     * (requiere que el comercio lo tenga activado).
     */
    public function useToken(string $identifier, bool $directPayment = false, ?string $cofTxnId = null): self
    {
        $this->set('DS_MERCHANT_IDENTIFIER', $identifier);
        if ($directPayment) {
            $this->set('DS_MERCHANT_DIRECTPAYMENT', 'true');
        }
        if ($cofTxnId !== null) {
            $this->set('DS_MERCHANT_COF_TXNID', $cofTxnId);
        }

        return $this;
    }

    /** Exención SCA: 'LWV' bajo importe, 'TRA' análisis de riesgo, 'MIT' iniciada por el comercio, 'COR' corporativa, 'ATD' delegada. */
    public function scaExemption(string $exemption): self
    {
        return $this->set('DS_MERCHANT_EXCEP_SCA', $exemption);
    }

    // ---- Genérico -------------------------------------------------------------

    /** Añade cualquier parámetro Ds_Merchant_* no cubierto por los métodos anteriores. */
    public function set(string $key, string|int|bool|null $value): self
    {
        $key = strtoupper($key);
        if (!str_starts_with($key, 'DS_MERCHANT_')) {
            $key = 'DS_MERCHANT_' . $key;
        }
        if ($value === null) {
            unset($this->params[$key]);
        } else {
            $this->params[$key] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        return $this;
    }

    public function getOrder(): string
    {
        return $this->order ??= OrderNumber::generate();
    }

    // ---- Resultado ------------------------------------------------------------

    /** @return array<string, string> Parámetros finales (sin codificar). */
    public function toParameters(): array
    {
        if ($this->amountInCents === null) {
            throw new InvalidParameterException('Falta el importe: usa amount() o amountInCents().');
        }

        return [
            'DS_MERCHANT_AMOUNT' => (string) $this->amountInCents,
            'DS_MERCHANT_ORDER' => $this->getOrder(),
            'DS_MERCHANT_MERCHANTCODE' => $this->config->merchantCode,
            'DS_MERCHANT_TERMINAL' => $this->config->terminal,
        ] + $this->params;
    }

    /** Firma la petición y devuelve el formulario para redirigir al TPV. */
    public function form(): RedirectForm
    {
        $parameters = $this->toParameters();
        $encoded = base64_encode(json_encode($parameters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return new RedirectForm(
            action: $this->config->environment->redirectUrl(),
            signatureVersion: Signature::VERSION,
            merchantParameters: $encoded,
            signature: $this->signature->sign($encoded, $parameters['DS_MERCHANT_ORDER']),
            parameters: $parameters,
        );
    }

    /** Atajo: HTML con autoenvío al TPV. */
    public function toHtml(bool $autoSubmit = true): string
    {
        return $this->form()->toHtml($autoSubmit);
    }

    private static function limit(string $value, int $max): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }
}
