<?php

declare(strict_types=1);

namespace Vaartix\Tests;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Mail\MailManager;
use PHPUnit\Framework\TestCase;
use Vaartix\Laravel\VaartixServiceProvider;
use Vaartix\Laravel\VaartixTransport;
use Vaartix\Vaartix;

final class LaravelServiceProviderTest extends TestCase
{
    public function testRegistersTheVaartixMailerAndClient(): void
    {
        $app = new Container();
        $app->instance('config', new Repository([
            'services' => ['vaartix' => ['key' => 'vx_from_config']],
            'mail' => ['mailers' => ['vaartix' => ['transport' => 'vaartix']]],
        ]));
        $app->singleton('mail.manager', static fn ($app) => new MailManager($app));

        $provider = new VaartixServiceProvider($app);
        $provider->register();
        $provider->boot();

        self::assertInstanceOf(Vaartix::class, $app->make(Vaartix::class));
        $manager = $app->make('mail.manager');
        self::assertInstanceOf(MailManager::class, $manager);
        $transport = $manager->createSymfonyTransport(['transport' => 'vaartix']);
        self::assertInstanceOf(VaartixTransport::class, $transport);
    }
}
