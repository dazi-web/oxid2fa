<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit;

use DaziWeb\Oxid2Fa\Application\AuditEntry;
use DaziWeb\Oxid2Fa\Application\AuditReport;
use DaziWeb\Oxid2Fa\Application\AuditTrail;
use DaziWeb\Oxid2Fa\Tests\Unit\Support\FakeUserDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuditReport::class)]
final class AuditReportTest extends TestCase
{
    public function testShowsLoginNamesInsteadOfIds(): void
    {
        $trail = new class implements AuditTrail {
            public function recent(int $limit, ?string $userId = null): array
            {
                return [
                    new AuditEntry('2026-10-01 10:00:00', '2FA_RESET', 'u1', 'u2', '203.0.113.7'),
                    new AuditEntry('2026-10-01 09:00:00', '2FA_RESET', 'u1', 'cli', ''),
                ];
            }
        };

        $rows = (new AuditReport($trail, new FakeUserDirectory()))->recent(10);

        $this->assertSame('u1@example.com', $rows[0]->account);
        $this->assertSame('u2@example.com', $rows[0]->actor);
        $this->assertSame('cli', $rows[1]->actor);
        $this->assertSame('203.0.113.7', $rows[0]->origin);
    }
}
