<?php

declare(strict_types=1);

namespace Vaartix;

use InvalidArgumentException;
use JsonException;
use Vaartix\Exceptions\VaartixException;
use Vaartix\Resources\Batch;
use Vaartix\Resources\Emails;
use Vaartix\Resources\Webhooks;

/**
 * Client for the Vaartix email API.
 *
 *     $vaartix = new Vaartix\Vaartix();  // reads VAARTIX_API_KEY
 *     $email = $vaartix->emails->send([
 *         'from' => 'Acme <hi@acme.in>',
 *         'to' => 'priya@example.com',
 *         'subject' => 'Hello',
 *         'html' => '<p>Hi</p>',
 *     ]);
 *     echo $email['id'];
 */
final class Vaartix
{
    public const VERSION = '0.1.0';
    public const DEFAULT_BASE_URL = 'https://api.vaartix.com';

    public readonly Emails $emails;
    public readonly Batch $batch;
    public readonly Webhooks $webhooks;

    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly float $timeout;
    /** @var callable(string, string, array<string, string>, ?string, float): array{int, string} */
    private $transport;

    /**
     * @param array{base_url?: string, timeout?: float|int, transport?: callable} $options
     *   `transport` replaces the HTTP layer (for tests): it receives
     *   (method, url, headers, body, timeout) and returns [statusCode, responseBody].
     */
    public function __construct(?string $apiKey = null, array $options = [])
    {
        $key = $apiKey ?? (getenv('VAARTIX_API_KEY') ?: null);
        if ($key === null || $key === '') {
            throw new InvalidArgumentException(
                'Missing API key. Pass it as new Vaartix(\'vx_...\') or set the VAARTIX_API_KEY environment variable.',
            );
        }
        $this->apiKey = $key;
        $this->baseUrl = rtrim($options['base_url'] ?? self::DEFAULT_BASE_URL, '/');
        $this->timeout = (float) ($options['timeout'] ?? 30);
        $this->transport = $options['transport'] ?? [self::class, 'curlTransport'];

        $this->emails = new Emails($this);
        $this->batch = new Batch($this);
        $this->webhooks = new Webhooks($this);
    }

    /**
     * @internal Used by the resource classes.
     * @param array<array-key, mixed>|null $body
     * @param array<string, scalar|null> $query
     * @return mixed The decoded JSON response.
     * @throws VaartixException
     */
    public function request(
        string $method,
        string $path,
        ?array $body = null,
        array $query = [],
        ?string $idempotencyKey = null,
    ): mixed {
        $url = $this->baseUrl . $path;
        $query = array_filter($query, static fn ($v) => $v !== null);
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'User-Agent' => 'vaartix-php/' . self::VERSION,
            'Accept' => 'application/json',
        ];
        $data = null;
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            try {
                $data = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } catch (JsonException $e) {
                throw new VaartixException('validation_error', 'Could not encode the request as JSON: ' . $e->getMessage(), null, $e);
            }
        }
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        [$status, $raw] = ($this->transport)($method, $url, $headers, $data, $this->timeout);

        $payload = null;
        if ($raw !== '') {
            $payload = json_decode($raw, true);
        }
        if ($status >= 400) {
            $error = is_array($payload) && is_array($payload['error'] ?? null) ? $payload['error'] : [];
            throw new VaartixException(
                is_string($error['type'] ?? null) ? $error['type'] : 'api_error',
                is_string($error['message'] ?? null) ? $error['message'] : "Request failed with status {$status}",
                $status,
            );
        }
        return $payload;
    }

    /**
     * @param array<string, string> $headers
     * @return array{int, string}
     * @throws VaartixException
     */
    public static function curlTransport(string $method, string $url, array $headers, ?string $body, float $timeout): array
    {
        if ($method === '' || $url === '') {
            throw new InvalidArgumentException('A request needs a method and a URL.');
        }
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => (int) ceil(min($timeout, 10)),
            CURLOPT_TIMEOUT_MS => (int) ($timeout * 1000),
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($ch);
        if ($response === false) {
            $message = curl_error($ch);
            curl_close($ch);
            throw new VaartixException('network_error', $message !== '' ? $message : 'Could not reach the Vaartix API.');
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [$status, (string) $response];
    }
}
