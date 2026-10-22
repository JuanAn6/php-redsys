<?php

declare(strict_types=1);

namespace JuanAn6\Redsys;

use JuanAn6\Redsys\Enum\Currency;
use JuanAn6\Redsys\Enum\Language;
use JuanAn6\Redsys\Enum\TransactionType;
use JuanAn6\Redsys\Exception\InvalidParameterException;

/** Datos del comercio y valores por defecto de cada pago. */
final class Config
{
    public readonly Environment $environment;
    /** Código ISO 4217 numérico, p. ej. '978'. */
    public readonly string $currency;
    public readonly ?Language $language;

    public function __construct(
        public readonly string $merchantCode,
        public readonly string $terminal,
        public readonly string $secretKey,
        Environment|string $environment = Environment::Test,
        Currency|string|int $currency = Currency::EUR,
        Language|string|null $language = null,
        public readonly ?string $merchantName = null,
        public readonly ?string $notificationUrl = null,
        public readonly ?string $okUrl = null,
        public readonly ?string $koUrl = null,
        public readonly TransactionType $transactionType = TransactionType::Authorization,
    ) {
        if (!preg_match('/^\d{1,9}$/', $merchantCode)) {
            throw new InvalidParameterException('El código de comercio (FUC) debe ser numérico, de hasta 9 dígitos.');
        }
        if (!preg_match('/^\d{1,3}$/', $terminal)) {
            throw new InvalidParameterException('El terminal debe ser numérico, de hasta 3 dígitos.');
        }
        if ($secretKey === '') {
            throw new InvalidParameterException('Falta la clave secreta de Redsys.');
        }

        $this->environment = Environment::fromString($environment);
        $this->currency = self::currencyCode($currency);
        $this->language = $language === null || $language === '' ? null : Language::fromLocale($language);
    }

    /**
     * Crea la configuración desde un array (p. ej. config('redsys')).
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        foreach (['merchant_code', 'terminal', 'secret_key'] as $required) {
            if (!isset($config[$required]) || $config[$required] === '') {
                throw new InvalidParameterException("Falta el parámetro de configuración de Redsys '{$required}'.");
            }
        }

        return new self(
            merchantCode: (string) $config['merchant_code'],
            terminal: (string) $config['terminal'],
            secretKey: (string) $config['secret_key'],
            environment: $config['environment'] ?? Environment::Test,
            currency: $config['currency'] ?? Currency::EUR,
            language: $config['language'] ?? null,
            merchantName: $config['merchant_name'] ?? null,
            notificationUrl: $config['notification_url'] ?? null,
            okUrl: $config['ok_url'] ?? null,
            koUrl: $config['ko_url'] ?? null,
        );
    }

    public static function currencyCode(Currency|string|int $currency): string
    {
        if ($currency instanceof Currency) {
            return $currency->value;
        }

        $value = strtoupper(trim((string) $currency));
        foreach (Currency::cases() as $case) {
            if ($case->name === $value) {
                return $case->value;
            }
        }
        if (ctype_digit($value) && strlen($value) <= 3) {
            return str_pad($value, 3, '0', STR_PAD_LEFT);
        }

        throw new InvalidParameterException("Moneda no válida: '{$currency}'. Usa el código ISO 4217 numérico (978) o alfabético (EUR).");
    }
}
