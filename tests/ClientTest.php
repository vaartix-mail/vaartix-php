<?php

declare(strict_types=1);

namespace Vaartix\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vaartix\Exceptions\VaartixException;
use Vaartix\Vaartix;

final class ClientTest extends TestCase
{
    private function client(FakeTransport $t, array $options = []): Vaartix
    {
        return new Vaartix('vx_test', ['transport' => $t] + $options);
    }

    public function testSendsWithAuthJsonAndIdempotencyKey(): void
    {
        $t = new FakeTransport();
        $result = $this->client($t, ['base_url' => 'https://api.example.test/'])->emails->send(
            ['from' => 'a@acme.in', 'to' => 'b@example.com', 'subject' => 'Hi', 'html' => '<p>Hi</p>'],
            'order-42',
        );
        self::assertSame(['id' => 'em_1'], $result);
        $call = $t->calls[0];
        self::assertSame('POST', $call['method']);
        self::assertSame('https://api.example.test/emails', $call['url']);
        self::assertSame('Bearer vx_test', $call['headers']['Authorization']);
        self::assertSame('order-42', $call['headers']['Idempotency-Key']);
        self::assertSame('vaartix-php/' . Vaartix::VERSION, $call['headers']['User-Agent']);
        self::assertSame('Hi', $call['json']['subject']);
    }

    public function testSnakeCaseKeysBecomeApiNames(): void
    {
        $t = new FakeTransport();
        $this->client($t)->emails->send([
            'from' => 'a@acme.in',
            'to' => ['b@example.com'],
            'subject' => 'Invoice',
            'text' => 'Attached',
            'reply_to' => 'billing@acme.in',
            'scheduled_at' => '2026-11-01T09:00:00+05:30',
            'attachments' => [['filename' => 'a.pdf', 'content' => 'QQ==', 'content_type' => 'application/pdf']],
        ]);
        $sent = $t->calls[0]['json'];
        self::assertSame('billing@acme.in', $sent['replyTo']);
        self::assertSame('2026-11-01T09:00:00+05:30', $sent['scheduledAt']);
        self::assertSame('application/pdf', $sent['attachments'][0]['contentType']);
        self::assertArrayNotHasKey('reply_to', $sent);
        self::assertSame(['b@example.com'], $sent['to']);
    }

    public function testApiErrorsThrowVaartixException(): void
    {
        $t = new FakeTransport(422, ['error' => ['type' => 'validation_error', 'message' => 'from is required']]);
        try {
            $this->client($t)->emails->send(['to' => 'b@example.com', 'subject' => 'Hi']);
            self::fail('Expected an exception');
        } catch (VaartixException $e) {
            self::assertSame('validation_error', $e->type);
            self::assertSame('from is required', $e->getMessage());
            self::assertSame(422, $e->statusCode);
        }
    }

    public function testNonJsonErrorsStillThrow(): void
    {
        $t = new FakeTransport(502, '<html>Bad gateway</html>');
        $this->expectException(VaartixException::class);
        $this->expectExceptionMessage('Request failed with status 502');
        $this->client($t)->emails->get('em_1');
    }

    public function testNetworkErrorsHaveNoStatus(): void
    {
        $client = new Vaartix('vx', ['base_url' => 'http://127.0.0.1:1', 'timeout' => 2]);
        try {
            $client->emails->get('em_1');
            self::fail('Expected an exception');
        } catch (VaartixException $e) {
            self::assertSame('network_error', $e->type);
            self::assertNull($e->statusCode);
        }
    }

    public function testListBuildsQueryAndSkipsEmptyFilters(): void
    {
        $t = new FakeTransport(200, ['data' => [], 'hasMore' => false]);
        $this->client($t)->emails->list(['limit' => 5, 'status' => 'bounced', 'api_key_id' => 'key_1']);
        self::assertSame('https://api.vaartix.com/emails?limit=5&status=bounced&apiKeyId=key_1', $t->calls[0]['url']);
    }

    public function testIdsAreEscapedInPaths(): void
    {
        $t = new FakeTransport(200, ['id' => 'em_1', 'status' => 'canceled']);
        $this->client($t)->emails->cancel('em_1/../x');
        self::assertSame('https://api.vaartix.com/emails/em_1%2F..%2Fx/cancel', $t->calls[0]['url']);
    }

    public function testBatchSendsAList(): void
    {
        $t = new FakeTransport(200, ['data' => [['id' => 'em_1'], ['id' => 'em_2']]]);
        $result = $this->client($t)->batch->send([
            ['from' => 'a@acme.in', 'to' => 'b@example.com', 'subject' => '1', 'text' => 'x'],
            ['from' => 'a@acme.in', 'to' => 'c@example.com', 'subject' => '2', 'text' => 'y', 'reply_to' => 'r@acme.in'],
        ]);
        self::assertSame(['em_1', 'em_2'], array_column($result['data'], 'id'));
        self::assertStringEndsWith('/emails/batch', $t->calls[0]['url']);
        self::assertSame('r@acme.in', $t->calls[0]['json'][1]['replyTo']);
    }

    public function testWebhookManagement(): void
    {
        $t = new FakeTransport(200, ['id' => 'wh_1']);
        $hooks = $this->client($t)->webhooks;
        $hooks->create('https://acme.in/hooks', ['email.bounced']);
        $hooks->update('wh_1', ['status' => 'disabled']);
        $hooks->deliveries('wh_1', 10);
        $hooks->retryDelivery('wh_1', 'whd_1');
        $routes = array_map(
            static fn (array $c) => [$c['method'], str_replace('https://api.vaartix.com', '', $c['url'])],
            $t->calls,
        );
        self::assertSame([
            ['POST', '/webhooks'],
            ['PATCH', '/webhooks/wh_1'],
            ['GET', '/webhooks/wh_1/deliveries?limit=10'],
            ['POST', '/webhooks/wh_1/deliveries/whd_1/retry'],
        ], $routes);
        self::assertSame(['url' => 'https://acme.in/hooks', 'events' => ['email.bounced']], $t->calls[0]['json']);
    }

    public function testApiKeyFromEnvironment(): void
    {
        putenv('VAARTIX_API_KEY=vx_env');
        try {
            $t = new FakeTransport();
            (new Vaartix(null, ['transport' => $t]))->emails->get('em_1');
            self::assertSame('Bearer vx_env', $t->calls[0]['headers']['Authorization']);
        } finally {
            putenv('VAARTIX_API_KEY');
        }
    }

    public function testMissingApiKey(): void
    {
        putenv('VAARTIX_API_KEY');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('VAARTIX_API_KEY');
        new Vaartix();
    }

    public function testManyTrailingSlashesInBaseUrl(): void
    {
        $t = new FakeTransport();
        $this->client($t, ['base_url' => 'https://api.example.test' . str_repeat('/', 50000)])->emails->get('em_1');
        self::assertSame('https://api.example.test/emails/em_1', $t->calls[0]['url']);
    }
}
