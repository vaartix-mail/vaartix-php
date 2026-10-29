<?php

declare(strict_types=1);

namespace Vaartix\Resources;

use Vaartix\Exceptions\VaartixException;
use Vaartix\Vaartix;

final class Batch
{
    public function __construct(private readonly Vaartix $client)
    {
    }

    /**
     * Send up to 100 emails in one request (no attachments or scheduling). All or nothing.
     *
     * @param array<array<string, mixed>> $emails
     * @return array{data: list<array{id: string}>}
     * @throws VaartixException
     */
    public function send(array $emails): array
    {
        /** @var array{data: list<array{id: string}>} */
        return $this->client->request('POST', '/emails/batch', array_map([Params::class, 'toApi'], array_values($emails)));
    }
}
