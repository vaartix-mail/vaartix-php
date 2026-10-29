<?php

declare(strict_types=1);

namespace Vaartix\Resources;

use Vaartix\Exceptions\VaartixException;
use Vaartix\Vaartix;
use Vaartix\Webhook;

/** Webhook endpoints. Managing them needs a full-access key. */
final class Webhooks
{
    public function __construct(private readonly Vaartix $client)
    {
    }

    /**
     * Create an endpoint. The response includes the signing secret, shown only this once.
     *
     * @param array<string> $events e.g. ['email.delivered', 'email.bounced']
     * @return array<string, mixed>
     * @throws VaartixException
     */
    public function create(string $url, array $events): array
    {
        /** @var array<string, mixed> */
        return $this->client->request('POST', '/webhooks', ['url' => $url, 'events' => array_values($events)]);
    }

    /**
     * @return array{data: list<array<string, mixed>>}
     * @throws VaartixException
     */
    public function list(): array
    {
        /** @var array{data: list<array<string, mixed>>} */
        return $this->client->request('GET', '/webhooks');
    }

    /**
     * @return array<string, mixed>
     * @throws VaartixException
     */
    public function get(string $id): array
    {
        /** @var array<string, mixed> */
        return $this->client->request('GET', '/webhooks/' . Params::id($id));
    }

    /**
     * Change the URL or events, or turn the endpoint off (`'status' => 'disabled'`) and on.
     *
     * @param array{url?: string, events?: list<string>, status?: string} $changes
     * @return array<string, mixed>
     * @throws VaartixException
     */
    public function update(string $id, array $changes): array
    {
        /** @var array<string, mixed> */
        return $this->client->request('PATCH', '/webhooks/' . Params::id($id), $changes);
    }

    /** @throws VaartixException */
    public function remove(string $id): void
    {
        $this->client->request('DELETE', '/webhooks/' . Params::id($id));
    }

    /**
     * Replace the signing secret. The old one stops working immediately.
     *
     * @return array<string, mixed>
     * @throws VaartixException
     */
    public function rotateSecret(string $id): array
    {
        /** @var array<string, mixed> */
        return $this->client->request('POST', '/webhooks/' . Params::id($id) . '/rotate-secret');
    }

    /**
     * Recent deliveries to an endpoint, newest first, with your server's responses.
     *
     * @return array{data: list<array<string, mixed>>}
     * @throws VaartixException
     */
    public function deliveries(string $id, ?int $limit = null): array
    {
        /** @var array{data: list<array<string, mixed>>} */
        return $this->client->request('GET', '/webhooks/' . Params::id($id) . '/deliveries', null, ['limit' => $limit]);
    }

    /**
     * Send a delivery again, with the same `webhook-id`.
     *
     * @return array<string, mixed>
     * @throws VaartixException
     */
    public function retryDelivery(string $id, string $deliveryId): array
    {
        /** @var array<string, mixed> */
        return $this->client->request(
            'POST',
            '/webhooks/' . Params::id($id) . '/deliveries/' . Params::id($deliveryId) . '/retry',
        );
    }

    /**
     * Same as `Vaartix\Webhook::verify()`.
     *
     * @param array<string, string|list<string>> $headers
     * @return array<string, mixed>
     */
    public function verify(string $payload, array $headers, string $secret, int $toleranceSeconds = 300): array
    {
        return Webhook::verify($payload, $headers, $secret, $toleranceSeconds);
    }
}
