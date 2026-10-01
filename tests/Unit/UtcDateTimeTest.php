<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit;

use DaziWeb\Oxid2Fa\Infrastructure\UtcDateTime;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UtcDateTime::class)]
final class UtcDateTimeTest extends TestCase
{
    public function testStoresInUtcWhateverTheTimeZoneOfTheMoment(): void
    {
        $berlin = new DateTimeImmutable('2026-10-01 14:00:00', new \DateTimeZone('Europe/Berlin'));

        $this->assertSame('2026-10-01 12:00:00', UtcDateTime::format($berlin));
    }

    public function testRoundTrip(): void
    {
        $moment = UtcDateTime::parse('2026-10-01 12:00:00');

        $this->assertSame('2026-10-01 12:00:00', UtcDateTime::format($moment));
        $this->assertSame('UTC', $moment->getTimezone()->getName());
    }
}
