<?php

declare(strict_types=1);

namespace Vaartix\Exceptions;

use RuntimeException;

/** Thrown by `Webhook::verify()` when a request's signature or timestamp is not valid. */
final class WebhookVerificationException extends RuntimeException
{
}
