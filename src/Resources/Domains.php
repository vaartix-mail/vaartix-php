<?php

declare(strict_types=1);

namespace Vaartix\Resources;

use Vaartix\Exceptions\VaartixException;
use Vaartix\Vaartix;

/** Sending domains. Changing them needs a full-access key from an owner or admin. */
final class Domains
{
    public function __construct(private readonly Vaartix $client)
    {
    }

    /**
     * Change a domain's default open and click tracking. Keys left out keep their value.
     *
     * @param array{open_tracking?: bool, click_tracking?: bool, openTracking?: bool, clickTracking?: bool} $params
     * @return array<string, mixed> The domain, including `openTracking` and `clickTracking`.
     * @throws VaartixException
     */
    public function update(string $id, array $params): array
    {
        /** @var array<string, mixed> */
        return $this->client->request('PATCH', '/domains/' . Params::id($id), Params::toApi($params));
    }
}
