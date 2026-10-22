<?php

declare(strict_types=1);

namespace JuanAn6\Redsys\Tests;

use JuanAn6\Redsys\Config;
use JuanAn6\Redsys\Crypto\Signature;
use JuanAn6\Redsys\Environment;
use JuanAn6\Redsys\Redsys;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected const KEY = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7';

    protected function redsys(array $overrides = []): Redsys
    {
        return Redsys::fromArray($overrides + [
            'merchant_code' => '999008881',
            'terminal' => '1',
            'secret_key' => self::KEY,
            'environment' => Environment::Test,
        ]);
    }

    /** Simula el POST que Redsys envía a la URL de notificación. */
    protected function fakeNotification(array $params, bool $urlSafe = true): array
    {
        $params += [
            'Ds_Date' => '22%2F10%2F2026',
            'Ds_Hour' => '20%3A15',
            'Ds_Amount' => '1250',
            'Ds_Currency' => '978',
            'Ds_Order' => '1234ABCD',
            'Ds_MerchantCode' => '999008881',
            'Ds_Terminal' => '1',
            'Ds_Response' => '0000',
            'Ds_TransactionType' => '0',
            'Ds_SecurePayment' => '1',
            'Ds_AuthorisationCode' => '123456',
            'Ds_Card_Country' => '724',
            'Ds_Card_Brand' => '1',
            'Ds_ConsumerLanguage' => '1',
        ];
        $encoded = base64_encode(json_encode($params));
        $signature = (new Signature(self::KEY))->sign($encoded, $params['Ds_Order']);
        if ($urlSafe) {
            $encoded = strtr($encoded, '+/', '-_');
            $signature = strtr($signature, '+/', '-_');
        }

        return [
            'Ds_SignatureVersion' => 'HMAC_SHA256_V1',
            'Ds_MerchantParameters' => $encoded,
            'Ds_Signature' => $signature,
        ];
    }
}
