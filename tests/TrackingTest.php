<?php

declare(strict_types=1);

namespace Vaartix\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vaartix\Tracking;

final class TrackingTest extends TestCase
{
    public function testParsesOnOffTrueFalseAndOneZero(): void
    {
        self::assertSame(['opens' => false, 'clicks' => true], Tracking::parseHeader('opens=off, clicks=on'));
        self::assertSame(['opens' => true, 'clicks' => true], Tracking::parseHeader(' OPENS = TRUE ,clicks=1'));
        self::assertSame(['clicks' => false], Tracking::parseHeader('clicks=0'));
    }

    public function testLaterValuesWin(): void
    {
        self::assertSame(
            ['opens' => true, 'clicks' => true],
            Tracking::parseHeader('opens=on', ['opens' => false, 'clicks' => true]),
        );
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalid(): iterable
    {
        yield 'empty' => ['', 'X-Vaartix-Tracking: use e.g. "opens=off, clicks=off".'];
        yield 'bad value' => ['opens=maybe', 'X-Vaartix-Tracking: "opens=maybe" is not opens=on|off or clicks=on|off.'];
        yield 'bad name' => ['foo=on', 'X-Vaartix-Tracking: "foo=on" is not opens=on|off or clicks=on|off.'];
        yield 'no value' => ['opens', 'X-Vaartix-Tracking: "opens" is not opens=on|off or clicks=on|off.'];
    }

    #[DataProvider('invalid')]
    public function testRejectsInvalidValuesWithTheRelaysMessage(string $value, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        Tracking::parseHeader($value);
    }
}
