<?php

declare(strict_types=1);

namespace Vaartix\Resources;

use Vaartix\Exceptions\VaartixException;
use Vaartix\Vaartix;

final class Emails
{
    public function __construct(private readonly Vaartix $client)
    {
    }

    /**
     * Send an email. Returns `['id' => 'em_...']` once it is queued.
     *
     * Keys can be snake_case (`reply_to`, `scheduled_at`) or the API's camelCase. With an
     * idempotency key, retrying within 24 hours returns the first email instead of sending again.
     *
     * @param array<string, mixed> $params
     * @return array{id: string}
     * @throws VaartixException
     */
    public function send(array $params, ?string $idempotencyKey = null): array
    {
        /** @var array{id: string} */
        return $this->client->request('POST', '/emails', Params::toApi($params), [], $idempotencyKey);
    }

    /**
     * An email with its status and event timeline. Needs a full-access key.
     *
     * @return array<string, mixed>
     * @throws VaartixException
     */
    public function get(string $id): array
    {
        /** @var array<string, mixed> */
        return $this->client->request('GET', '/emails/' . Params::id($id));
    }

    /**
     * Recent emails, newest first: `['data' => [...], 'hasMore' => bool]`. Needs a full-access key.
     *
     * @param array{limit?: int, before?: string, status?: string, search?: string, days?: int, api_key_id?: string} $options
     * @return array{data: list<array<string, mixed>>, hasMore: bool}
     * @throws VaartixException
     */
    public function list(array $options = []): array
    {
        /** @var array{data: list<array<string, mixed>>, hasMore: bool} */
        return $this->client->request('GET', '/emails', null, [
            'limit' => $options['limit'] ?? null,
            'before' => $options['before'] ?? null,
            'status' => $options['status'] ?? null,
            'search' => $options['search'] ?? null,
            'days' => $options['days'] ?? null,
            'apiKeyId' => $options['api_key_id'] ?? null,
        ]);
    }

    /**
     * Cancel a scheduled email before it is sent. Needs a full-access key.
     *
     * @return array<string, mixed>
     * @throws VaartixException
     */
    public function cancel(string $id): array
    {
        /** @var array<string, mixed> */
        return $this->client->request('POST', '/emails/' . Params::id($id) . '/cancel');
    }
}
