<?php

declare(strict_types=1);

namespace Vaartix;

/**
 * Webhook event types, for `$vaartix->webhooks->create()` and for checking `$event['type']`.
 *
 * `email.opened` and `email.clicked` are sent only to endpoints that subscribe to them. Their
 * `$event['data']['details']` contains:
 * - `machine` (bool): true when it was most likely not a person (an image proxy, Apple Mail
 *   Privacy Protection or a link scanner), with the reason in `machineReason` (string|null):
 *   `image_proxy`, `apple_mpp` or `too_fast` for opens; `scanner`, `scanner_network`,
 *   `too_fast` or `burst` for clicks.
 * - `userAgentFamily` (string), `country` (string|null) and `asn` (int|null).
 * - Clicks only: `link` (the original URL) and `linkIndex` (its position in the HTML, from 0).
 *
 * `email.delivery_delayed` details: `delayType`, `expirationTime` (when retries stop),
 * `diagnosticCode` (the receiving server's exact reply, e.g. Yahoo's `421 4.7.0 [TSS04] …` line)
 * and `statusCode` (e.g. `4.7.0`). A soft bounce can also arrive as `email.delivery_delayed`;
 * it has `bounceType` instead of `delayType`.
 *
 * `email.bounced` details: `bounceType` (`Permanent`), `bounceSubType`, `diagnosticCode`,
 * `statusCode` and the deprecated `diagnostics` list.
 */
final class WebhookEvent
{
    public const SENT = 'email.sent';
    public const DELIVERED = 'email.delivered';
    public const DELIVERY_DELAYED = 'email.delivery_delayed';
    public const BOUNCED = 'email.bounced';
    public const COMPLAINED = 'email.complained';
    public const REJECTED = 'email.rejected';
    public const RENDERING_FAILURE = 'email.rendering_failure';
    public const SUPPRESSED = 'email.suppressed';
    public const OPENED = 'email.opened';
    public const CLICKED = 'email.clicked';

    /** Every event type. */
    public const ALL = [
        self::SENT,
        self::DELIVERED,
        self::DELIVERY_DELAYED,
        self::BOUNCED,
        self::COMPLAINED,
        self::REJECTED,
        self::RENDERING_FAILURE,
        self::SUPPRESSED,
        self::OPENED,
        self::CLICKED,
    ];

    private function __construct()
    {
    }
}
