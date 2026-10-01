<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Infrastructure;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * All timestamps in the 2FA tables are UTC, independent of the shop's or the server's time zone.
 */
final class UtcDateTime
{
    public const FORMAT = 'Y-m-d H:i:s';

    public static function format(DateTimeInterface $moment): string
    {
        return DateTimeImmutable::createFromInterface($moment)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(self::FORMAT);
    }

    public static function parse(string $stored): DateTimeImmutable
    {
        return new DateTimeImmutable($stored, new DateTimeZone('UTC'));
    }
}
