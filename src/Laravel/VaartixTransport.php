<?php

declare(strict_types=1);

namespace Vaartix\Laravel;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\HeaderInterface;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;
use Vaartix\Exceptions\VaartixException;
use Vaartix\Vaartix;

/**
 * Symfony Mailer transport that sends through the Vaartix API. Laravel uses it for the
 * `vaartix` mailer; it also works in any Symfony Mailer setup.
 */
final class VaartixTransport extends AbstractTransport
{
    /** Headers Vaartix builds from the message itself. */
    private const RESERVED_HEADERS = [
        'from', 'to', 'cc', 'bcc', 'reply-to', 'sender', 'subject', 'date', 'message-id',
        'content-type', 'content-transfer-encoding', 'mime-version', 'return-path',
    ];

    public function __construct(private readonly Vaartix $client)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();
        if (!$original instanceof Message) {
            throw new TransportException('Vaartix can only send structured emails, not raw MIME messages.');
        }
        $email = MessageConverter::toEmail($original);
        $params = self::toParams($email, $message->getEnvelope());
        try {
            $result = $this->client->emails->send($params);
        } catch (VaartixException $e) {
            throw new TransportException("Vaartix: {$e->getMessage()} ({$e->type})", (int) $e->getCode(), $e);
        }
        // Laravel's SentMessage::getMessageId() then returns the Vaartix email ID (em_...).
        $message->setMessageId($result['id']);
    }

    /**
     * @internal Public for tests.
     * @return array<string, mixed>
     */
    public static function toParams(Email $email, Envelope $envelope): array
    {
        $from = $email->getFrom()[0] ?? $envelope->getSender();
        $params = [
            'from' => self::address($from),
            'to' => self::addresses($email->getTo()),
            'subject' => $email->getSubject() ?? '',
        ];
        // Bcc recipients only exist in the envelope once Symfony has removed the Bcc header.
        $listed = array_map(
            static fn (Address $a) => strtolower($a->getAddress()),
            [...$email->getTo(), ...$email->getCc(), ...$email->getBcc()],
        );
        $bcc = self::addresses($email->getBcc());
        foreach ($envelope->getRecipients() as $recipient) {
            if (!in_array(strtolower($recipient->getAddress()), $listed, true)) {
                $bcc[] = $recipient->getAddress();
            }
        }
        if ($email->getCc() !== []) {
            $params['cc'] = self::addresses($email->getCc());
        }
        if ($bcc !== []) {
            $params['bcc'] = $bcc;
        }
        if ($email->getReplyTo() !== []) {
            $params['reply_to'] = self::addresses($email->getReplyTo());
        }

        $html = $email->getHtmlBody();
        $text = $email->getTextBody();
        if ($html !== null) {
            $params['html'] = is_resource($html) ? (string) stream_get_contents($html) : $html;
        }
        if ($text !== null) {
            $params['text'] = is_resource($text) ? (string) stream_get_contents($text) : $text;
        }

        $attachments = [];
        foreach ($email->getAttachments() as $part) {
            $attachment = [
                'filename' => $part->getFilename() ?? 'attachment',
                'content' => base64_encode($part->getBody()),
                'content_type' => $part->getMediaType() . '/' . $part->getMediaSubtype(),
            ];
            if ($part->getDisposition() === 'inline') {
                // Laravel's $message->embed() creates a Content-ID and puts it in the HTML.
                // Symfony's ->embed($data, 'logo') is referenced as cid:logo instead.
                $attachment['content_id'] = $part->hasContentId()
                    ? $part->getContentId()
                    : ($part->getFilename() ?? 'inline');
            }
            $attachments[] = $attachment;
        }
        if ($attachments !== []) {
            $params['attachments'] = $attachments;
        }

        $headers = [];
        $tags = [];
        foreach ($email->getHeaders()->all() as $header) {
            /** @var HeaderInterface $header */
            if ($header instanceof TagHeader) {
                // Laravel's ->tag('welcome') becomes the Vaartix tag welcome=true.
                $tags[] = ['name' => $header->getValue(), 'value' => 'true'];
            } elseif ($header instanceof MetadataHeader) {
                // Laravel's ->metadata('order_id', 42) becomes the Vaartix tag order_id=42.
                $tags[] = ['name' => $header->getKey(), 'value' => $header->getValue()];
            } elseif (!in_array(strtolower($header->getName()), self::RESERVED_HEADERS, true)) {
                $headers[$header->getName()] = $header->getBodyAsString();
            }
        }
        if ($headers !== []) {
            $params['headers'] = $headers;
        }
        if ($tags !== []) {
            $params['tags'] = $tags;
        }
        return $params;
    }

    public function __toString(): string
    {
        return 'vaartix';
    }

    private static function address(Address $address): string
    {
        $name = $address->getName();
        return $name === '' ? $address->getAddress() : sprintf('%s <%s>', str_replace(['"', '<', '>'], '', $name), $address->getAddress());
    }

    /**
     * @param Address[] $addresses
     * @return list<string>
     */
    private static function addresses(array $addresses): array
    {
        return array_values(array_map([self::class, 'address'], $addresses));
    }
}
