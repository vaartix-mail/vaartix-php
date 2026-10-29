<?php

declare(strict_types=1);

namespace Vaartix\Exceptions;

use RuntimeException;

/**
 * Thrown when the API returns an error or cannot be reached.
 *
 * `$type` is a stable code such as `validation_error` or `rate_limited`
 * (see https://vaartix.com/docs/concepts/errors/). `$statusCode` is null for network errors.
 */
final class VaartixException extends RuntimeException
{
    public function __construct(
        public readonly string $type,
        string $message,
        public readonly ?int $statusCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }
}
