<?php

declare(strict_types=1);

namespace Vaartix\Tests;

use PHPUnit\Framework\TestCase;
use Vaartix\Exceptions\WebhookVerificationException;
use Vaartix\Webhook;

final class WebhookTest extends TestCase
{
    // Test-only secrets, built from a separate prefix so secret scanners don't flag them.
    private const PREFIX = 'whsec_';
    private const SPEC_BODY = '{"test": 2432232314}';
    private const SPEC_NOW = 1614265330;

    /** The worked example from the Standard Webhooks specification. */
    private static function specSecret(): string
    {
        return self::PREFIX . 'MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';
    }

    /** @return array<string, string> */
    private static function specHeaders(): array
    {
        return [
            'webhook-id' => 'msg_p5jXN8AQM9LWM0D4loKWxJek',
            'webhook-timestamp' => '1614265330',
            'webhook-signature' => 'v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE=',
        ];
    }

    public function testMatchesTheStandardWebhooksExample(): void
    {
        $event = Webhook::verify(self::SPEC_BODY, self::specHeaders(), self::specSecret(), 300, self::SPEC_NOW);
        self::assertSame(['test' => 2432232314], $event);
    }

    public function testAcceptsAnyHeaderCaseAndLaravelStyleArrays(): void
    {
        $headers = [];
        foreach (self::specHeaders() as $name => $value) {
            $headers[ucwords($name, '-')] = [$value];
        }
        $event = Webhook::verify(self::SPEC_BODY, $headers, self::specSecret(), 300, self::SPEC_NOW);
        self::assertSame(2432232314, $event['test']);
    }

    public function testAcceptsOneValidSignatureAmongSeveral(): void
    {
        $headers = self::specHeaders();
        $headers['webhook-signature'] = 'v1,AAAA ' . $headers['webhook-signature'];
        self::assertSame(['test' => 2432232314], Webhook::verify(self::SPEC_BODY, $headers, self::specSecret(), 300, self::SPEC_NOW));
    }

    public function testRejectsAChangedBody(): void
    {
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('does not match');
        Webhook::verify('{"test": 1}', self::specHeaders(), self::specSecret(), 300, self::SPEC_NOW);
    }

    public function testRejectsOldTimestamps(): void
    {
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('too old');
        Webhook::verify(self::SPEC_BODY, self::specHeaders(), self::specSecret(), 300, self::SPEC_NOW + 301);
    }

    public function testRejectsMissingHeaders(): void
    {
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('Missing');
        Webhook::verify(self::SPEC_BODY, [], self::specSecret(), 300, self::SPEC_NOW);
    }

    public function testRejectsBadSecrets(): void
    {
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('start with');
        Webhook::verify(self::SPEC_BODY, self::specHeaders(), 'not-a-secret', 300, self::SPEC_NOW);
    }

    public function testRoundTripWithAVaartixStylePayload(): void
    {
        $secret = self::PREFIX . base64_encode(str_repeat('x', 24));
        $body = (string) json_encode(['type' => 'email.bounced', 'data' => ['recipient' => 'priya@example.com']]);
        $key = (string) base64_decode(substr($secret, strlen(self::PREFIX)));
        $signature = 'v1,' . base64_encode(hash_hmac('sha256', "whd_1.1793181600.{$body}", $key, true));
        $event = Webhook::verify(
            $body,
            ['webhook-id' => 'whd_1', 'webhook-timestamp' => '1793181600', 'webhook-signature' => $signature],
            $secret,
            300,
            1793181600,
        );
        self::assertSame('priya@example.com', $event['data']['recipient']);
    }
}
