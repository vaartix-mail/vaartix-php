<?php

declare(strict_types=1);

namespace Vaartix;

use InvalidArgumentException;

/**
 * Reads the X-Vaartix-Tracking header format used by the SMTP relay ("opens=off, clicks=on"), so
 * Laravel and Symfony emails can set tracking the same way.
 */
final class Tracking
{
    public const HEADER = 'X-Vaartix-Tracking';

    private const VALUES = [
        'on' => true, 'true' => true, '1' => true,
        'off' => false, 'false' => false, '0' => false,
    ];

    /**
     * Parses a header value into the API's `tracking` object. Names and values are
     * case-insensitive and spaces are ignored; a name left out uses the domain's default.
     *
     * @param array{opens?: bool, clicks?: bool} $into Earlier headers; later values win.
     * @return array{opens?: bool, clicks?: bool}
     * @throws InvalidArgumentException
     */
    public static function parseHeader(string $value, array $into = []): array
    {
        $compact = strtolower(str_replace([' ', "\t"], '', $value));
        if ($compact === '') {
            throw new InvalidArgumentException('X-Vaartix-Tracking: use e.g. "opens=off, clicks=off".');
        }
        foreach (explode(',', $compact) as $pair) {
            if ($pair === '') {
                continue;
            }
            $parts = explode('=', $pair, 2);
            $name = $parts[0];
            $setting = $parts[1] ?? null;
            if (($name !== 'opens' && $name !== 'clicks') || $setting === null || !array_key_exists($setting, self::VALUES)) {
                throw new InvalidArgumentException(
                    sprintf('X-Vaartix-Tracking: "%s" is not opens=on|off or clicks=on|off.', $pair),
                );
            }
            if ($name === 'opens') {
                $into['opens'] = self::VALUES[$setting];
            } else {
                $into['clicks'] = self::VALUES[$setting];
            }
        }
        return $into;
    }
}
