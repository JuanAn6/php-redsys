<?php

declare(strict_types=1);

namespace JuanAn6\Redsys\Tests;

use JuanAn6\Redsys\Crypto\Signature;
use JuanAn6\Redsys\Exception\InvalidParameterException;

final class SignatureTest extends TestCase
{
    /** Vector calculado de forma independiente con la CLI de openssl (3DES-CBC + HMAC-SHA256). */
    public function testMatchesIndependentOpensslVector(): void
    {
        $params = 'eyJEU19NRVJDSEFOVF9BTU9VTlQiOiIxNDUiLCJEU19NRVJDSEFOVF9PUkRFUiI6IjE0NDYwNjg1ODEifQ==';

        self::assertSame(
            'VjiPPOIr3gWAfPu6F1jVicvKXAbnjqMWcviI4hsocgg=',
            (new Signature(self::KEY))->sign($params, '1446068581'),
        );
    }

    public function testVerifyAcceptsStandardAndUrlSafeBase64(): void
    {
        $signer = new Signature(self::KEY);
        $params = base64_encode('{"Ds_Order":"1234"}');
        $signature = $signer->sign($params, '1234');

        self::assertTrue($signer->verify($params, '1234', $signature));
        self::assertTrue($signer->verify($params, '1234', strtr($signature, '+/', '-_')));
        self::assertTrue($signer->verify($params, '1234', rtrim($signature, '=')));
    }

    public function testVerifyRejectsTampering(): void
    {
        $signer = new Signature(self::KEY);
        $params = base64_encode('{"Ds_Order":"1234"}');
        $signature = $signer->sign($params, '1234');

        self::assertFalse($signer->verify($params, '1235', $signature));
        self::assertFalse($signer->verify($params . 'x', '1234', $signature));
        self::assertFalse((new Signature(base64_encode(str_repeat('k', 24))))->verify($params, '1234', $signature));
    }

    public function testOrderLengthMultipleOfEightIsNotOverPadded(): void
    {
        $signer = new Signature(self::KEY);
        // 8 y 12 caracteres deben dar firmas distintas y estables
        self::assertNotSame($signer->sign('x', '12345678'), $signer->sign('x', '123456780000'));
        self::assertSame($signer->sign('x', '12345678'), $signer->sign('x', '12345678'));
    }

    public function testInvalidKeyThrows(): void
    {
        $this->expectException(InvalidParameterException::class);
        new Signature('***no-base64***');
    }
}
