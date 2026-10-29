<?php

declare(strict_types=1);

namespace Vaartix\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Vaartix\Laravel\VaartixTransport;
use Vaartix\Vaartix;

final class LaravelTransportTest extends TestCase
{
    private function transport(FakeTransport $t): VaartixTransport
    {
        return new VaartixTransport(new Vaartix('vx_test', ['transport' => $t]));
    }

    public function testSendsAnEmailAndReturnsTheVaartixId(): void
    {
        $t = new FakeTransport(200, ['id' => 'em_laravel']);
        $email = (new Email())
            ->from(new Address('orders@acme.in', 'Acme Orders'))
            ->to('priya@example.com')
            ->cc('ravi@example.com')
            ->replyTo('support@acme.in')
            ->subject('Order confirmed')
            ->text('Thanks for your order')
            ->html('<p>Thanks for your order</p>');
        $email->getHeaders()->addTextHeader('X-Order', '1042');
        $email->getHeaders()->add(new TagHeader('order'));
        $email->getHeaders()->add(new MetadataHeader('order_id', '1042'));

        $sent = $this->transport($t)->send($email);

        self::assertNotNull($sent);
        self::assertSame('em_laravel', $sent->getMessageId());
        $json = $t->calls[0]['json'];
        self::assertSame('Acme Orders <orders@acme.in>', $json['from']);
        self::assertSame(['priya@example.com'], $json['to']);
        self::assertSame(['ravi@example.com'], $json['cc']);
        self::assertSame(['support@acme.in'], $json['replyTo']);
        self::assertSame('Order confirmed', $json['subject']);
        self::assertSame('Thanks for your order', $json['text']);
        self::assertSame('<p>Thanks for your order</p>', $json['html']);
        self::assertSame(['X-Order' => '1042'], $json['headers']);
        self::assertSame([
            ['name' => 'order', 'value' => 'true'],
            ['name' => 'order_id', 'value' => '1042'],
        ], $json['tags']);
    }

    public function testBccAndAttachments(): void
    {
        $t = new FakeTransport();
        $email = (new Email())
            ->from('a@acme.in')
            ->to('b@example.com')
            ->bcc('audit@acme.in')
            ->subject('Invoice')
            ->html('<img src="cid:logo"> Attached')
            ->attach('total: 100', 'invoice.txt', 'text/plain')
            ->embed("\x89PNG fake", 'logo', 'image/png');

        $this->transport($t)->send($email);

        $json = $t->calls[0]['json'];
        self::assertSame(['audit@acme.in'], $json['bcc']);
        [$file, $image] = $json['attachments'];
        self::assertSame('invoice.txt', $file['filename']);
        self::assertSame('total: 100', base64_decode($file['content']));
        self::assertSame('text/plain', $file['contentType']);
        self::assertArrayNotHasKey('contentId', $file);
        self::assertSame('image/png', $image['contentType']);
        self::assertSame("\x89PNG fake", base64_decode($image['content']));
        self::assertArrayHasKey('contentId', $image);
    }

    public function testApiErrorsBecomeTransportExceptions(): void
    {
        $t = new FakeTransport(422, ['error' => ['type' => 'domain_not_verified', 'message' => 'Verify acme.in first']]);
        $email = (new Email())->from('a@acme.in')->to('b@example.com')->subject('Hi')->text('x');
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Verify acme.in first (domain_not_verified)');
        $this->transport($t)->send($email);
    }

    public function testNameIsTransportName(): void
    {
        self::assertSame('vaartix', (string) $this->transport(new FakeTransport()));
    }
}
