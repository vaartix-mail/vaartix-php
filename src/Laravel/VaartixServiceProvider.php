<?php

declare(strict_types=1);

namespace Vaartix\Laravel;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Mail\MailManager;
use Illuminate\Support\ServiceProvider;
use Vaartix\Vaartix;

/**
 * Registers the `vaartix` mail transport and a shared Vaartix client. Laravel loads this
 * automatically (package discovery); no manual registration is needed.
 */
final class VaartixServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Vaartix::class, static function (Container $app): Vaartix {
            /** @var ConfigRepository $repository */
            $repository = $app->make('config');
            /** @var array{key?: string|null, base_url?: string|null} $config */
            $config = $repository->get('services.vaartix', []);
            $options = isset($config['base_url']) ? ['base_url' => $config['base_url']] : [];
            return new Vaartix($config['key'] ?? null, $options);
        });
    }

    public function boot(): void
    {
        $app = $this->app;
        $this->callAfterResolving('mail.manager', static function (MailManager $mail) use ($app): void {
            $mail->extend('vaartix', static function (array $config) use ($app): VaartixTransport {
                $key = $config['key'] ?? null;
                $client = is_string($key) && $key !== ''
                    ? new Vaartix($key)
                    : $app->make(Vaartix::class);
                return new VaartixTransport($client);
            });
        });
    }
}
