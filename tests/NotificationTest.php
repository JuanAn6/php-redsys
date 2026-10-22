<?php

declare(strict_types=1);

namespace JuanAn6\Redsys\Tests;

use JuanAn6\Redsys\Enum\TransactionType;
use JuanAn6\Redsys\Exception\InvalidParameterException;
use JuanAn6\Redsys\Exception\InvalidSignatureException;
use JuanAn6\Redsys\Response\PaymentStatus;
use PHPUnit\Framework\Attributes\DataProvider;

final class NotificationTest extends TestCase
{
    public static function statusProvider(): array
    {
        return [
            'autorizado 0000' => ['0000', '0', PaymentStatus::Authorized, true],
            'autorizado 0099' => ['0099', '0', PaymentStatus::Authorized, true],
            'preautorización' => ['0000', '1', PaymentStatus::Authorized, true],
            'denegado 0190' => ['0190', '0', PaymentStatus::Denied, false],
            'fallo 3DS 0184' => ['0184', '0', PaymentStatus::Denied, false],
            'caducada 0101' => ['0101', '0', PaymentStatus::Denied, false],
            'cancelado usuario' => ['9915', '0', PaymentStatus::CancelledByUser, false],
            'devolución 0900' => ['0900', '3', PaymentStatus::Refunded, false],
            'confirmación 0900' => ['0900', '2', PaymentStatus::Confirmed, false],
            'anulación 0400' => ['0400', '9', PaymentStatus::Cancelled, false],
            'sin código' => ['', '0', PaymentStatus::Error, false],
        ];
    }

    #[DataProvider('statusProvider')]
    public function testStatusMapping(string $code, string $type, PaymentStatus $expected, bool $paid): void
    {
        $n = $this->redsys()->handleNotification($this->fakeNotification(['Ds_Response' => $code, 'Ds_TransactionType' => $type]));

        self::assertSame($expected, $n->status);
        self::assertSame($paid, $n->isPaid());
        self::assertNotSame('', $n->getResponseMessage());
    }

    public function testGetters(): void
    {
        $n = $this->redsys()->handleNotification($this->fakeNotification([
            'Ds_Merchant_Identifier' => 'tok123',
            'Ds_ExpiryDate' => '2912',
            'Ds_Merchant_Cof_Txnid' => '999',
            'Ds_MerchantData' => '{"order_id":42}',
        ]));

        self::assertSame('1234ABCD', $n->getOrder());
        self::assertSame(1250, $n->getAmountInCents());
        self::assertSame(12.5, $n->getAmount());
        self::assertTrue($n->amountEquals('12,50'));
        self::assertFalse($n->amountEquals(12.49));
        self::assertSame('978', $n->getCurrency());
        self::assertSame(TransactionType::Authorization, $n->getTransactionType());
        self::assertSame('123456', $n->getAuthorisationCode());
        self::assertTrue($n->isSecurePayment());
        self::assertSame('tok123', $n->getIdentifier());
        self::assertSame('2912', $n->getExpiryDate());
        self::assertSame('999', $n->getCofTxnId());
        self::assertSame(['order_id' => 42], $n->getMerchantDataArray());
        self::assertSame('724', $n->getCardCountry());
        self::assertSame('2026-10-22 20:15', $n->getDateTime()?->format('Y-m-d H:i'));
        self::assertSame('1234ABCD', $n->get('ds_order'));
        self::assertSame('authorized', $n->toArray()['status']);
    }

    public function testStandardBase64AndLowercaseKeys(): void
    {
        $data = array_change_key_case($this->fakeNotification([], false), CASE_LOWER);
        self::assertTrue($this->redsys()->handleReturn($data)->isPaid());
    }

    public function testTamperedAmountIsRejected(): void
    {
        $data = $this->fakeNotification([]);
        $params = json_decode(base64_decode(strtr($data['Ds_MerchantParameters'], '-_', '+/')), true);
        $params['Ds_Amount'] = '1';
        $data['Ds_MerchantParameters'] = base64_encode(json_encode($params));

        $this->expectException(InvalidSignatureException::class);
        $this->redsys()->handleNotification($data);
    }

    public function testWrongKeyIsRejected(): void
    {
        $this->expectException(InvalidSignatureException::class);
        $this->redsys(['secret_key' => base64_encode(str_repeat('x', 24))])->handleNotification($this->fakeNotification([]));
    }

    public function testMissingFieldsThrow(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->redsys()->handleNotification(['foo' => 'bar']);
    }

    public function testTryHandleReturnsNullOnInvalid(): void
    {
        self::assertNull($this->redsys()->tryHandleNotification([]));
        self::assertNotNull($this->redsys()->tryHandleNotification($this->fakeNotification([])));
    }

    public function testRoundTripFromOwnRequest(): void
    {
        // La firma de la notificación usa el mismo algoritmo que la de la petición.
        $form = $this->redsys()->createPayment()->amount(5)->order('9999zz')->form();
        $notification = $this->fakeNotification(['Ds_Order' => $form->parameters['DS_MERCHANT_ORDER'], 'Ds_Amount' => $form->parameters['DS_MERCHANT_AMOUNT']]);

        $n = $this->redsys()->handleNotification($notification);
        self::assertSame('9999zz', $n->getOrder());
        self::assertTrue($n->amountEquals(5));
    }
}
