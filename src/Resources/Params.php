<?php

declare(strict_types=1);

namespace Vaartix\Resources;

/** @internal */
final class Params
{
    /** snake_case keys PHP developers expect, mapped to the API's names. */
    private const NAMES = [
        'reply_to' => 'replyTo',
        'scheduled_at' => 'scheduledAt',
        'content_type' => 'contentType',
        'content_id' => 'contentId',
    ];

    /**
     * @param array<array-key, mixed> $params
     * @return array<array-key, mixed>
     */
    public static function toApi(array $params): array
    {
        $out = [];
        foreach ($params as $key => $value) {
            $name = is_string($key) ? (self::NAMES[$key] ?? $key) : $key;
            $out[$name] = is_array($value) ? self::toApi($value) : $value;
        }
        return $out;
    }

    public static function id(string $id): string
    {
        return rawurlencode($id);
    }
}
