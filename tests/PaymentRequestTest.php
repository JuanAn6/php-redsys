<?php

declare(strict_types=1);

namespace JuanAn6\Redsys\Tests;

use JuanAn6\Redsys\Enum\Language;
use JuanAn6\Redsys\Enum\PayMethod;
use JuanAn6\Redsys\Environment;
use JuanAn6\Redsys\Exception\InvalidParameterException;
use JuanAn6\Redsys\Redsys;

final class PaymentRequestTest extends TestCase
{
    public function testBuildsSignedForm(): void
    {
        $form = $this->redsys()->createPayment()
            ->amount(12.5)
            ->order('1234ABCD')
            ->description('Pedido 1234')
            ->notificationUrl('https://tienda.test/redsys/notify')
            ->okUrl('https://tienda.test/ok')
            ->koUrl('https://tienda.test/ko')
            ->form();

        self::assertSame('https://sis-t.redsys.es:25443/sis/realizarPago', $form->action);
        self::assertSame('HMAC_SHA256_V1', $form->signatureVersion);

        $decoded = json_decode(base64_decode($form->merchantParameters), true);
        self::assertSame('1250', $decoded['DS_MERCHANT_AMOUNT']);
        self::assertSame('1234ABCD', $decoded['DS_MERCHANT_ORDER']);
        self::assertSame('999008881', $decoded['DS_MERCHANT_MERCHANTCODE']);
        self::assertSame('1', $decoded['DS_MERCHANT_TERMINAL']);
        self::assertSame('978', $decoded['DS_MERCHANT_CURRENCY']);
        self::assertSame('0', $decoded['DS_MERCHANT_TRANSACTIONTYPE']);
        self::assertSame('https://tienda.test/redsys/notify', $decoded['DS_MERCHANT_MERCHANTURL']);

        self::assertTrue($this->redsys()->getSignature()->verify($form->merchantParameters, '1234ABCD', $form->signature));
    }

    public function testProductionUrl(): void
    {
        $form = $this->redsys(['environment' => 'production'])->createPayment()->amount(1)->form();
        self::assertSame('https://sis.redsys.es/sis/realizarPago', $form->action);
    }

    public function testGeneratesOrderWhenMissing(): void
    {
        $request = $this->redsys()->createPayment()->amount(1);
        $params = $request->toParameters();
        self::assertMatchesRegularExpression('/^\d{4}[0-9A-Za-z]{8}$/', $params['DS_MERCHANT_ORDER']);
        self::assertSame($params['DS_MERCHANT_ORDER'], $request->getOrder());
    }

    public function testConfigDefaultsAreApplied(): void
    {
        $params = $this->redsys([
            'notification_url' => 'https://n',
            'ok_url' => 'https://ok',
            'ko_url' => 'https://ko',
            'language' => 'ca',
            'merchant_name' => 'Mi Tienda',
            'currency' => 'USD',
        ])->createPayment()->amount(1)->toParameters();

        self::assertSame('https://n', $params['DS_MERCHANT_MERCHANTURL']);
        self::assertSame('https://ok', $params['DS_MERCHANT_URLOK']);
        self::assertSame('https://ko', $params['DS_MERCHANT_URLKO']);
        self::assertSame('003', $params['DS_MERCHANT_CONSUMERLANGUAGE']);
        self::assertSame('Mi Tienda', $params['DS_MERCHANT_MERCHANTNAME']);
        self::assertSame('840', $params['DS_MERCHANT_CURRENCY']);
    }

    public function testFlows(): void
    {
        $r = $this->redsys();

        self::assertSame('1', $r->createPayment()->amount(1)->preauthorization()->toParameters()['DS_MERCHANT_TRANSACTIONTYPE']);
        self::assertSame('7', $r->createPayment()->amount(1)->authentication()->toParameters()['DS_MERCHANT_TRANSACTIONTYPE']);
        self::assertSame('z', $r->createPayment()->amount(1)->bizum()->toParameters()['DS_MERCHANT_PAYMETHODS']);
        self::assertSame('Cz', $r->createPayment()->amount(1)->payMethods(PayMethod::Card, PayMethod::Bizum)->toParameters()['DS_MERCHANT_PAYMETHODS']);

        $token = $r->createPayment()->amount(1)->requestToken('R')->toParameters();
        self::assertSame('REQUIRED', $token['DS_MERCHANT_IDENTIFIER']);
        self::assertSame('S', $token['DS_MERCHANT_COF_INI']);
        self::assertSame('R', $token['DS_MERCHANT_COF_TYPE']);

        $use = $r->createPayment()->amount(1)->useToken('abc123', true, 'TXN1')->scaExemption('MIT')->toParameters();
        self::assertSame('abc123', $use['DS_MERCHANT_IDENTIFIER']);
        self::assertSame('true', $use['DS_MERCHANT_DIRECTPAYMENT']);
        self::assertSame('TXN1', $use['DS_MERCHANT_COF_TXNID']);
        self::assertSame('MIT', $use['DS_MERCHANT_EXCEP_SCA']);
    }

    public function testMiscSetters(): void
    {
        $params = $this->redsys()->createPayment()
            ->amountInCents(999)
            ->language(Language::English)
            ->holder('Juan Pérez')
            ->merchantData(['order_id' => 42])
            ->set('Ds_Merchant_Group', '123')
            ->set('DS_MERCHANT_GROUP', null)
            ->set('custom', 'x')
            ->toParameters();

        self::assertSame('999', $params['DS_MERCHANT_AMOUNT']);
        self::assertSame('002', $params['DS_MERCHANT_CONSUMERLANGUAGE']);
        self::assertSame('Juan Pérez', $params['DS_MERCHANT_TITULAR']);
        self::assertSame('{"order_id":42}', $params['DS_MERCHANT_MERCHANTDATA']);
        self::assertArrayNotHasKey('DS_MERCHANT_GROUP', $params);
        self::assertSame('x', $params['DS_MERCHANT_CUSTOM']);
    }

    public function testMissingAmountThrows(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->redsys()->createPayment()->form();
    }

    public function testInvalidOrderThrows(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->redsys()->createPayment()->order('AB12');
    }

    public function testHtmlIsEscapedAndAutoSubmits(): void
    {
        $form = $this->redsys()->createPayment()->amount(1)->order('1234')->form();
        $html = $form->toHtml();

        self::assertStringContainsString('action="https://sis-t.redsys.es:25443/sis/realizarPago"', $html);
        self::assertStringContainsString('name="Ds_Signature" value="' . htmlspecialchars($form->signature) . '"', $html);
        self::assertStringContainsString('.submit()', $html);
        self::assertStringNotContainsString('<script>', $form->toHtml(false));
        self::assertSame(['Ds_SignatureVersion', 'Ds_MerchantParameters', 'Ds_Signature'], array_keys($form->fields()));
    }

    public function testInvalidConfigThrows(): void
    {
        $this->expectException(InvalidParameterException::class);
        Redsys::fromArray(['merchant_code' => 'abc', 'terminal' => '1', 'secret_key' => self::KEY]);
    }

    public function testEnvironmentAliases(): void
    {
        self::assertSame(Environment::Production, Environment::fromString('live'));
        self::assertSame(Environment::Test, Environment::fromString('sandbox'));
        $this->expectException(InvalidParameterException::class);
        Environment::fromString('foo');
    }
}
