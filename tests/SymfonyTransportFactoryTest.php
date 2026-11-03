<?php

declare(strict_types=1);

namespace Vaartix\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Mailer\Exception\IncompleteDsnException;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Dsn;
use Vaartix\Laravel\VaartixTransport;
use Vaartix\Symfony\VaartixTransportFactory;
use Vaartix\Vaartix;

final class SymfonyTransportFactoryTest extends TestCase
{
    /** @return array{0: string, 1: float, 2: string} Base URL, timeout and API key of the client inside the transport. */
    private static function clientSettings(VaartixTransport $transport): array
    {
        $client = (new ReflectionProperty(VaartixTransport::class, 'client'))->getValue($transport);
        self::assertInstanceOf(Vaartix::class, $client);
        $read = static fn (string $name) => (new ReflectionProperty(Vaartix::class, $name))->getValue($client);
        return [$read('baseUrl'), $read('timeout'), $read('apiKey')];
    }

    public function testDefaultHostUsesTheVaartixApi(): void
    {
        $transport = (new VaartixTransportFactory())->create(Dsn::fromString('vaartix+api://vx_test@default'));

        self::assertInstanceOf(VaartixTransport::class, $transport);
        self::assertSame([Vaartix::DEFAULT_BASE_URL, 30.0, 'vx_test'], self::clientSettings($transport));
    }

    public function testCustomHostPortAndTimeout(): void
    {
        $transport = (new VaartixTransportFactory())->create(Dsn::fromString('vaartix://vx_test@localhost:3000?timeout=5'));

        self::assertInstanceOf(VaartixTransport::class, $transport);
        self::assertSame(['https://localhost:3000', 5.0, 'vx_test'], self::clientSettings($transport));
    }

    public function testUrlEncodedKeyIsDecoded(): void
    {
        $transport = (new VaartixTransportFactory())->create(Dsn::fromString('vaartix+api://vx_a%2Bb@default'));

        self::assertInstanceOf(VaartixTransport::class, $transport);
        self::assertSame('vx_a+b', self::clientSettings($transport)[2]);
    }

    public function testMissingKeyIsRejected(): void
    {
        $this->expectException(IncompleteDsnException::class);
        (new VaartixTransportFactory())->create(Dsn::fromString('vaartix+api://default'));
    }

    public function testOtherSchemesAreRejected(): void
    {
        $factory = new VaartixTransportFactory();
        self::assertTrue($factory->supports(Dsn::fromString('vaartix+api://vx_test@default')));
        self::assertFalse($factory->supports(Dsn::fromString('smtp://user:pass@example.com')));

        $this->expectException(UnsupportedSchemeException::class);
        $factory->create(Dsn::fromString('smtp://user:pass@example.com'));
    }

    public function testWorksWithSymfonyTransportFromString(): void
    {
        $transport = (new Transport([new VaartixTransportFactory()]))->fromString('vaartix+api://vx_test@default');

        self::assertSame('vaartix', (string) $transport);
    }
}
