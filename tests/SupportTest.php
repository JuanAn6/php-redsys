<?php

declare(strict_types=1);

namespace JuanAn6\Redsys\Tests;

use JuanAn6\Redsys\Exception\InvalidParameterException;
use JuanAn6\Redsys\Response\ResponseCode;
use JuanAn6\Redsys\Support\Amount;
use JuanAn6\Redsys\Support\OrderNumber;
use PHPUnit\Framework\Attributes\DataProvider;

final class SupportTest extends TestCase
{
    public static function amounts(): array
    {
        return [
            [12.5, 1250], ['12,50', 1250], ['12.5', 1250], [10, 1000], [0.1 + 0.2, 30],
            [19.99, 1999], ['1.234,56', 123456], ['1,234.56', 123456], ['7 €', 700], [1.005, 101],
        ];
    }

    #[DataProvider('amounts')]
    public function testToCents(int|float|string $in, int $out): void
    {
        self::assertSame($out, Amount::toCents($in));
    }

    public static function invalidAmounts(): array
    {
        return [[0], [-1], ['abc'], ['1.234'], ['12.345'], [INF]];
    }

    #[DataProvider('invalidAmounts')]
    public function testInvalidAmounts(int|float|string $in): void
    {
        $this->expectException(InvalidParameterException::class);
        Amount::toCents($in);
    }

    public function testOrderNumber(): void
    {
        self::assertTrue(OrderNumber::isValid('1234'));
        self::assertTrue(OrderNumber::isValid('1234abcdEFGH'));
        self::assertFalse(OrderNumber::isValid('123'));
        self::assertFalse(OrderNumber::isValid('1234abcdEFGHI'));
        self::assertFalse(OrderNumber::isValid('A234'));
        self::assertFalse(OrderNumber::isValid('1234-56'));
        self::assertSame('000000000042', OrderNumber::fromId(42));

        $seen = [];
        for ($i = 0; $i < 200; $i++) {
            $o = OrderNumber::generate();
            self::assertTrue(OrderNumber::isValid($o));
            $seen[$o] = true;
        }
        self::assertCount(200, $seen);
    }

    public function testResponseMessages(): void
    {
        self::assertSame('El usuario ha cancelado el pago', ResponseCode::message('9915'));
        self::assertSame('Tarjeta caducada', ResponseCode::message('0101'));
        self::assertStringContainsString('autorizada', ResponseCode::message('0000'));
        self::assertStringContainsString('9876', ResponseCode::message('9876'));
        self::assertTrue(ResponseCode::isAuthorized('0099'));
        self::assertTrue(ResponseCode::isAuthorized('0900'));
        self::assertFalse(ResponseCode::isAuthorized('0101'));
        self::assertFalse(ResponseCode::isAuthorized(null));
    }
}
