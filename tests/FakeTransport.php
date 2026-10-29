<?php

declare(strict_types=1);

namespace Vaartix\Tests;

/** Records requests and answers with a fixed response instead of calling the network. */
final class FakeTransport
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, json: mixed, timeout: float}> */
    public array $calls = [];

    /** @param array<mixed>|string $body */
    public function __construct(private int $status = 200, private array|string $body = ['id' => 'em_1'])
    {
    }

    /**
     * @param array<string, string> $headers
     * @return array{int, string}
     */
    public function __invoke(string $method, string $url, array $headers, ?string $body, float $timeout): array
    {
        $this->calls[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'json' => $body === null ? null : json_decode($body, true),
            'timeout' => $timeout,
        ];
        return [$this->status, is_string($this->body) ? $this->body : (string) json_encode($this->body)];
    }
}
