<?php

declare(strict_types=1);

namespace Vaartix;

use Vaartix\Exceptions\WebhookVerificationException;

/** Checks that webhook requests really came from Vaartix (Standard Webhooks signatures). */
final class Webhook
{
    private const PREFIX = 'whsec_';

    /**
     * Verify a webhook request and return its decoded JSON body.
     *
     * Pass the raw request body exactly as received: `file_get_contents('php://input')`, or
     * `$request->getContent()` in Laravel. Headers can be a plain array with any key case, or
     * the array of lists that Laravel's `$request->headers->all()` and PSR-7 return.
     *
     * @param array<string, string|list<string>> $headers
     * @return array<string, mixed>
     * @throws WebhookVerificationException
     */
    public static function verify(
        string $payload,
        array $headers,
        string $secret,
        int $toleranceSeconds = 300,
        ?int $now = null,
    ): array {
        $msgId = self::header($headers, 'webhook-id');
        $timestamp = self::header($headers, 'webhook-timestamp');
        $signatures = self::header($headers, 'webhook-signature');
        if ($msgId === null || $timestamp === null || $signatures === null) {
            throw new WebhookVerificationException(
                'Missing webhook-id, webhook-timestamp or webhook-signature header.',
            );
        }

        if (!ctype_digit($timestamp)) {
            throw new WebhookVerificationException('The webhook timestamp is not a number.');
        }
        if (abs(($now ?? time()) - (int) $timestamp) > $toleranceSeconds) {
            throw new WebhookVerificationException('The webhook timestamp is too old or in the future.');
        }

        if (!str_starts_with($secret, self::PREFIX)) {
            throw new WebhookVerificationException('The secret should start with ' . self::PREFIX . '.');
        }
        $key = base64_decode(substr($secret, strlen(self::PREFIX)), true);
        if ($key === false) {
            throw new WebhookVerificationException('The secret is not valid base64.');
        }

        $expected = hash_hmac('sha256', "{$msgId}.{$timestamp}.{$payload}", $key, true);

        // The header can hold several space-separated signatures, e.g. while a secret is rotated.
        foreach (explode(' ', $signatures) as $entry) {
            [$version, $signature] = array_pad(explode(',', $entry, 2), 2, '');
            if ($version !== 'v1' || $signature === '') {
                continue;
            }
            $given = base64_decode($signature, true);
            if ($given !== false && hash_equals($expected, $given)) {
                $decoded = json_decode($payload, true);
                if (!is_array($decoded)) {
                    throw new WebhookVerificationException('The webhook body is not a JSON object.');
                }
                /** @var array<string, mixed> $decoded */
                return $decoded;
            }
        }
        throw new WebhookVerificationException('The webhook signature does not match.');
    }

    /** @param array<string, string|list<string>> $headers */
    private static function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) !== $name) {
                continue;
            }
            if (is_array($value)) {
                $value = $value[0] ?? null;
            }
            return $value === null || $value === '' ? null : (string) $value;
        }
        return null;
    }
}
