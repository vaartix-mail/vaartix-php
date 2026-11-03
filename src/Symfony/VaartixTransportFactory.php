<?php

declare(strict_types=1);

namespace Vaartix\Symfony;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Vaartix\Laravel\VaartixTransport;
use Vaartix\Vaartix;

/**
 * Lets Symfony Mailer build the Vaartix transport from a DSN:
 *
 *     MAILER_DSN=vaartix+api://vx_your_api_key@default
 *
 * `default` uses https://api.vaartix.com; any other host is used as the API host
 * (vaartix+api://KEY@localhost:3000 for local testing). Options: `timeout` in seconds.
 */
final class VaartixTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        if (!in_array($dsn->getScheme(), $this->getSupportedSchemes(), true)) {
            throw new UnsupportedSchemeException($dsn, 'vaartix', $this->getSupportedSchemes());
        }

        $options = [];
        if ($dsn->getHost() !== 'default') {
            $port = $dsn->getPort();
            $options['base_url'] = 'https://' . $dsn->getHost() . ($port !== null ? ':' . $port : '');
        }
        $timeout = $dsn->getOption('timeout');
        if (is_numeric($timeout)) {
            $options['timeout'] = (float) $timeout;
        }

        return new VaartixTransport(new Vaartix($this->getUser($dsn), $options), $this->dispatcher, $this->logger);
    }

    /** @return list<string> */
    protected function getSupportedSchemes(): array
    {
        return ['vaartix', 'vaartix+api'];
    }
}
